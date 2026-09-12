@extends('admin.layout')

@section('content')
@php
    $money = fn ($value) => money((float) $value);
    $periods = [
        'today' => 'Aujourd’hui', 'this_week' => 'Cette semaine', 'last_week' => 'Semaine dernière',
        'current_month' => 'Mois en cours', 'last_month' => 'Mois dernier', 'last_3_months' => '3 derniers mois',
        'last_6_months' => '6 derniers mois', 'last_12_months' => '12 derniers mois',
        'this_year' => 'Année en cours', 'last_year' => 'Année dernière', 'custom' => 'Période choisie',
    ];
    $byEmployee = $payrolls->groupBy('employee_id')->map(fn ($items) => [
        'employee' => $items->first()->employee,
        'count' => $items->count(),
        'gross' => (float) $items->sum('gross_salary'),
        'deductions' => (float) $items->sum('total_employee_deductions'),
        'net' => (float) $items->sum('net_salary'),
        'charges' => (float) $items->sum('total_employer_deductions'),
    ])->sortByDesc('net')->values();
    $stats = [
        ['Bulletins', $payrolls->count(), 'bi-journal-text', 'indigo', $byEmployee->count() . ' employé(s) payé(s)'],
        ['Brut cumulé', $money($payrolls->sum('gross_salary')), 'bi-cash-stack', 'blue', 'sur la période'],
        ['Net versé', $money($payrolls->sum('net_salary')), 'bi-wallet2', 'green', 'montant payé aux employés'],
        ['Coût employeur', $money($payrolls->sum('gross_salary') + $payrolls->sum('total_employer_deductions')), 'bi-building', 'purple', 'brut et charges patronales'],
    ];
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.rh')" back-label="RH & Paie">
        <x-slot:actions>
            <a href="{{ route('admin.rh.payroll') }}" class="dg-btn dg-btn--outline"><i class="bi bi-receipt"></i>Bulletins de paie</a>
        </x-slot:actions>
    </x-dg.page-header>

    @if($periodError)<div class="alert alert-warning">{{ $periodError }}</div>@endif

    <div class="dg-card mb-5">
        <form method="GET" action="{{ route('admin.rh.payrollBook') }}" class="row g-3 align-items-end">
            <div class="col-sm-6 col-lg-4">
                <label class="form-label" for="bookPeriod">Période</label>
                <select id="bookPeriod" name="period" class="form-select">
                    @foreach($periods as $value => $label)
                        <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-3 col-lg-3">
                <label class="form-label" for="bookFrom">Du</label>
                <input id="bookFrom" type="date" name="from" value="{{ $from->toDateString() }}" class="form-control">
            </div>
            <div class="col-sm-3 col-lg-3">
                <label class="form-label" for="bookTo">Au</label>
                <input id="bookTo" type="date" name="to" value="{{ $to->toDateString() }}" class="form-control">
            </div>
            <div class="col-sm-12 col-lg-2">
                <button type="submit" class="dg-btn dg-btn--primary w-100"><i class="bi bi-funnel"></i>Afficher</button>
            </div>
        </form>
        <p class="dg-muted mt-3 mb-0" style="font-size:12.5px"><i class="bi bi-info-circle me-1"></i>Les dates ne servent que pour « Période choisie ». Un bulletin est retenu dès que son mois touche la période.</p>
    </div>

    <div class="dg-kpi-grid">
        @foreach($stats as [$label, $value, $icon, $color, $hint])
            <x-dg.kpi :label="$label" :value="$value" :icon="$icon" :color="$color" :hint="$hint" />
        @endforeach
    </div>

    <div class="dg-card dg-card--table mb-5">
        <div class="dg-card__header flex-wrap">
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-indigo"><i class="bi bi-people"></i></span>Total par employé</h2>
            <span class="dg-card__meta">{{ $from->format('d/m/Y') }} au {{ $to->format('d/m/Y') }}</span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0 book-table no-export">
                <thead>
                    <tr>
                        <th>Employé</th>
                        <th class="text-end">Bulletins</th>
                        <th class="text-end">Brut</th>
                        <th class="text-end">Retenues</th>
                        <th class="text-end">Net versé</th>
                        <th class="text-end">Charges patronales</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($byEmployee as $row)
                    <tr>
                        <td>
                            <span class="d-block fw-semibold">{{ $row['employee']?->full_name ?? 'Employé supprimé' }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ collect([$row['employee']?->matricule, $row['employee']?->department])->filter()->implode(' · ') ?: '—' }}</span>
                        </td>
                        <td class="text-end dg-cell-num">{{ $row['count'] }}</td>
                        <td class="text-end dg-cell-num">{{ $money($row['gross']) }}</td>
                        <td class="text-end dg-cell-num dg-amount-negative">− {{ $money($row['deductions']) }}</td>
                        <td class="text-end dg-cell-num fw-semibold">{{ $money($row['net']) }}</td>
                        <td class="text-end dg-cell-num">{{ $money($row['charges']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-5">
                            <div class="dg-chart-empty" style="min-height:200px">
                                <span class="dg-tile dg-tone-indigo"><i class="bi bi-journal-text"></i></span>
                                <div><strong>Aucun bulletin sur la période</strong>Choisissez une autre période, ou générez les bulletins du mois.</div>
                                <a href="{{ route('admin.rh.payroll.create') }}" class="dg-btn dg-btn--primary dg-btn--sm"><i class="bi bi-plus-lg"></i>Générer un bulletin</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
                @if($byEmployee->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <td class="text-end dg-cell-num">{{ $payrolls->count() }}</td>
                            <td class="text-end dg-cell-num">{{ $money($payrolls->sum('gross_salary')) }}</td>
                            <td class="text-end dg-cell-num">− {{ $money($payrolls->sum('total_employee_deductions')) }}</td>
                            <td class="text-end dg-cell-num">{{ $money($payrolls->sum('net_salary')) }}</td>
                            <td class="text-end dg-cell-num">{{ $money($payrolls->sum('total_employer_deductions')) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    @if($byMonth->isNotEmpty())
        <div class="dg-card dg-card--table">
            <div class="dg-card__header flex-wrap">
                <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-blue"><i class="bi bi-calendar3"></i></span>Mois par mois</h2>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 book-months-table no-export">
                    <thead>
                        <tr>
                            <th>Mois</th>
                            <th class="text-end">Bulletins</th>
                            <th class="text-end">Brut</th>
                            <th class="text-end">Net versé</th>
                            <th class="text-end">Charges patronales</th>
                            <th class="text-end"><span class="visually-hidden">Ouvrir</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($byMonth as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row['label'] }}</td>
                            <td class="text-end dg-cell-num">{{ $row['count'] }}</td>
                            <td class="text-end dg-cell-num">{{ $money($row['gross']) }}</td>
                            <td class="text-end dg-cell-num fw-semibold">{{ $money($row['net']) }}</td>
                            <td class="text-end dg-cell-num">{{ $money($row['charges']) }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.rh.payroll', ['month' => $row['month'], 'year' => $row['year']]) }}" class="dg-btn dg-btn--outline dg-btn--sm">Ouvrir</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

<style>
    .book-table { min-width: 820px; }
    .book-months-table { min-width: 700px; }
    .dg-scope .book-table > thead > tr > th, .dg-scope .book-table > tbody > tr > td, .dg-scope .book-table > tfoot > tr > td,
    .dg-scope .book-months-table > thead > tr > th, .dg-scope .book-months-table > tbody > tr > td { padding-left: 10px !important; padding-right: 10px !important; }
    .book-table tfoot td { border-top: 2px solid var(--dg-border-strong); font-weight: 700; color: var(--dg-navy); }
</style>
@endsection
