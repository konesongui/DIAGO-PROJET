@extends('admin.layout')

@section('content')
@php
    $money = fn ($value) => money((float) $value);
    // Chaque détail liste exactement les éléments qui composent le montant affiché.
    $cashMovements = $detailLists['cash_movements'];
    $bankTransactions = $detailLists['bank_transactions'];
    $details = [
        $detailLists['cash_accounts']->concat($detailLists['bank_accounts']),
        $cashMovements->where('movement_type', 'entry')->concat($bankTransactions->where('transaction_type', 'credit')),
        $cashMovements->where('movement_type', 'exit')->concat($bankTransactions->where('transaction_type', 'debit')),
        $cashMovements->filter(fn ($item) => $item->movement_date?->isSameMonth(now()))
            ->concat($bankTransactions->filter(fn ($item) => $item->transaction_date?->isSameMonth(now()))),
    ];
    $cards = [
        ['Liquidité totale', $liquidity, 'bi-wallet2', 'Soldes des caisses et des banques', 'blue'],
        ['Entrées caisse et banque', $cashIn + $bankIn, 'bi-box-arrow-in-down', 'Encaissements de la période', 'green'],
        ['Sorties caisse et banque', $cashOut + $bankOut, 'bi-box-arrow-up', 'Décaissements de la période', 'red'],
        ['Flux du mois', $monthlyTotal, 'bi-arrow-left-right', 'Mouvements du mois en cours', 'purple'],
    ];
    $indicators = [
        ['Comptes caisse', $detailLists['cash_accounts']->count(), 'bi-cash-stack', 'orange'],
        ['Comptes bancaires', $detailLists['bank_accounts']->count(), 'bi-bank', 'blue'],
        ['Factures fournisseurs', $detailLists['supplier_invoices']->count(), 'bi-file-earmark-text', 'pink'],
        ['Immobilisations', $detailLists['fixed_assets']->count(), 'bi-building', 'purple'],
    ];
@endphp

<div class="dg-font">
    <x-dg.page-header title="Tableau comptable" subtitle="Pilotage des liquidités, des flux et des engagements financiers." :back="route('admin.comptabilite')" back-label="Comptabilité">
        <x-slot:actions>
            <form method="GET" action="{{ route('admin.comptabilite.tableau') }}" class="dg-period" aria-label="Période d’analyse">
                <input type="date" name="date_debut" value="{{ $periodFrom }}" class="dg-input" aria-label="Date de début">
                <span class="dg-period__sep">au</span>
                <input type="date" name="date_fin" value="{{ $periodTo }}" class="dg-input" aria-label="Date de fin">
                <button type="submit" class="dg-btn dg-btn--outline"><i class="bi bi-funnel"></i>Filtrer</button>
                <a href="{{ route('admin.comptabilite.tableau') }}" class="dg-btn dg-btn--outline" title="Réinitialiser la période" aria-label="Réinitialiser la période"><i class="bi bi-arrow-counterclockwise"></i></a>
            </form>
        </x-slot:actions>
    </x-dg.page-header>

    <div class="dg-kpi-grid">
        @foreach($cards as [$label, $value, $icon, $hint, $color])
            <x-dg.kpi :label="$label" :value="$money($value)" :icon="$icon" :hint="$hint" :color="$color">
                <button type="button" class="dg-link-btn d-flex mt-2" data-bs-toggle="modal" data-bs-target="#accountingDetail{{ $loop->index }}">
                    <i class="bi bi-eye"></i>Voir le détail
                </button>
            </x-dg.kpi>
        @endforeach
    </div>

    <div class="dg-grid-2">
        <x-dg.card title="Évolution des flux (6 derniers mois)" icon="bi-bar-chart-line" color="blue" :meta="'Montants en ' . currency_symbol()">
            @if($months->sum(fn ($month) => $month['entries'] + $month['sorties']) > 0)
                <div class="dg-chart"><canvas id="accountingFlowsChart" role="img" aria-label="Évolution des entrées et sorties sur six mois"></canvas></div>
            @else
                <div class="dg-chart">
                    <div class="dg-chart-empty">
                        <span class="dg-tile dg-tone-blue"><i class="bi bi-bar-chart-line"></i></span>
                        <div><strong>Aucun flux sur les 6 derniers mois</strong>Les entrées et sorties de caisse et de banque s’afficheront ici.</div>
                    </div>
                </div>
            @endif
        </x-dg.card>
        <x-dg.card title="Indicateurs" icon="bi-speedometer2" color="purple">
            <table class="dg-mini-table">
                <tbody>
                    @foreach($indicators as [$label, $count, $icon, $color])
                        <tr>
                            <td><span class="d-inline-flex align-items-center gap-3"><span class="dg-tile dg-tile--sm dg-tone-{{ $color }}"><i class="bi {{ $icon }}"></i></span>{{ $label }}</span></td>
                            <td>{{ $count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-dg.card>
    </div>
</div>

@foreach($cards as [$label, $value, $icon, $hint, $color])
    <div class="modal fade dg-modal dg-tone-{{ $color }}" id="accountingDetail{{ $loop->index }}" tabindex="-1" aria-labelledby="accountingDetailTitle{{ $loop->index }}" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="accountingDetailTitle{{ $loop->index }}"><i class="bi {{ $icon }}" aria-hidden="true"></i>{{ $label }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="dg-table-wrap">
                        <table class="dg-table">
                            <thead><tr><th>Opération</th><th class="dg-cell-actions">Montant</th></tr></thead>
                            <tbody>
                                @forelse($details[$loop->index] as $item)
                                    <tr>
                                        <td>
                                            @php
                                                $itemTitle = $item->name ?? $item->label ?? $item->description ?? $item->reference ?? 'Opération';
                                                $itemAccount = $item->bank_name ?? $item->cashAccount?->name ?? $item->bankAccount?->name;
                                            @endphp
                                            <span class="dg-cell-strong">{{ $itemTitle }}</span>
                                            <span class="dg-cell-sub">{{ collect([strcasecmp((string) $itemAccount, (string) $itemTitle) === 0 ? null : $itemAccount, ($item->movement_date ?? $item->transaction_date)?->format('d/m/Y')])->filter()->implode(' · ') }}</span>
                                        </td>
                                        <td class="dg-cell-num dg-cell-actions">{{ $money($item->amount ?? $item->balance ?? $item->current_balance ?? $item->acquisition_value ?? 0) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="dg-empty">Aucun enregistrement sur la période.</td></tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr><td>Total</td><td class="dg-cell-num dg-cell-actions">{{ $money($value) }}</td></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="dg-btn dg-btn--outline" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
@endforeach

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
    const flowsCanvas = document.getElementById('accountingFlowsChart');
    if (!flowsCanvas) return;
    const months = @json($months);
    const compact = new Intl.NumberFormat('fr-FR', { notation: 'compact', maximumFractionDigits: 1 });
    Chart.defaults.font.family = "'Poppins', system-ui, sans-serif";
    Chart.defaults.color = '#8a93a6';
    new Chart(flowsCanvas, {
        type: 'bar',
        data: {
            labels: months.map(month => month.label.charAt(0).toUpperCase() + month.label.slice(1)),
            datasets: [
                { label: 'Entrées', data: months.map(month => month.entries), backgroundColor: '#10b981', borderRadius: 6, maxBarThickness: 28 },
                { label: 'Sorties', data: months.map(month => month.sorties), backgroundColor: '#ef4444', borderRadius: 6, maxBarThickness: 28 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                tooltip: { callbacks: { label: context => context.dataset.label + ' : ' + window.formatMoney(context.raw) } }
            },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, grid: { color: 'rgba(138, 147, 166, .16)' }, border: { display: false }, ticks: { callback: value => compact.format(value) } }
            }
        }
    });
})();
</script>
@endsection
