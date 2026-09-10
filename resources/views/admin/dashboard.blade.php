@extends('admin.layout')

@section('content')
@php
    $money = fn ($value) => money((float) $value);
    $percent = fn (?float $value) => $value === null ? null : ($value >= 0 ? '+' : '-') . number_format(abs($value), 1, ',', ' ') . ' %';
    $monthName = fn ($month) => ucfirst(\Carbon\Carbon::create(null, (int) $month, 1)->translatedFormat('M'));
    $yearMonthName = fn ($key) => ucfirst(\Carbon\Carbon::createFromFormat('Y-m-d', $key . '-01')->translatedFormat('M Y'));
    $currencyLabel = __('Amounts in :currency', ['currency' => currency_symbol()]);

    $user = auth()->user();
    $rubriques = array_merge(
        ['commercial' => true, 'comptabilite' => true, 'rh' => true],
        data_get($user->entreprise?->settings ?? [], 'enabled_rubriques', [])
    );
    $quickActions = array_values(array_filter([
        !empty($rubriques['commercial']) && $user->hasPermission('commercial', 'edit') ? ['admin.commercial.custom-invoice.create', 'bi-file-earmark-plus', __('New invoice')] : null,
        !empty($rubriques['commercial']) && $user->hasPermission('commercial', 'edit') ? ['admin.commercial.quotes.create', 'bi-file-earmark-text', __('New quote')] : null,
        !empty($rubriques['commercial']) && $user->hasPermission('commercial', 'edit') ? ['admin.commercial.pos.create', 'bi-cart-plus', __('Counter sale')] : null,
        !empty($rubriques['comptabilite']) && $user->hasPermission('accounting', 'edit') ? ['admin.comptabilite.caisses', 'bi-cash-stack', __('Cash movement')] : null,
        !empty($rubriques['rh']) && $user->hasPermission('hr', 'edit') ? ['admin.rh.employees.create', 'bi-person-plus', __('New employee')] : null,
    ]));

    // Palette des graphiques : charte Diago puis déclinaisons.
    $chartColors = ['#273772', '#fadf2f', '#dfe3ec', '#6272b0', '#c9ad12', '#a3acc2'];
@endphp

<div class="dg-font">
    <x-dg.page-header :title="__('Hello, welcome to Diago')" :subtitle="__('Here is a real-time overview of your financial activity.')">
        <x-slot:actions>
            <form method="GET" class="dg-period" aria-label="{{ __('Analysis period') }}">
                <input type="date" name="date_debut" value="{{ $filters['date_debut'] }}" class="dg-input" aria-label="{{ __('Start date') }}">
                <span class="dg-period__sep">{{ __('to') }}</span>
                <input type="date" name="date_fin" value="{{ $filters['date_fin'] }}" class="dg-input" aria-label="{{ __('End date') }}">
                <button type="submit" class="dg-btn dg-btn--outline"><i class="bi bi-funnel"></i>{{ __('Filter') }}</button>
            </form>
            @if($quickActions)
                <div class="dropdown">
                    <button type="button" class="dg-btn dg-btn--primary" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-plus-lg"></i>{{ __('Quick action') }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                        @foreach($quickActions as [$route, $icon, $label])
                            <li><a class="dropdown-item" href="{{ route($route) }}"><i class="bi {{ $icon }}"></i>{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-slot:actions>
    </x-dg.page-header>

    {{-- Indicateurs de tête (maquette, page 2) --}}
    <div class="dg-kpi-grid">
        <x-dg.kpi :label="__('Receipts')" :value="$money($overview['revenue'])" icon="bi-graph-up-arrow"
            :trend="$percent($overview['revenue_trend'])"
            :trend-direction="($overview['revenue_trend'] ?? 0) >= 0 ? 'up' : 'down'"
            :trend-tone="($overview['revenue_trend'] ?? 0) >= 0 ? 'success' : 'danger'"
            :hint="$overview['comparison']" />
        <x-dg.kpi :label="__('Expenses')" :value="$money($overview['expenses'])" icon="bi-receipt"
            :trend="$percent($overview['expenses_trend'])"
            :trend-direction="($overview['expenses_trend'] ?? 0) >= 0 ? 'up' : 'down'"
            :trend-tone="($overview['expenses_trend'] ?? 0) > 0 ? 'danger' : 'success'"
            :hint="$overview['comparison']" />
        <x-dg.kpi :label="__('Net balance')" :value="$money($overview['net'])" icon="bi-coin"
            :trend="$percent($overview['net_trend'])"
            :trend-direction="($overview['net_trend'] ?? 0) >= 0 ? 'up' : 'down'"
            :trend-tone="($overview['net_trend'] ?? 0) >= 0 ? 'success' : 'danger'"
            :hint="$overview['margin'] === null ? $overview['comparison'] : __('margin :value', ['value' => number_format($overview['margin'], 1, ',', ' ') . ' %'])" />
        <x-dg.kpi :label="__('Unpaid invoices')" :value="$money($overview['receivables'])" icon="bi-exclamation-triangle-fill" tone="danger"
            :trend="$percent($overview['receivables_trend'])"
            :trend-direction="($overview['receivables_trend'] ?? 0) >= 0 ? 'up' : 'down'"
            :trend-tone="($overview['receivables_trend'] ?? 0) > 0 ? 'danger' : 'success'"
            :hint="$overview['comparison']" />
    </div>

    <div class="dg-grid-2">
        <x-dg.card :title="__('Receipts trend') . ' (' . $revenueTrend['range'] . ')'" :meta="$currencyLabel">
            <div class="dg-chart"><canvas id="revenueTrendChart" aria-label="{{ __('Receipts trend') }}" role="img"></canvas></div>
        </x-dg.card>

        <x-dg.card :title="__('Expense breakdown')">
            @if($expenseBreakdown->isNotEmpty())
                <div class="dg-donut">
                    <div class="dg-chart"><canvas id="expenseBreakdownChart" aria-label="{{ __('Expense breakdown') }}" role="img"></canvas></div>
                    <ul class="dg-legend">
                        @foreach($expenseBreakdown as $index => $row)
                            <li title="{{ $money($row['amount']) }}">
                                <span class="dg-legend__swatch" style="background:{{ $chartColors[$index % count($chartColors)] }}"></span>
                                <span class="dg-legend__label">{{ $row['label'] }} <span class="text-nowrap">({{ number_format($row['percent'], 0, ',', ' ') }}&nbsp;%)</span></span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <div class="dg-empty-state">{{ __('No expenses recorded during this period.') }}</div>
            @endif
        </x-dg.card>
    </div>

    {{-- Trésorerie et comptabilité --}}
    <h2 class="dg-section-title dg-section-title--spaced">{{ __('Treasury') }}</h2>
    <div class="dg-kpi-grid">
        <x-dg.kpi :label="__('Current balance')" :value="$money($treasury['balance'])" icon="bi-bank" />
        <x-dg.kpi :label="__('Cash outflows')" :value="$money($treasury['cash_exits'])" icon="bi-cash" />
        <x-dg.kpi :label="__('Transactions')" :value="$treasury['transactions']" icon="bi-arrow-left-right" :hint="__('during the period')" />
    </div>
    <x-dg.card :title="__('Revenue vs expenses')" :meta="$currencyLabel">
        <div class="dg-chart"><canvas id="revenueExpenseChart" aria-label="{{ __('Revenue vs expenses') }}" role="img"></canvas></div>
    </x-dg.card>

    {{-- Commercial --}}
    <h2 class="dg-section-title dg-section-title--spaced">{{ __('Commercial') }}</h2>
    <div class="dg-kpi-grid">
        <x-dg.kpi label="Ventes facturées" :value="$money($commercial['sales'])" icon="bi-bag" />
        <x-dg.kpi label="Factures émises" :value="$commercial['invoices']" icon="bi-file-earmark-text" />
        <x-dg.kpi label="Achats fournisseurs" :value="$money($commercial['purchases'])" icon="bi-truck" />
        <x-dg.kpi label="Factures fournisseurs reçues" :value="$commercial['supplier_invoices']" icon="bi-inbox" />
    </div>

    {{-- Ressources humaines --}}
    <h2 class="dg-section-title dg-section-title--spaced">{{ __('Human resources') }}</h2>
    <div class="dg-kpi-grid">
        <x-dg.kpi label="Effectif total" :value="$hr['count']" icon="bi-people" />
        <x-dg.kpi label="Âge médian" :value="$hr['median_age'] . ' ans'" icon="bi-person" />
        <x-dg.kpi label="Ancienneté moyenne" :value="number_format($hr['seniority'], 1, ',', ' ') . ' ans'" icon="bi-hourglass-split" />
        <x-dg.kpi label="Salaire net moyen" :value="$money($hr['net_average'])" icon="bi-wallet2" />
    </div>

    @php
        $hrCharts = [
            ['ageChart', 'Pyramide des âges (effectifs par tranche)', $hr['age_bands']->keys()->values(), $hr['age_bands']->values(), 'bar', false],
            ['turnoverChart', 'Sorties mensuelles', $hr['turnover']->sortKeys()->keys()->map($monthName)->values(), $hr['turnover']->sortKeys()->values(), 'bar', false],
            ['salaryChart', 'Évolution du salaire net moyen', $hr['salary']->sortKeys()->keys()->map($yearMonthName)->values(), $hr['salary']->sortKeys()->values(), 'line', true],
            ['leaveChart', 'Congés, absences et arrêts par mois', $hr['leave']->sortKeys()->keys()->map($monthName)->values(), $hr['leave']->sortKeys()->values(), 'bar', false],
        ];
        $hrTables = [
            ['Contrats', $hr['contracts'], false],
            ['Nationalités', $hr['nationalities'], false],
            ['Catégories professionnelles', $hr['categories'], false],
            ['Salaire net moyen par service', $hr['departments'], true],
        ];
    @endphp
    <div class="dg-grid-halves mb-5">
        @foreach($hrCharts as [$id, $chartTitle])
            <x-dg.card :title="$chartTitle">
                <div class="dg-chart dg-chart--sm"><canvas id="{{ $id }}" aria-label="{{ $chartTitle }}" role="img"></canvas></div>
            </x-dg.card>
        @endforeach
    </div>
    <div class="dg-grid-4">
        @foreach($hrTables as [$tableTitle, $rows, $isMoney])
            <x-dg.card :title="$tableTitle">
                <table class="dg-mini-table">
                    <tbody>
                        @forelse($rows as $label => $value)
                            <tr><td>{{ $label ?: 'Non renseigné' }}</td><td>{{ $isMoney ? $money($value) : $value }}</td></tr>
                        @empty
                            <tr><td class="dg-muted">Aucune donnée</td><td></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-dg.card>
        @endforeach
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
    const navy = '#273772', yellow = '#fadf2f', muted = '#8a93a6', grid = 'rgba(138, 147, 166, .16)';
    const money = value => window.formatMoney(value);
    // Axes : montants compacts (25 M, 500 k), l'unité figure dans l'en-tête de la carte.
    const compact = new Intl.NumberFormat('fr-FR', { notation: 'compact', maximumFractionDigits: 1 });
    const axisMoney = value => compact.format(value);
    Chart.defaults.font.family = "'Poppins', system-ui, sans-serif";
    Chart.defaults.color = muted;

    // Évolution des recettes : aire marine, dernier point jaune (maquette).
    const trendValues = @json($revenueTrend['values']);
    const lastIndex = trendValues.length - 1;
    new Chart(document.getElementById('revenueTrendChart'), {
        type: 'line',
        data: {
            labels: @json($revenueTrend['labels']),
            datasets: [{
                label: @json(__('Receipts')),
                data: trendValues,
                borderColor: navy,
                borderWidth: 3,
                backgroundColor: 'rgba(39, 55, 114, .08)',
                fill: true,
                tension: .45,
                pointRadius: trendValues.map((_, index) => index === lastIndex ? 6 : 0),
                pointHoverRadius: 6,
                pointBackgroundColor: yellow,
                pointBorderColor: navy,
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: context => money(context.raw) } } },
            scales: {
                x: { grid: { display: false }, border: { display: false } },
                y: { display: false, beginAtZero: true }
            }
        }
    });

    const breakdownCanvas = document.getElementById('expenseBreakdownChart');
    if (breakdownCanvas) {
        const breakdown = @json($expenseBreakdown);
        const colors = @json($chartColors);
        new Chart(breakdownCanvas, {
            type: 'doughnut',
            data: {
                labels: breakdown.map(row => row.label),
                datasets: [{ data: breakdown.map(row => row.amount), backgroundColor: breakdown.map((_, index) => colors[index % colors.length]), borderWidth: 0 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: context => context.label + ' : ' + money(context.raw) } } }
            }
        });
    }

    new Chart(document.getElementById('revenueExpenseChart'), {
        type: 'line',
        data: {
            labels: @json($monthLabels),
            datasets: [
                { label: @json(__('Receipts')), data: @json($accounting['revenue_series']), borderColor: navy, backgroundColor: navy, borderWidth: 3, tension: .4, pointRadius: 3 },
                { label: @json(__('Expenses')), data: @json($accounting['expense_series']), borderColor: '#c9ad12', backgroundColor: '#c9ad12', borderWidth: 3, tension: .4, pointRadius: 3 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } }, tooltip: { callbacks: { label: context => context.dataset.label + ' : ' + money(context.raw) } } },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, grid: { color: grid }, border: { display: false }, ticks: { callback: axisMoney } }
            }
        }
    });

    @json($hrCharts).forEach(([id, , labels, values, type, isMoney]) => {
        new Chart(document.getElementById(id), {
            type,
            data: {
                labels,
                datasets: [{
                    data: values,
                    borderColor: navy,
                    backgroundColor: type === 'bar' ? navy : 'rgba(39, 55, 114, .08)',
                    borderRadius: type === 'bar' ? 6 : 0,
                    maxBarThickness: 36,
                    fill: type === 'line',
                    tension: .4,
                    borderWidth: type === 'line' ? 3 : 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: context => isMoney ? money(context.raw) : context.raw } } },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, grid: { color: grid }, border: { display: false }, ticks: { precision: 0, callback: value => isMoney ? axisMoney(value) : value } }
                }
            }
        });
    });
})();
</script>
@endsection
