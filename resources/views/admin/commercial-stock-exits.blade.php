@extends('admin.layout')

@section('content')
@php
    $quantity = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, ',', ' '), '0'), ',');
    $reasons = \App\Models\StockExit::reasons();
    // Destination d'une sortie : « delivery » pour une livraison client, sinon le motif.
    $destinationOf = fn ($exit) => $exit->delivery_id ? 'delivery' : ($exit->reason ?: 'unknown');
    $tones = ['inventory' => 'cyan', 'delivery' => 'blue', 'sale' => 'green', 'internal_use' => 'purple', 'damage' => 'red', 'loss' => 'red', 'supplier_return' => 'orange', 'gift' => 'pink', 'other' => 'navy', 'unknown' => 'navy'];
    $deliveries = $exits->whereNotNull('delivery_id');
    $losses = $exits->filter(fn ($exit) => in_array($exit->reason, ['damage', 'loss'], true));
    $stats = [
        ['Sorties', $exits->count(), 'bi-box-arrow-up', 'orange', $exits->isNotEmpty() ? 'dernière le ' . $exits->first()->exit_date->format('d/m/Y') : 'aucune sortie'],
        ['Valeur sortie', money((float) $exits->sum('total_value')), 'bi-cash-stack', 'blue', 'au prix d’achat des lots'],
        ['Livrées aux clients', money((float) $deliveries->sum('total_value')), 'bi-truck', 'green', $deliveries->count() . ' livraison(s)'],
        ['Pertes et casse', money((float) $losses->sum('total_value')), 'bi-exclamation-triangle', 'red', $losses->count() . ' sortie(s)'],
    ];
    $details = $exits->mapWithKeys(fn ($exit) => [$exit->id => [
        'title' => 'Sortie du ' . $exit->exit_date->format('d/m/Y'),
        'origin' => $exit->originLabel() . ($exit->delivery?->order?->customer_order_code ? ' · BC ' . $exit->delivery->order->customer_order_code : ''),
        'note' => $exit->note ?: ($exit->delivery_id ? 'Sortie créée à la validation de la livraison.' : ''),
        'total' => money((float) $exit->total_value),
        'lines' => $exit->lines->map(fn ($line) => [
            'designation' => $line->designation,
            'unit' => $line->unit ?: '—',
            'quantity' => $quantity($line->quantity),
            // Traçabilité : chaque quantité sortie remonte à sa réception et à son fournisseur.
            'lot' => $line->entryLine?->stockEntry
                ? 'Réception du ' . $line->entryLine->stockEntry->entry_date->format('d/m/Y') . ' · ' . ($line->entryLine->stockEntry->supplier_name ?: ($line->entryLine->stockEntry->stock_inventory_id ? 'régularisation d’inventaire' : 'fournisseur non renseigné'))
                : '—',
            'price' => money((float) $line->unit_price),
            'value' => money((float) $line->total_value),
        ])->values(),
    ]]);
    $usedDestinations = $exits->map($destinationOf)->unique();
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" subtitle="Marchandises sorties du stock : livraisons aux clients et sorties manuelles, avec leur motif." :back="route('admin.commercial')" back-label="Commercial">
        <x-slot:actions>
            <a href="{{ route('admin.commercial.module', 'etat-stock') }}" class="dg-btn dg-btn--outline"><i class="bi bi-boxes"></i>État du stock</a>
            <a href="{{ route('admin.commercial.stock-exits.create') }}" class="dg-btn dg-btn--primary"><i class="bi bi-plus-lg"></i>Nouvelle sortie</a>
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
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-orange"><i class="bi bi-box-arrow-up"></i></span>Sorties enregistrées</h2>
            <div class="d-flex flex-wrap align-items-center gap-2" role="search" aria-label="Filtrer les sorties">
                <label class="dg-search" style="max-width:220px">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input id="exitSearch" type="search" placeholder="Article, client…" aria-label="Rechercher une sortie">
                </label>
                <select id="exitDestination" class="form-select" style="max-width:220px" aria-label="Destination">
                    <option value="">Toutes les destinations</option>
                    @if($usedDestinations->contains('delivery'))<option value="delivery">Livraisons aux clients</option>@endif
                    @foreach($reasons as $key => $label)
                        @if($usedDestinations->contains($key))<option value="{{ $key }}">{{ $label }}</option>@endif
                    @endforeach
                    @if($usedDestinations->contains('inventory'))<option value="inventory">Écarts d’inventaire</option>@endif
                    @if($usedDestinations->contains('unknown'))<option value="unknown">Motif non renseigné</option>@endif
                </select>
                <div class="dg-period">
                    <input id="exitDateStart" type="date" class="dg-input" aria-label="Du">
                    <span class="dg-period__sep">au</span>
                    <input id="exitDateEnd" type="date" class="dg-input" aria-label="Au">
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 exits-table" id="exitsTable">
                <thead>
                    <tr>
                        <th>Sortie</th>
                        <th>Destination</th>
                        <th>Articles sortis</th>
                        <th class="text-end">Valeur</th>
                        <th class="text-end"><span class="visually-hidden">Détail</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($exits as $exit)
                    @php
                        $destination = $destinationOf($exit);
                        $names = $exit->lines->pluck('designation')->unique()->values();
                    @endphp
                    <tr data-date="{{ $exit->exit_date->format('Y-m-d') }}" data-destination="{{ $destination }}" data-search="{{ $exit->lines->pluck('designation')->implode(' ') }} {{ $exit->note }}">
                        <td class="text-nowrap">
                            <span class="d-block fw-semibold">{{ $exit->exit_date->format('d/m/Y') }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ $exit->delivery?->order?->customer_order_code ? 'BC ' . $exit->delivery->order->customer_order_code : $exit->lines->count() . ' ligne(s)' }}</span>
                        </td>
                        <td>
                            <span class="exit-destination dg-tone-{{ $tones[$destination] ?? 'navy' }}"><i class="bi {{ $exit->delivery_id ? 'bi-truck' : ($exit->reason === 'inventory' ? 'bi-clipboard-check' : 'bi-tag') }}"></i>{{ $exit->originLabel() }}</span>
                            @if($exit->note)<span class="d-block dg-muted text-truncate mt-1" style="font-size:12.5px;max-width:240px">{{ $exit->note }}</span>@endif
                        </td>
                        <td>
                            <span class="d-block text-truncate" style="max-width:260px">{{ $names->take(3)->implode(', ') }}</span>
                            @if($names->count() > 3)<span class="d-block dg-muted" style="font-size:12.5px">et {{ $names->count() - 3 }} autre(s)</span>@endif
                        </td>
                        <td class="text-end dg-cell-num">{{ money((float) $exit->total_value) }}</td>
                        <td class="text-end"><button type="button" class="dg-btn dg-btn--outline dg-btn--sm" data-bs-toggle="modal" data-bs-target="#exitModal" data-exit="{{ $exit->id }}">Voir</button></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-5">
                            <div class="dg-chart-empty" style="min-height:220px">
                                <span class="dg-tile dg-tone-orange"><i class="bi bi-box-arrow-up"></i></span>
                                <div><strong>Aucune sortie de stock</strong>Les livraisons de commandes et les sorties manuelles (usage interne, casse, retour…) apparaîtront ici.</div>
                                <a href="{{ route('admin.commercial.stock-exits.create') }}" class="dg-btn dg-btn--primary dg-btn--sm"><i class="bi bi-plus-lg"></i>Nouvelle sortie</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Hors du tableau : une ligne fusionnée dans le tbody empêcherait DataTables de s'initialiser. --}}
        <div class="dg-chart-empty mt-4 d-none" id="exitsNoResult" style="min-height:160px">
            <span class="dg-tile dg-tone-orange"><i class="bi bi-search"></i></span>
            <div><strong>Aucune sortie ne correspond à vos filtres</strong>Modifiez la recherche, la destination ou la période.</div>
        </div>
    </div>

    <div class="modal fade dg-tone-orange" id="exitModal" tabindex="-1" aria-labelledby="exitModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exitModalTitle"><i class="bi bi-box-arrow-up me-2"></i><span data-exit-field="title"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="exit-origin mb-4">
                        <span class="dg-tile dg-tile--sm dg-tone-orange"><i class="bi bi-signpost-split"></i></span>
                        <div>
                            <div class="fw-semibold" data-exit-field="origin"></div>
                            <div class="dg-muted" style="font-size:13px" data-exit-field="note"></div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 no-export no-column-sort">
                            <thead><tr><th>Désignation</th><th>Unité</th><th class="text-end">Qté</th><th>Lot d’origine</th><th class="text-end">Prix d’achat</th><th class="text-end">Valeur</th></tr></thead>
                            <tbody id="exitLines"></tbody>
                        </table>
                    </div>
                    <div class="exit-total mt-3"><span>Valeur au prix d’achat</span><strong data-exit-field="total"></strong></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="exitDetails">@json($details)</script>
<style>
    .exits-table { min-width: 780px; }
    .dg-scope .exits-table > thead > tr > th, .dg-scope .exits-table > tbody > tr > td { padding-left: 12px !important; padding-right: 12px !important; }
    .exit-destination { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px; background: var(--dg-tone-bg); color: var(--dg-tone); font-size: 13px; font-weight: 600; white-space: nowrap; }
    .exit-destination .bi { color: inherit; }
    .exit-origin { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: var(--dg-radius); background: var(--dg-table-head); }
    .exit-total { display: flex; justify-content: space-between; align-items: baseline; margin-left: auto; width: min(100%, 360px); padding: 12px 16px; border-radius: var(--dg-radius); background: var(--dg-tone-bg); color: var(--dg-tone); }
    .exit-total span { font-weight: 600; }
    .exit-total strong { font-size: 18px; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Filtres : article ou client, destination et période.
    const rows = Array.from(document.querySelectorAll('#exitsTable tbody tr[data-date]'));
    const search = document.getElementById('exitSearch');
    const destination = document.getElementById('exitDestination');
    const dateStart = document.getElementById('exitDateStart');
    const dateEnd = document.getElementById('exitDateEnd');
    const noResult = document.getElementById('exitsNoResult');
    const filterExits = function () {
        const term = search.value.trim().toLowerCase();
        let visible = 0;
        rows.forEach(function (row) {
            const show = (!term || (row.dataset.search + ' ' + row.textContent).toLowerCase().includes(term))
                && (!destination.value || row.dataset.destination === destination.value)
                && (!dateStart.value || row.dataset.date >= dateStart.value)
                && (!dateEnd.value || row.dataset.date <= dateEnd.value);
            row.style.display = show ? '' : 'none';
            visible += show ? 1 : 0;
        });
        noResult.classList.toggle('d-none', !rows.length || visible > 0);
    };
    [search, destination, dateStart, dateEnd].forEach(field => field.addEventListener(field === search ? 'input' : 'change', filterExits));

    // Détail d'une sortie : lignes, lot d'origine et fournisseur.
    const details = JSON.parse(document.getElementById('exitDetails').textContent || '{}');
    const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
    document.getElementById('exitModal').addEventListener('show.bs.modal', function (event) {
        const exit = details[event.relatedTarget?.dataset.exit];
        if (!exit) return;
        this.querySelectorAll('[data-exit-field]').forEach(field => { field.textContent = exit[field.dataset.exitField] ?? ''; });
        document.getElementById('exitLines').innerHTML = exit.lines.map(line =>
            '<tr><td class="fw-semibold">' + escape(line.designation) + '</td><td>' + escape(line.unit) + '</td><td class="text-end dg-cell-num">' + escape(line.quantity) + '</td>'
            + '<td class="dg-muted" style="font-size:13px">' + escape(line.lot) + '</td><td class="text-end dg-cell-num">' + escape(line.price) + '</td>'
            + '<td class="text-end dg-cell-num fw-semibold">' + escape(line.value) + '</td></tr>').join('');
    });
});
</script>
@endsection
