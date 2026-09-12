@extends('admin.layout')

@section('content')
@php
    $completed = $sales->where('status', '!=', 'cancelled');
    $cancelled = $sales->where('status', 'cancelled');
    $cash = $completed->where('payment_method', 'cash');
    $bank = $completed->where('payment_method', '!=', 'cash');
    $revenue = (float) $completed->sum('total');
    $average = $completed->count() ? $revenue / $completed->count() : 0;
    $stats = [
        ['Chiffre d’affaires TTC', money($revenue), 'bi-graph-up-arrow', 'blue', $completed->count() . ' vente(s) · panier moyen ' . money($average)],
        ['Encaissé en espèces', money((float) $cash->sum('total')), 'bi-cash-stack', 'green', $cash->count() . ' vente(s) en caisse'],
        ['Encaissé en banque', money((float) $bank->sum('total')), 'bi-bank', 'cyan', $bank->count() . ' vente(s) par banque'],
        ['Ventes annulées', $cancelled->count(), 'bi-x-circle', 'red', money((float) $cancelled->sum('total')) . ' rendus'],
    ];
    $listUrl = route('admin.commercial.module', 'point-de-vente');
    $shortcuts = [
        'Aujourd’hui' => [now(), now()],
        '7 derniers jours' => [now()->subDays(6), now()],
        'Ce mois' => [now()->startOfMonth(), now()],
        'Mois dernier' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
    ];
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.commercial')" back-label="Commercial">
        <x-slot:actions>
            <a href="{{ route('admin.commercial.pos.create') }}" class="dg-btn dg-btn--primary"><i class="bi bi-plus-lg"></i>Nouvelle vente</a>
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    {{-- Période : les indicateurs et la liste portent sur les mêmes dates. --}}
    <form method="GET" action="{{ $listUrl }}" class="pos-period mb-6" aria-label="Période affichée">
        <span class="dg-tile dg-tile--sm dg-tone-navy"><i class="bi bi-calendar3"></i></span>
        <span class="fw-semibold">Période</span>
        <div class="dg-period">
            <input type="date" name="du" class="dg-input" value="{{ $from->format('Y-m-d') }}" aria-label="Du">
            <span class="dg-period__sep">au</span>
            <input type="date" name="au" class="dg-input" value="{{ $to->format('Y-m-d') }}" aria-label="Au">
        </div>
        <button class="dg-btn dg-btn--secondary dg-btn--sm"><i class="bi bi-funnel"></i>Afficher</button>
        <div class="d-flex flex-wrap gap-2 ms-lg-auto">
            @foreach($shortcuts as $label => [$start, $end])
                @php $isCurrent = $from->isSameDay($start) && $to->isSameDay($end); @endphp
                <a href="{{ $listUrl . '?' . http_build_query(['du' => $start->format('Y-m-d'), 'au' => $end->format('Y-m-d')]) }}" class="dg-chip {{ $isCurrent ? 'is-active' : '' }}" @if($isCurrent) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
        </div>
    </form>

    {{-- Chiffres de gestion : réservés à l'encadrement, la caisse n'en a pas besoin. --}}
    @if(auth()->user()?->isManager())
        <div class="dg-kpi-grid">
            @foreach($stats as [$label, $value, $icon, $color, $hint])
                <x-dg.kpi :label="$label" :value="$value" :icon="$icon" :color="$color" :hint="$hint" />
            @endforeach
        </div>
    @endif

    <div class="dg-card dg-card--table">
        <div class="dg-card__header flex-wrap">
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-blue"><i class="bi bi-shop"></i></span>Ventes du {{ $from->format('d/m/Y') }} au {{ $to->format('d/m/Y') }}</h2>
            <label class="dg-search" style="max-width:260px">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input id="posSearch" type="search" placeholder="Ticket, client, article…" aria-label="Rechercher une vente">
            </label>
        </div>

        @if($sales->isNotEmpty())
            <div class="dg-tabs mb-4" role="group" aria-label="Filtrer par statut">
                <button type="button" class="dg-tab is-active" data-state-filter="" aria-pressed="true">Toutes ({{ $sales->count() }})</button>
                <button type="button" class="dg-tab" data-state-filter="completed" aria-pressed="false">Terminées ({{ $completed->count() }})</button>
                <button type="button" class="dg-tab" data-state-filter="cancelled" aria-pressed="false">Annulées ({{ $cancelled->count() }})</button>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle mb-0 pos-table" id="posTable">
                <thead>
                    <tr>
                        <th>Vente</th>
                        <th>Client</th>
                        <th>Paiement</th>
                        <th class="text-end">Total TTC</th>
                        <th class="text-end">Reçu / rendu</th>
                        <th>Statut</th>
                        <th class="text-end"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($sales as $sale)
                    @php
                        $isCancelled = $sale->status === 'cancelled';
                        $lines = collect($sale->lines ?: []);
                        $number = $sale->reference ?: 'N° ' . $sale->id;
                    @endphp
                    <tr data-state="{{ $isCancelled ? 'cancelled' : 'completed' }}" data-search="{{ $lines->pluck('item_name')->implode(' ') }}">
                        <td class="text-nowrap">
                            <a href="{{ route('admin.commercial.pos.show', $sale) }}" class="d-block fw-semibold text-reset text-decoration-none">{{ $number }}</a>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ $sale->created_at->format('d/m/Y à H:i') }}</span>
                        </td>
                        <td>
                            <span class="d-block fw-semibold">{{ $sale->client_name ?: 'Client comptoir' }}</span>
                            <span class="d-block dg-muted text-truncate" style="font-size:12.5px;max-width:190px">{{ $lines->count() }} article(s){{ $lines->isNotEmpty() ? ' · ' . $lines->pluck('item_name')->take(2)->implode(', ') : '' }}</span>
                        </td>
                        <td class="text-nowrap">
                            <span class="d-flex align-items-center gap-2 fw-semibold"><i class="bi {{ $sale->payment_method === 'cash' ? 'bi-cash-stack text-success' : 'bi-bank text-info' }}"></i>{{ $sale->payment_method === 'cash' ? 'Espèces' : 'Banque' }}</span>
                            <span class="d-block dg-muted text-truncate" style="font-size:12.5px;max-width:150px">{{ $sale->payment_method === 'cash' ? $sale->cashAccount?->name : $sale->bankAccount?->name }}</span>
                        </td>
                        <td class="text-end">
                            <span class="d-block dg-cell-num {{ $isCancelled ? 'text-decoration-line-through dg-muted' : '' }}">{{ money((float) $sale->total) }}</span>
                            @if((float) $sale->tax_amount > 0)<span class="d-block dg-muted text-nowrap" style="font-size:12.5px">dont TVA {{ money((float) $sale->tax_amount) }}</span>@endif
                        </td>
                        <td class="text-end text-nowrap">
                            <span class="d-block dg-cell-num">{{ money((float) $sale->paid_amount) }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">rendu : {{ money((float) $sale->change_amount) }}</span>
                        </td>
                        <td>
                            @if($isCancelled)
                                <span class="dg-badge dg-badge--danger"><i class="bi bi-x-circle"></i>Annulée</span>
                            @else
                                <span class="dg-badge dg-badge--success"><i class="bi bi-check-lg"></i>Terminée</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light action-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions pour la vente {{ $number }}"></button>
                                <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                                    <li><a class="dropdown-item" href="{{ route('admin.commercial.pos.show', $sale) }}"><i class="bi bi-eye"></i>Voir la vente</a></li>
                                    @unless($isCancelled)
                                        <li><a class="dropdown-item" href="{{ route('admin.commercial.pos.edit', $sale) }}"><i class="bi bi-pencil"></i>Modifier</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#cancelSaleModal"
                                                data-action="{{ route('admin.commercial.pos.cancel', $sale) }}"
                                                data-label="{{ $number }} · {{ $sale->client_name ?: 'Client comptoir' }}"
                                                data-amount="{{ money((float) $sale->total) }}"
                                                data-where="{{ $sale->refundSourceLabel() }}"><i class="bi bi-x-circle"></i>Annuler la vente</button>
                                        </li>
                                    @else
                                        <li><hr class="dropdown-divider"></li>
                                        <li><form method="POST" action="{{ route('admin.commercial.pos.destroy', $sale) }}" onsubmit="return confirm('Supprimer définitivement cette vente annulée ?')">@csrf @method('DELETE')<button class="dropdown-item text-danger"><i class="bi bi-trash"></i>Supprimer</button></form></li>
                                    @endunless
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-5">
                            <div class="dg-chart-empty" style="min-height:220px">
                                <span class="dg-tile dg-tone-blue"><i class="bi bi-shop"></i></span>
                                <div><strong>Aucune vente sur cette période</strong>Choisissez une autre période, ou enregistrez une vente au comptoir.</div>
                                <a href="{{ route('admin.commercial.pos.create') }}" class="dg-btn dg-btn--primary dg-btn--sm"><i class="bi bi-plus-lg"></i>Nouvelle vente</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Hors du tableau : une ligne fusionnée dans le tbody empêcherait DataTables de s'initialiser. --}}
        <div class="dg-chart-empty mt-4 d-none" id="posNoResult" style="min-height:160px">
            <span class="dg-tile dg-tone-blue"><i class="bi bi-search"></i></span>
            <div><strong>Aucune vente ne correspond à votre recherche</strong>Modifiez la recherche ou le statut.</div>
        </div>
    </div>

    @include('admin.partials.pos-cancel-modal')
</div>

<style>
    .pos-period { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 14px 18px; border: 1px solid var(--dg-border); border-radius: var(--dg-radius-lg); background: var(--dg-surface); font-family: var(--dg-font); }
    .pos-period .dg-chip.is-active { background: var(--dg-navy); color: #fff; }
    .pos-table { min-width: 860px; }
    .dg-scope .pos-table > thead > tr > th,
    .dg-scope .pos-table > tbody > tr > td { padding-left: 8px !important; padding-right: 8px !important; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Recherche et statut, sur les ventes de la période déjà chargées.
    const rows = Array.from(document.querySelectorAll('#posTable tbody tr[data-state]'));
    const search = document.getElementById('posSearch');
    const tabs = Array.from(document.querySelectorAll('[data-state-filter]'));
    const noResult = document.getElementById('posNoResult');
    let state = '';
    const filterSales = function () {
        const term = search.value.trim().toLowerCase();
        let visible = 0;
        rows.forEach(function (row) {
            const text = (row.textContent + ' ' + (row.dataset.search || '')).toLowerCase();
            const show = (!term || text.includes(term)) && (!state || row.dataset.state === state);
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
            filterSales();
        });
    });
    search.addEventListener('input', filterSales);

});
</script>
@endsection
