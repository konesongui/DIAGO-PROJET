@extends('admin.layout')

@section('content')
@php
    $quantity = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, ',', ' '), '0'), ',');
    $marginOf = fn ($purchase, $profit) => (float) $purchase > 0 ? round((float) $profit / (float) $purchase * 100, 1) : null;
    $totalPurchase = (float) $entries->sum('total_purchase');
    $totalProfit = (float) $entries->sum('total_profit');
    $allLines = $entries->flatMap->lines;
    $articles = $allLines->map(fn ($line) => mb_strtolower(trim($line->designation . '|' . $line->article)))->unique()->count();
    $averageMargin = $marginOf($totalPurchase, $totalProfit);
    $stats = [
        ['Réceptions', $entries->count(), 'bi-box-arrow-in-down', 'blue', $entries->isNotEmpty() ? 'dernière le ' . $entries->first()->entry_date->format('d/m/Y') : 'aucune réception'],
        ['Valeur achetée', money($totalPurchase), 'bi-cart-check', 'orange', 'prix d’achat des quantités reçues'],
        ['Bénéfice attendu', money($totalProfit), 'bi-graph-up-arrow', 'green', $averageMargin !== null ? 'marge moyenne ' . number_format($averageMargin, 1, ',', ' ') . ' %' : 'à la revente'],
        ['Articles reçus', $articles, 'bi-tags', 'purple', $allLines->count() . ' ligne(s), ' . $entries->pluck('supplier_name')->filter()->unique()->count() . ' fournisseur(s)'],
    ];
    // Détail de chaque réception, affiché dans une fenêtre unique.
    $details = $entries->mapWithKeys(fn ($entry) => [$entry->id => [
        'title' => 'Réception du ' . $entry->entry_date->format('d/m/Y'),
        'supplier' => $entry->supplier?->name ?? $entry->supplier_name ?? ($entry->stock_inventory_id ? 'Régularisation d’inventaire' : 'Fournisseur non renseigné'),
        'reference' => $entry->supplier_reference ? 'Bon de livraison ' . $entry->supplier_reference : 'Sans numéro de bon de livraison',
        'purchase' => money((float) $entry->total_purchase),
        'profit' => money((float) $entry->total_profit),
        'margin' => ($margin = $marginOf($entry->total_purchase, $entry->total_profit)) !== null ? number_format($margin, 1, ',', ' ') . ' %' : '—',
        'lines' => $entry->lines->map(fn ($line) => [
            'designation' => $line->designation,
            'article' => $line->article ?: '',
            'unit' => $line->unit ?: '—',
            'quantity' => $quantity($line->quantity),
            'purchase' => money((float) $line->purchase_price),
            'sale' => money((float) $line->purchase_price + (float) $line->profit_per_unit),
            'total' => money((float) $line->total_purchase),
        ])->values(),
    ]]);
    $supplierNames = $entries->map(fn ($entry) => $entry->supplier?->name ?? $entry->supplier_name)->filter()->unique()->sort()->values();
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" subtitle="Réceptions de marchandises : quantités, prix d’achat et bénéfice attendu à la revente." :back="route('admin.commercial')" back-label="Commercial">
        <x-slot:actions>
            <a href="{{ route('admin.commercial.module', 'etat-stock') }}" class="dg-btn dg-btn--outline"><i class="bi bi-boxes"></i>État du stock</a>
            <a href="{{ route('admin.commercial.stock-entries.create') }}" class="dg-btn dg-btn--primary"><i class="bi bi-plus-lg"></i>Nouvelle réception</a>
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="dg-kpi-grid">
        @foreach($stats as [$label, $value, $icon, $color, $hint])
            <x-dg.kpi :label="$label" :value="$value" :icon="$icon" :color="$color" :hint="$hint" />
        @endforeach
    </div>

    <div class="dg-card dg-card--table">
        <div class="dg-card__header flex-wrap">
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-blue"><i class="bi bi-box-arrow-in-down"></i></span>Réceptions enregistrées</h2>
            <div class="d-flex flex-wrap align-items-center gap-2" role="search" aria-label="Filtrer les réceptions">
                <label class="dg-search" style="max-width:220px">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input id="entrySearch" type="search" placeholder="Article, bon…" aria-label="Rechercher un article">
                </label>
                <select id="entrySupplier" class="form-select" style="max-width:220px" aria-label="Fournisseur">
                    <option value="">Tous les fournisseurs</option>
                    @foreach($supplierNames as $supplierName)
                        <option value="{{ mb_strtolower($supplierName) }}">{{ $supplierName }}</option>
                    @endforeach
                    @if($entries->whereNotNull('stock_inventory_id')->isNotEmpty())<option value="inventaire">Régularisations d’inventaire</option>@endif
                    <option value="-">Non renseigné</option>
                </select>
                <div class="dg-period">
                    <input id="entryDateStart" type="date" class="dg-input" aria-label="Du">
                    <span class="dg-period__sep">au</span>
                    <input id="entryDateEnd" type="date" class="dg-input" aria-label="Au">
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 entries-table" id="entriesTable">
                <thead>
                    <tr>
                        <th>Réception</th>
                        <th>Fournisseur</th>
                        <th>Articles reçus</th>
                        <th class="text-end">Valeur d’achat</th>
                        <th class="text-end">Bénéfice attendu</th>
                        <th class="text-end"><span class="visually-hidden">Détail</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($entries as $entry)
                    @php
                        $names = $entry->lines->pluck('designation');
                        $margin = $marginOf($entry->total_purchase, $entry->total_profit);
                        $supplierName = $entry->supplier?->name ?? $entry->supplier_name;
                    @endphp
                    <tr data-date="{{ $entry->entry_date->format('Y-m-d') }}" data-supplier="{{ $supplierName ? mb_strtolower($supplierName) : ($entry->stock_inventory_id ? 'inventaire' : '-') }}" data-search="{{ $entry->lines->map(fn ($l) => $l->designation . ' ' . $l->article)->implode(' ') }}">
                        <td class="text-nowrap">
                            <span class="d-block fw-semibold">{{ $entry->entry_date->format('d/m/Y') }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ $entry->supplier_reference ? 'BL ' . $entry->supplier_reference : $entry->lines->count() . ' ligne(s)' }}</span>
                        </td>
                        <td>
                            @if($supplierName)
                                <span class="d-block fw-semibold text-truncate" style="max-width:190px">{{ $supplierName }}</span>
                            @elseif($entry->stock_inventory_id)
                                <span class="dg-badge dg-badge--neutral"><i class="bi bi-clipboard-check"></i>Inventaire</span>
                            @else
                                <span class="dg-badge dg-badge--warning"><i class="bi bi-question-circle"></i>Non renseigné</span>
                            @endif
                        </td>
                        <td>
                            <span class="d-block text-truncate" style="max-width:230px">{{ $names->take(3)->implode(', ') }}</span>
                            @if($names->count() > 3)<span class="d-block dg-muted" style="font-size:12.5px">et {{ $names->count() - 3 }} autre(s)</span>@endif
                        </td>
                        <td class="text-end dg-cell-num">{{ money((float) $entry->total_purchase) }}</td>
                        <td class="text-end">
                            <span class="d-block dg-cell-num dg-amount-positive">{{ money((float) $entry->total_profit) }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ $margin !== null ? 'marge ' . number_format($margin, 1, ',', ' ') . ' %' : '—' }}</span>
                        </td>
                        <td class="text-end">
                            <button type="button" class="dg-btn dg-btn--outline dg-btn--sm" data-bs-toggle="modal" data-bs-target="#entryModal" data-entry="{{ $entry->id }}">Voir</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-5">
                            <div class="dg-chart-empty" style="min-height:220px">
                                <span class="dg-tile dg-tone-blue"><i class="bi bi-box-arrow-in-down"></i></span>
                                <div><strong>Aucune réception</strong>Chaque réception ajoute des quantités au stock, avec leur prix d’achat et le bénéfice attendu.</div>
                                <a href="{{ route('admin.commercial.stock-entries.create') }}" class="dg-btn dg-btn--primary dg-btn--sm"><i class="bi bi-plus-lg"></i>Nouvelle réception</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Hors du tableau : une ligne fusionnée dans le tbody empêcherait DataTables de s'initialiser. --}}
        <div class="dg-chart-empty mt-4 d-none" id="entriesNoResult" style="min-height:160px">
            <span class="dg-tile dg-tone-blue"><i class="bi bi-search"></i></span>
            <div><strong>Aucune réception ne correspond à vos filtres</strong>Modifiez la recherche ou la période.</div>
        </div>
    </div>

    <div class="modal fade dg-tone-blue" id="entryModal" tabindex="-1" aria-labelledby="entryModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="entryModalTitle"><i class="bi bi-box-arrow-in-down me-2"></i><span data-entry-field="title"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="entry-supplier mb-4">
                        <span class="dg-tile dg-tile--sm dg-tone-indigo"><i class="bi bi-truck"></i></span>
                        <div>
                            <div class="fw-semibold" data-entry-field="supplier"></div>
                            <div class="dg-muted" style="font-size:13px" data-entry-field="reference"></div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 no-export no-column-sort entry-detail">
                            <thead>
                                <tr><th>Désignation</th><th>Unité</th><th class="text-end">Qté</th><th class="text-end">Prix d’achat</th><th class="text-end">Prix de vente</th><th class="text-end">Total achat</th></tr>
                            </thead>
                            <tbody id="entryLines"></tbody>
                        </table>
                    </div>
                    <div class="entry-detail__totals">
                        <div><span>Valeur d’achat</span><strong data-entry-field="purchase"></strong></div>
                        <div><span>Bénéfice attendu</span><strong class="text-success" data-entry-field="profit"></strong></div>
                        <div><span>Marge</span><strong data-entry-field="margin"></strong></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="entryDetails">@json($details)</script>
<style>
    .entries-table { min-width: 800px; }
    .dg-scope .entries-table > thead > tr > th,
    .dg-scope .entries-table > tbody > tr > td { padding-left: 12px !important; padding-right: 12px !important; }
    .entry-supplier { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: var(--dg-radius); background: var(--dg-table-head); }
    .entry-detail__totals { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 12px; margin-top: 16px; }
    .entry-detail__totals > div { min-width: 170px; padding: 10px 14px; border-radius: var(--dg-radius); background: var(--dg-tone-bg); }
    .entry-detail__totals span { display: block; font-size: 12.5px; color: var(--dg-muted); }
    .entry-detail__totals strong { font-size: 16px; color: var(--dg-tone); }
    .entry-detail__totals strong.text-success { color: #059669 !important; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Filtres : article recherché dans les lignes, et période de réception.
    const rows = Array.from(document.querySelectorAll('#entriesTable tbody tr[data-date]'));
    const search = document.getElementById('entrySearch');
    const dateStart = document.getElementById('entryDateStart');
    const dateEnd = document.getElementById('entryDateEnd');
    const supplier = document.getElementById('entrySupplier');
    const noResult = document.getElementById('entriesNoResult');
    const filterEntries = function () {
        const term = search.value.trim().toLowerCase();
        let visible = 0;
        rows.forEach(function (row) {
            const show = (!term || (row.dataset.search + ' ' + row.textContent).toLowerCase().includes(term))
                && (!supplier.value || row.dataset.supplier === supplier.value)
                && (!dateStart.value || row.dataset.date >= dateStart.value)
                && (!dateEnd.value || row.dataset.date <= dateEnd.value);
            row.style.display = show ? '' : 'none';
            visible += show ? 1 : 0;
        });
        noResult.classList.toggle('d-none', !rows.length || visible > 0);
    };
    search.addEventListener('input', filterEntries);
    dateStart.addEventListener('change', filterEntries);
    dateEnd.addEventListener('change', filterEntries);
    supplier.addEventListener('change', filterEntries);

    // Détail d'une réception, rempli à partir des données de la page.
    const details = JSON.parse(document.getElementById('entryDetails').textContent || '{}');
    const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
    document.getElementById('entryModal').addEventListener('show.bs.modal', function (event) {
        const entry = details[event.relatedTarget?.dataset.entry];
        if (!entry) return;
        this.querySelectorAll('[data-entry-field]').forEach(field => { field.textContent = entry[field.dataset.entryField] ?? ''; });
        document.getElementById('entryLines').innerHTML = entry.lines.map(line =>
            '<tr><td><span class="d-block fw-semibold">' + escape(line.designation) + '</span>' + (line.article ? '<span class="d-block dg-muted" style="font-size:12.5px">' + escape(line.article) + '</span>' : '') + '</td>'
            + '<td>' + escape(line.unit) + '</td><td class="text-end dg-cell-num">' + escape(line.quantity) + '</td>'
            + '<td class="text-end dg-cell-num">' + escape(line.purchase) + '</td><td class="text-end dg-cell-num">' + escape(line.sale) + '</td>'
            + '<td class="text-end dg-cell-num fw-semibold">' + escape(line.total) + '</td></tr>').join('');
    });
});
</script>
@endsection
