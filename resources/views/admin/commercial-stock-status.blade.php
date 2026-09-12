@extends('admin.layout')

@section('content')
@php
    $quantity = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, ',', ' '), '0'), ',');
    $states = [
        'ok' => ['En stock', 'success', 'bi-check-circle', 'En stock'],
        'low' => ['Stock faible', 'warning', 'bi-exclamation-triangle', 'Stock faible'],
        'out' => ['Rupture', 'danger', 'bi-x-circle', 'En rupture'],
    ];
    $byState = $items->groupBy('state');
    $inStock = $items->where('available', '>', 0);
    $stats = [
        ['Articles en stock', $inStock->count(), 'bi-boxes', 'purple', $items->count() . ' article(s) suivis'],
        ['Valeur du stock', money((float) $items->sum('stock_value')), 'bi-safe', 'blue', 'au prix d’achat de chaque réception'],
        ['Bénéfice potentiel', money((float) $items->sum('potential_profit')), 'bi-graph-up-arrow', 'green', 'à la revente du disponible'],
        ['À réapprovisionner', $byState->get('out', collect())->count() + $byState->get('low', collect())->count(), 'bi-cart-plus', 'red',
            $byState->get('out', collect())->count() . ' en rupture, ' . $byState->get('low', collect())->count() . ' à ' . \App\Services\StockService::LOW_STOCK . ' ou moins'],
    ];
    $supplierNames = $items->flatMap(fn ($item) => $item['suppliers'])->unique()->sort()->values();
    $details = $items->mapWithKeys(fn ($item) => [$item['key'] => [
        'title' => $item['designation'],
        'subtitle' => collect([$item['article'], $item['unit'] ? 'unité : ' . $item['unit'] : null])->filter()->implode(' · '),
        'available' => $quantity($item['available']) . ' ' . $item['unit'],
        'value' => money((float) $item['stock_value']),
        'profit' => money((float) $item['potential_profit']),
        'sale' => money((float) $item['sale_price']),
        'lots' => collect($item['lots'])->map(fn ($lot) => [
            'date' => $lot['date']->format('d/m/Y'),
            'supplier' => $lot['supplier'] ?: 'Fournisseur non renseigné',
            'reference' => $lot['reference'] ? 'BL ' . $lot['reference'] : '',
            'received' => $quantity($lot['received']),
            'remaining' => $quantity($lot['remaining']),
            'price' => money($lot['price']),
        ]),
        'exits' => collect($item['exits'])->map(fn ($exit) => [
            'date' => $exit['date']?->format('d/m/Y') ?? '—',
            'destination' => $exit['destination'],
            'quantity' => $quantity($exit['quantity']),
        ]),
    ]]);
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.commercial')" back-label="Commercial">
        <x-slot:actions>
            <a href="{{ route('admin.commercial.stock-exits.create') }}" class="dg-btn dg-btn--outline"><i class="bi bi-box-arrow-up"></i>Nouvelle sortie</a>
            <a href="{{ route('admin.commercial.stock-entries.create') }}" class="dg-btn dg-btn--primary"><i class="bi bi-plus-lg"></i>Nouvelle réception</a>
        </x-slot:actions>
    </x-dg.page-header>

    <div class="dg-kpi-grid">
        @foreach($stats as [$label, $value, $icon, $color, $hint])
            <x-dg.kpi :label="$label" :value="$value" :icon="$icon" :color="$color" :hint="$hint" />
        @endforeach
    </div>

    <div class="dg-card dg-card--table">
        <div class="dg-card__header flex-wrap">
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-purple"><i class="bi bi-boxes"></i></span>Articles</h2>
            <div class="d-flex flex-wrap align-items-center gap-2 status-filters" role="search" aria-label="Filtrer les articles">
                <label class="dg-search" style="max-width:240px">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input id="statusSearch" type="search" placeholder="Article, référence…" aria-label="Rechercher un article">
                </label>
                <select id="statusSupplier" class="form-select" style="max-width:230px" aria-label="Fournisseur">
                    <option value="">Tous les fournisseurs</option>
                    @foreach($supplierNames as $supplierName)
                        <option value="{{ mb_strtolower($supplierName) }}">{{ $supplierName }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if($items->isNotEmpty())
            <div class="dg-tabs mb-4" role="group" aria-label="Filtrer par état">
                <button type="button" class="dg-tab is-active" data-state-filter="" aria-pressed="true">Tous ({{ $items->count() }})</button>
                @foreach($states as $value => [, , , $tabLabel])
                    <button type="button" class="dg-tab" data-state-filter="{{ $value }}" aria-pressed="false">{{ $tabLabel }} ({{ $byState->get($value, collect())->count() }})</button>
                @endforeach
            </div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle mb-0 status-table" id="statusTable">
                <thead>
                    <tr>
                        <th>Article</th>
                        <th>Fournisseur</th>
                        <th class="text-end">Reçu / sorti</th>
                        <th class="text-end">Disponible</th>
                        <th class="text-end">Valeur du stock</th>
                        <th class="text-end">Prix de vente</th>
                        <th class="text-end"><span class="visually-hidden">Fiche</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($items as $item)
                    @php [$stateLabel, $stateTone, $stateIcon] = $states[$item['state']]; @endphp
                    <tr data-state="{{ $item['state'] }}" data-suppliers="{{ collect($item['suppliers'])->map(fn ($name) => mb_strtolower($name))->implode('|') }}">
                        <td>
                            <span class="d-block fw-semibold">{{ $item['designation'] }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ $item['article'] ?: 'Sans référence' }} · reçu le {{ $item['last_entry']->format('d/m/Y') }}</span>
                        </td>
                        <td>
                            @if($item['suppliers'])
                                <span class="d-block text-truncate" style="max-width:180px">{{ $item['suppliers'][0] }}</span>
                                @if(count($item['suppliers']) > 1)<span class="d-block dg-muted" style="font-size:12.5px">et {{ count($item['suppliers']) - 1 }} autre(s)</span>@endif
                            @else
                                <span class="dg-muted">Non renseigné</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap dg-muted">{{ $quantity($item['received']) }} / {{ $quantity($item['exited']) }}</td>
                        <td class="text-end text-nowrap">
                            <span class="d-block fw-semibold dg-cell-num">{{ $quantity($item['available']) }} {{ $item['unit'] }}</span>
                            <span class="dg-badge dg-badge--{{ $stateTone }} mt-1" style="font-size:11.5px;padding:2px 8px"><i class="bi {{ $stateIcon }}"></i>{{ $stateLabel }}</span>
                        </td>
                        <td class="text-end dg-cell-num">{{ money((float) $item['stock_value']) }}</td>
                        <td class="text-end dg-cell-num">{{ money((float) $item['sale_price']) }}</td>
                        <td class="text-end"><button type="button" class="dg-btn dg-btn--outline dg-btn--sm" data-bs-toggle="modal" data-bs-target="#articleModal" data-article="{{ $item['key'] }}">Fiche</button></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-5">
                            <div class="dg-chart-empty" style="min-height:220px">
                                <span class="dg-tile dg-tone-purple"><i class="bi bi-boxes"></i></span>
                                <div><strong>Aucun article en stock</strong>Enregistrez une réception : chaque article reçu apparaîtra ici avec ses quantités, sa valeur et son fournisseur.</div>
                                <a href="{{ route('admin.commercial.stock-entries.create') }}" class="dg-btn dg-btn--primary dg-btn--sm"><i class="bi bi-plus-lg"></i>Nouvelle réception</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Hors du tableau : une ligne fusionnée dans le tbody empêcherait DataTables de s'initialiser. --}}
        <div class="dg-chart-empty mt-4 d-none" id="statusNoResult" style="min-height:160px">
            <span class="dg-tile dg-tone-purple"><i class="bi bi-search"></i></span>
            <div><strong>Aucun article ne correspond à vos filtres</strong>Modifiez la recherche, le fournisseur ou l’état.</div>
        </div>
    </div>

    <div class="modal fade dg-tone-purple" id="articleModal" tabindex="-1" aria-labelledby="articleModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="articleModalTitle"><i class="bi bi-box-seam me-2"></i><span data-article-field="title"></span></h5>
                        <div class="text-muted" style="font-size:13px" data-article-field="subtitle"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="article-stats mb-4">
                        <div><span>Disponible</span><strong data-article-field="available"></strong></div>
                        <div><span>Valeur du stock</span><strong data-article-field="value"></strong></div>
                        <div><span>Bénéfice potentiel</span><strong class="text-success" data-article-field="profit"></strong></div>
                        <div><span>Prix de vente</span><strong data-article-field="sale"></strong></div>
                    </div>
                    <h6 class="fw-semibold mb-2"><i class="bi bi-box-arrow-in-down text-primary me-1"></i>Réceptions (lots)</h6>
                    <div class="table-responsive mb-4">
                        <table class="table align-middle mb-0 no-export no-column-sort">
                            <thead><tr><th>Réception</th><th>Fournisseur</th><th class="text-end">Reçu</th><th class="text-end">Restant</th><th class="text-end">Prix d’achat</th></tr></thead>
                            <tbody id="articleLots"></tbody>
                        </table>
                    </div>
                    <h6 class="fw-semibold mb-2"><i class="bi bi-box-arrow-up text-warning me-1"></i>Sorties</h6>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 no-export no-column-sort">
                            <thead><tr><th>Date</th><th>Destination</th><th class="text-end">Quantité</th></tr></thead>
                            <tbody id="articleExits"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="articleDetails">@json($details)</script>
<style>
    .status-table { min-width: 860px; }
    @media (min-width: 768px) { .status-filters { flex-wrap: nowrap !important; } .status-filters .dg-search { min-width: 220px; } }
    .dg-scope .status-table > thead > tr > th, .dg-scope .status-table > tbody > tr > td { padding-left: 10px !important; padding-right: 10px !important; }
    .article-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    .article-stats > div { padding: 12px 14px; border-radius: var(--dg-radius); background: var(--dg-tone-bg); }
    .article-stats span { display: block; font-size: 12.5px; color: var(--dg-muted); }
    .article-stats strong { font-size: 17px; color: var(--dg-tone); }
    .article-stats strong.text-success { color: #059669 !important; }
    @media (max-width: 767px) { .article-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Filtres : recherche, fournisseur et état.
    const rows = Array.from(document.querySelectorAll('#statusTable tbody tr[data-state]'));
    const search = document.getElementById('statusSearch');
    const supplier = document.getElementById('statusSupplier');
    const tabs = Array.from(document.querySelectorAll('[data-state-filter]'));
    const noResult = document.getElementById('statusNoResult');
    let state = '';
    const filterItems = function () {
        const term = search.value.trim().toLowerCase();
        let visible = 0;
        rows.forEach(function (row) {
            const show = (!term || row.textContent.toLowerCase().includes(term))
                && (!supplier.value || row.dataset.suppliers.split('|').includes(supplier.value))
                && (!state || row.dataset.state === state);
            row.style.display = show ? '' : 'none';
            visible += show ? 1 : 0;
        });
        noResult.classList.toggle('d-none', !rows.length || visible > 0);
    };
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            state = tab.dataset.stateFilter;
            tabs.forEach(function (item) {
                item.classList.toggle('is-active', item === tab);
                item.setAttribute('aria-pressed', item === tab ? 'true' : 'false');
            });
            filterItems();
        });
    });
    search.addEventListener('input', filterItems);
    supplier.addEventListener('change', filterItems);

    // Fiche article : lots reçus (avec leur fournisseur) et sorties.
    const details = JSON.parse(document.getElementById('articleDetails').textContent || '{}');
    const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
    document.getElementById('articleModal').addEventListener('show.bs.modal', function (event) {
        const item = details[event.relatedTarget?.dataset.article];
        if (!item) return;
        this.querySelectorAll('[data-article-field]').forEach(field => { field.textContent = item[field.dataset.articleField] ?? ''; });
        document.getElementById('articleLots').innerHTML = item.lots.map(lot =>
            '<tr><td class="fw-semibold">' + escape(lot.date) + '</td><td><span class="d-block">' + escape(lot.supplier) + '</span>'
            + (lot.reference ? '<span class="d-block dg-muted" style="font-size:12.5px">' + escape(lot.reference) + '</span>' : '') + '</td>'
            + '<td class="text-end dg-cell-num">' + escape(lot.received) + '</td><td class="text-end dg-cell-num fw-semibold">' + escape(lot.remaining) + '</td>'
            + '<td class="text-end dg-cell-num">' + escape(lot.price) + '</td></tr>').join('');
        document.getElementById('articleExits').innerHTML = item.exits.length
            ? item.exits.map(exit => '<tr><td>' + escape(exit.date) + '</td><td>' + escape(exit.destination) + '</td><td class="text-end dg-cell-num">' + escape(exit.quantity) + '</td></tr>').join('')
            : '<tr><td colspan="3" class="dg-muted">Aucune sortie pour cet article.</td></tr>';
    });
});
</script>
@endsection
