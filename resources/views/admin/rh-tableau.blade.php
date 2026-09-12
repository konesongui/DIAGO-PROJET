@extends('admin.layout')

@section('content')
@php
    $money = fn ($value) => money((float) $value);
    $from = \Illuminate\Support\Carbon::parse($periodFrom);
    $to = \Illuminate\Support\Carbon::parse($periodTo);
    $stats = [
        ['Effectif à ce jour', $headcount, 'bi-people', 'purple', $onLeaveToday . ' en congé aujourd’hui'],
        ['Masse salariale', $money($salaryMass), 'bi-cash-coin', 'green', 'salaires de base des employés en poste'],
        ['Entrées sur la période', $hired->count(), 'bi-person-plus', 'blue', $left->count() . ' départ(s) sur la même période'],
        ['Bulletins de la période', $payrollCount, 'bi-receipt', 'orange', $payrollTotal > 0 ? $money($payrollTotal) . ' de net versé' : 'aucun net versé'],
    ];
    $hasPayroll = $months->contains(fn ($month) => $month['net'] > 0 || $month['charges'] > 0);
    $maxDepartment = max(1, (int) $departments->max('count'));
    $emptyBlock = fn (string $tone, string $icon, string $title, string $text) => '<div class="dg-chart-empty" style="min-height:180px"><span class="dg-tile dg-tone-' . $tone . '"><i class="bi ' . $icon . '"></i></span><div><strong>' . e($title) . '</strong>' . e($text) . '</div></div>';
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.rh')" back-label="RH & Paie">
        <x-slot:actions>
            <form method="GET" action="{{ route('admin.rh.tableau') }}" class="dg-period" aria-label="Période d’analyse">
                <input type="date" name="date_debut" value="{{ $periodFrom }}" class="dg-input" aria-label="Date de début">
                <span class="dg-period__sep">au</span>
                <input type="date" name="date_fin" value="{{ $periodTo }}" class="dg-input" aria-label="Date de fin">
                <button type="submit" class="dg-btn dg-btn--outline"><i class="bi bi-funnel"></i>Filtrer</button>
                <a href="{{ route('admin.rh.tableau') }}" class="dg-btn dg-btn--outline" title="Revenir à l’année en cours" aria-label="Revenir à l’année en cours"><i class="bi bi-arrow-counterclockwise"></i></a>
            </form>
        </x-slot:actions>
    </x-dg.page-header>

    @if($periodError)<div class="alert alert-warning">{{ $periodError }}</div>@endif

    <div class="dg-kpi-grid">
        @foreach($stats as [$label, $value, $icon, $color, $hint])
            <x-dg.kpi :label="$label" :value="$value" :icon="$icon" :color="$color" :hint="$hint" />
        @endforeach
    </div>

    <div class="dg-grid-2 mb-5">
        <x-dg.card title="Masse salariale versée" icon="bi-bar-chart-line" color="green" :meta="'Montants en ' . currency_symbol()">
            <div class="dg-chart">
                @if($hasPayroll)
                    <canvas id="payrollChart" role="img" aria-label="Net versé et charges patronales par mois"></canvas>
                @else
                    {!! $emptyBlock('green', 'bi-bar-chart-line', 'Aucun bulletin sur la période', 'Le net versé et les charges patronales s’afficheront ici, mois par mois.') !!}
                @endif
            </div>
        </x-dg.card>

        <x-dg.card title="À traiter" icon="bi-inbox" color="orange">
            <ul class="dg-list">
                <li class="dg-list-row">
                    <span class="dg-tile dg-tile--sm dg-tone-green"><i class="bi bi-calendar-range"></i></span>
                    <div class="dg-list-row__body">
                        <div class="dg-list-row__title">Congés à valider</div>
                        <div class="dg-list-row__sub">{{ $periodLeaveDays }} jour(s) validés sur la période</div>
                    </div>
                    <a href="{{ route('admin.rh.leaves') }}" class="dg-list-row__value text-reset text-decoration-none">{{ $pendingLeaves->count() }}</a>
                </li>
                <li class="dg-list-row">
                    <span class="dg-tile dg-tile--sm dg-tone-purple"><i class="bi bi-pencil-square"></i></span>
                    <div class="dg-list-row__body">
                        <div class="dg-list-row__title">Permissions à traiter</div>
                        <div class="dg-list-row__sub">{{ $periodPermissions }} demande(s) sur la période</div>
                    </div>
                    <a href="{{ route('admin.rh.permissions') }}" class="dg-list-row__value text-reset text-decoration-none">{{ $pendingPermissions->count() }}</a>
                </li>
                <li class="dg-list-row">
                    <span class="dg-tile dg-tile--sm dg-tone-blue"><i class="bi bi-check2-circle"></i></span>
                    <div class="dg-list-row__body">
                        <div class="dg-list-row__title">Présents aujourd’hui</div>
                        <div class="dg-list-row__sub">{{ $stillWorking }} encore au travail</div>
                    </div>
                    <a href="{{ route('admin.rh.attendance.today') }}" class="dg-list-row__value text-reset text-decoration-none">{{ $presentToday }}</a>
                </li>
                <li class="dg-list-row">
                    <span class="dg-tile dg-tile--sm dg-tone-orange"><i class="bi bi-receipt"></i></span>
                    <div class="dg-list-row__body">
                        <div class="dg-list-row__title">Charges patronales</div>
                        <div class="dg-list-row__sub">sur les bulletins de la période</div>
                    </div>
                    <a href="{{ route('admin.rh.payroll') }}" class="dg-list-row__value text-reset text-decoration-none">{{ $money($payrollCharges) }}</a>
                </li>
            </ul>

            @if($pendingLeaves->isNotEmpty())
                <h3 class="rh-subtitle">Congés en attente</h3>
                <ul class="dg-list">
                    @foreach($pendingLeaves->take(3) as $leave)
                        <li class="dg-list-row">
                            <div class="dg-list-row__body">
                                <div class="dg-list-row__title">{{ $leave->employee?->full_name ?? 'Employé supprimé' }}</div>
                                <div class="dg-list-row__sub">{{ $leave->leaveType?->name }} · {{ $leave->start_date->format('d/m/Y') }} au {{ $leave->end_date->format('d/m/Y') }}</div>
                            </div>
                            <span class="dg-list-row__value">{{ $leave->days }} j</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-dg.card>
    </div>

    <div class="dg-grid-halves mb-5">
        <x-dg.card title="Effectif par service" icon="bi-diagram-3" color="blue" :meta="$headcount . ' employé(s) en poste'">
            @if($departments->isNotEmpty())
                <ul class="rh-ranking">
                    @foreach($departments as $department)
                        <li>
                            <div class="d-flex justify-content-between gap-3">
                                <span class="min-w-0"><span class="d-block fw-semibold text-truncate">{{ $department['name'] }}</span><span class="dg-muted" style="font-size:12.5px">{{ $money($department['salary']) }} de salaires de base</span></span>
                                <span class="fw-semibold text-nowrap">{{ $department['count'] }}</span>
                            </div>
                            <span class="rh-bar mt-2"><span style="width:{{ $department['count'] / $maxDepartment * 100 }}%"></span></span>
                        </li>
                    @endforeach
                </ul>
            @else
                {!! $emptyBlock('blue', 'bi-diagram-3', 'Aucun employé en poste', 'Les services et leurs effectifs s’afficheront ici.') !!}
            @endif
        </x-dg.card>

        <x-dg.card title="Répartition de l’effectif" icon="bi-pie-chart" color="cyan">
            <h3 class="rh-subtitle mt-0">Par type de contrat</h3>
            <table class="dg-mini-table mb-4">
                <tbody>
                    @forelse($contracts as $contract => $count)
                        <tr><td>{{ $contract }}</td><td>{{ $count }}</td></tr>
                    @empty
                        <tr><td class="dg-muted">Aucun contrat renseigné</td><td>—</td></tr>
                    @endforelse
                </tbody>
            </table>
            <h3 class="rh-subtitle">Par genre</h3>
            <table class="dg-mini-table">
                <tbody>
                    @forelse($genders as $gender => $count)
                        <tr><td>{{ $gender }}</td><td>{{ $count }}</td></tr>
                    @empty
                        <tr><td class="dg-muted">Aucun genre renseigné</td><td>—</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-dg.card>
    </div>

    <div class="dg-grid-halves">
        <div class="dg-card dg-card--table">
            <div class="dg-card__header flex-wrap">
                <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-orange"><i class="bi bi-receipt"></i></span>Derniers bulletins</h2>
                <a href="{{ route('admin.rh.payroll') }}" class="dg-btn dg-btn--outline dg-btn--sm">Bulletins de paie</a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 rh-table no-export">
                    <thead><tr><th>Employé</th><th>Période</th><th class="text-end">Net à payer</th></tr></thead>
                    <tbody>
                    @forelse($payrolls as $payroll)
                        <tr>
                            <td>
                                <span class="d-block fw-semibold">{{ $payroll->employee?->full_name ?? 'Employé supprimé' }}</span>
                                <span class="d-block dg-muted" style="font-size:12.5px">{{ $payroll->employee?->department ?: '—' }}</span>
                            </td>
                            <td class="text-nowrap">{{ ucfirst(\Illuminate\Support\Carbon::create($payroll->year, $payroll->month, 1)->translatedFormat('F Y')) }}</td>
                            <td class="text-end dg-cell-num">{{ $money($payroll->net_salary) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="p-5">
                                <div class="dg-chart-empty" style="min-height:170px">
                                    <span class="dg-tile dg-tone-orange"><i class="bi bi-receipt"></i></span>
                                    <div><strong>Aucun bulletin sur la période</strong>Générez les bulletins du mois : ils alimenteront la masse salariale.</div>
                                    <a href="{{ route('admin.rh.payroll.create') }}" class="dg-btn dg-btn--primary dg-btn--sm"><i class="bi bi-plus-lg"></i>Générer un bulletin</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="dg-card dg-card--table">
            <div class="dg-card__header flex-wrap">
                <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-green"><i class="bi bi-person-plus"></i></span>Mouvements du personnel</h2>
                <span class="dg-card__meta">{{ $from->format('d/m/Y') }} au {{ $to->format('d/m/Y') }}</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 rh-table no-export">
                    <thead><tr><th>Employé</th><th>Mouvement</th><th class="text-end">Date</th></tr></thead>
                    <tbody>
                    @forelse($hired->map(fn ($employee) => ['employee' => $employee, 'type' => 'in', 'date' => $employee->registered_at ?? $employee->hire_date])
                        ->concat($left->map(fn ($employee) => ['employee' => $employee, 'type' => 'out', 'date' => $employee->contract_end_date]))
                        ->sortByDesc('date') as $movement)
                        <tr>
                            <td>
                                <span class="d-block fw-semibold">{{ $movement['employee']->full_name }}</span>
                                <span class="d-block dg-muted" style="font-size:12.5px">{{ collect([$movement['employee']->position, $movement['employee']->department])->filter()->implode(' · ') ?: '—' }}</span>
                            </td>
                            <td>
                                <span class="dg-badge dg-badge--{{ $movement['type'] === 'in' ? 'success' : 'neutral' }}">
                                    <i class="bi {{ $movement['type'] === 'in' ? 'bi-person-plus' : 'bi-person-dash' }}"></i>{{ $movement['type'] === 'in' ? 'Entrée' : 'Contrat clôturé' }}
                                </span>
                            </td>
                            <td class="text-end text-nowrap">{{ $movement['date']?->format('d/m/Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="p-5">
                                <div class="dg-chart-empty" style="min-height:170px">
                                    <span class="dg-tile dg-tone-green"><i class="bi bi-people"></i></span>
                                    <div><strong>Aucun mouvement sur la période</strong>Les arrivées et les fins de contrat apparaîtront ici.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    .rh-table { min-width: 420px; }
    .dg-scope .rh-table > thead > tr > th, .dg-scope .rh-table > tbody > tr > td { padding-left: 10px !important; padding-right: 10px !important; }
    /* Les intitulés de la carte « À traiter » peuvent tenir sur deux lignes. */
    .dg-scope .dg-list-row__title { white-space: normal; overflow: visible; }
    .rh-subtitle { margin: 18px 0 10px; font-size: 14px; font-weight: 600; color: var(--dg-text); }
    .rh-subtitle.mt-0 { margin-top: 0; }
    .rh-ranking { margin: 0; padding: 0; list-style: none; }
    .rh-ranking li { padding: 10px 0; border-bottom: 1px solid var(--dg-border); font-size: 14px; }
    .rh-ranking li:first-child { padding-top: 0; }
    .rh-ranking li:last-child { border-bottom: 0; padding-bottom: 0; }
    .rh-bar { display: block; height: 8px; border-radius: 99px; background: var(--dg-neutral-bg, #eceff6); }
    .rh-bar span { display: block; height: 100%; border-radius: inherit; background: #2563eb; }
    @media (max-width: 991px) { .dg-scope .dg-grid-2 { grid-template-columns: minmax(0, 1fr); } }
</style>
@if($hasPayroll)
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
@endif
<script>
document.addEventListener('DOMContentLoaded', function () {
    @if($hasPayroll)
    const months = @json($months);
    const compact = new Intl.NumberFormat('fr-FR', { notation: 'compact', maximumFractionDigits: 1 });
    Chart.defaults.font.family = "'Poppins', system-ui, sans-serif";
    Chart.defaults.color = '#8a93a6';
    new Chart(document.getElementById('payrollChart'), {
        type: 'bar',
        data: {
            labels: months.map(month => month.label),
            datasets: [
                { label: 'Net versé', data: months.map(month => month.net), backgroundColor: '#059669', borderRadius: 4, maxBarThickness: 30, stack: 'paie' },
                { label: 'Charges patronales', data: months.map(month => month.charges), backgroundColor: '#d97706', borderRadius: 4, maxBarThickness: 30, stack: 'paie' }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, boxHeight: 12 } },
                tooltip: { callbacks: { label: context => context.dataset.label + ' : ' + window.formatMoney(context.raw) } }
            },
            scales: {
                x: { stacked: true, grid: { display: false }, border: { display: false } },
                y: { stacked: true, border: { display: false }, grid: { color: 'rgba(138, 147, 166, .16)' }, ticks: { callback: value => compact.format(value) } }
            }
        }
    });
    @endif
});
</script>
@endsection
