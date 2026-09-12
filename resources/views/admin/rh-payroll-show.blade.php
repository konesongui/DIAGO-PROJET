@extends('admin.layout')

@section('content')
@php
    $employee = $payroll->employee;
    $period = \Illuminate\Support\Carbon::create($payroll->year, $payroll->month, 1);
    $money = fn ($value) => money((float) $value);
    $paymentLabels = ['cash' => 'Espèces', 'bank' => 'Virement bancaire', 'transfer' => 'Virement'];
    $earnings = collect([
        ['Salaire catégoriel', $payroll->base_salary],
        ['Sursalaire', $payroll->sursalary],
        ['Prime d’ancienneté', $payroll->seniority_bonus],
        ['Prime de transport', $payroll->transport_allowance],
        ['Prime de responsabilité', $payroll->responsibility_bonus],
        ['Bonus', $payroll->bonus],
        ['Prime de rendement', $payroll->performance_bonus],
        ['Prime de risque', $payroll->risk_bonus],
        ['Prime d’assiduité', $payroll->attendance_bonus],
        ['Gratification', $payroll->gratification],
        ['Congé payé', $payroll->leave_pay],
        ['Indemnités', $payroll->indemnities],
    ])->filter(fn ($line) => (float) $line[1] > 0);
    $deductions = collect([
        ['Impôt sur les traitements et salaires', $payroll->income_tax],
        ['CNPS — retraite (6,30 %)', $payroll->cnps_employee],
        ['Couverture maladie universelle', $payroll->cmu],
        ['Autres retenues', $payroll->deductions],
    ])->filter(fn ($line) => (float) $line[1] > 0);
    $charges = collect([
        ['CNPS — retraite (7,70 %)', $payroll->cnps_employer],
        ['CNPS — accident du travail', $payroll->work_accident],
        ['CNPS — prestations familiales', $payroll->family_benefits],
        ['FDFP — apprentissage', $payroll->fdfp_apprenticeship],
        ['FDFP — formation professionnelle', $payroll->fdfp_training],
        ['CMU part employeur', $payroll->cmu],
    ])->filter(fn ($line) => (float) $line[1] > 0);
    $stats = [
        ['Salaire brut', $money($payroll->gross_salary), 'bi-cash-stack', 'blue', 'gains du mois'],
        ['Retenues salariales', $money($payroll->total_employee_deductions), 'bi-dash-circle', 'orange', 'impôts et cotisations'],
        ['Net à payer', $money($payroll->net_salary), 'bi-wallet2', 'green', $paymentLabels[$payroll->payment_mode] ?? 'mode non renseigné'],
        ['Charges patronales', $money($payroll->total_employer_deductions), 'bi-building', 'purple', 'coût employeur en plus du brut'],
    ];
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.rh.payroll', ['month' => $payroll->month, 'year' => $payroll->year])" back-label="Bulletins de paie">
        <x-slot:actions>
            <a href="{{ route('admin.rh.payroll.pdf', $payroll) }}" class="dg-btn dg-btn--outline"><i class="bi bi-file-earmark-pdf"></i>Télécharger le PDF</a>
            @if($isAdmin)
                <form method="POST" action="{{ route('admin.rh.payroll.email', $payroll) }}"
                    onsubmit="return confirm('Envoyer ce bulletin à {{ addslashes($employee?->email ?: 'l’employé') }} ?')">
                    @csrf
                    <button class="dg-btn dg-btn--primary" @disabled(! filter_var($employee?->email, FILTER_VALIDATE_EMAIL))><i class="bi bi-envelope"></i>Envoyer par e-mail</button>
                </form>
            @endif
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="dg-kpi-grid">
        @foreach($stats as [$label, $value, $icon, $color, $hint])
            <x-dg.kpi :label="$label" :value="$value" :icon="$icon" :color="$color" :hint="$hint" />
        @endforeach
    </div>

    <div class="dg-grid-2 mb-5">
        <x-dg.card title="Détail du bulletin" icon="bi-receipt" color="blue" :meta="ucfirst($period->translatedFormat('F Y'))">
            <div class="table-responsive">
                <table class="table align-middle mb-0 payslip-table no-export no-column-sort">
                    <tbody>
                        <tr class="payslip-section"><td colspan="2">Rémunération</td></tr>
                        @foreach($earnings as [$label, $value])
                            <tr><td>{{ $label }}</td><td class="text-end dg-cell-num">{{ $money($value) }}</td></tr>
                        @endforeach
                        <tr class="payslip-total"><td>Salaire brut</td><td class="text-end dg-cell-num">{{ $money($payroll->gross_salary) }}</td></tr>

                        <tr class="payslip-section"><td colspan="2">Retenues salariales</td></tr>
                        @foreach($deductions as [$label, $value])
                            <tr><td>{{ $label }}</td><td class="text-end dg-cell-num dg-amount-negative">− {{ $money($value) }}</td></tr>
                        @endforeach
                        <tr class="payslip-total"><td>Total des retenues</td><td class="text-end dg-cell-num dg-amount-negative">− {{ $money($payroll->total_employee_deductions) }}</td></tr>

                        <tr class="payslip-net"><td>Net à payer</td><td class="text-end dg-cell-num">{{ $money($payroll->net_salary) }}</td></tr>
                    </tbody>
                </table>
            </div>
            <p class="dg-muted mt-3 mb-0" style="font-size:12.5px"><i class="bi bi-info-circle me-1"></i>Brut fiscal : {{ $money($payroll->fiscal_gross) }} · brut social : {{ $money($payroll->social_gross) }}. Ces bases servent au calcul de l’impôt et des cotisations.</p>
        </x-dg.card>

        <div>
            <x-dg.card title="Employé" icon="bi-person-badge" color="purple" class="mb-4">
                <dl class="payslip-info">
                    <dt>Nom</dt><dd>{{ $employee?->full_name ?? 'Employé supprimé' }}</dd>
                    <dt>Matricule</dt><dd>{{ $employee?->matricule ?: '—' }}</dd>
                    <dt>Fonction</dt><dd>{{ $employee?->position ?: '—' }}</dd>
                    <dt>Service</dt><dd>{{ $employee?->department ?: '—' }}</dd>
                    <dt>Catégorie</dt><dd>{{ $employee?->salary_category ?: '—' }}</dd>
                    <dt>N° CNPS</dt><dd>{{ $employee?->cnps_number ?: '—' }}</dd>
                    <dt>Parts IGR</dt><dd>{{ rtrim(rtrim(number_format((float) $payroll->part_igr, 1, ',', ' '), '0'), ',') }} · {{ (int) $payroll->children_count }} enfant(s)</dd>
                    <dt>Envoi</dt>
                    <dd>
                        @if($payroll->sent_at)
                            <span class="dg-badge dg-badge--success"><i class="bi bi-check-circle"></i>Envoyé le {{ $payroll->sent_at->format('d/m/Y') }}</span>
                        @else
                            <span class="dg-badge dg-badge--neutral"><i class="bi bi-envelope"></i>Pas encore envoyé</span>
                        @endif
                    </dd>
                </dl>
            </x-dg.card>

            <x-dg.card title="Charges patronales" icon="bi-building" color="orange" :meta="$money($payroll->total_employer_deductions)">
                <table class="dg-mini-table">
                    <tbody>
                        @foreach($charges as [$label, $value])
                            <tr><td>{{ $label }}</td><td>{{ $money($value) }}</td></tr>
                        @endforeach
                        <tr class="payslip-mini-total"><td>Coût total employeur</td><td>{{ $money((float) $payroll->gross_salary + (float) $payroll->total_employer_deductions) }}</td></tr>
                    </tbody>
                </table>
            </x-dg.card>
        </div>
    </div>
</div>

<style>
    .payslip-table { min-width: 380px; }
    .dg-scope .payslip-table > tbody > tr > td { padding-left: 10px !important; padding-right: 10px !important; }
    .dg-scope .payslip-table tr.payslip-section td { background: var(--dg-table-head); font-weight: 600; color: var(--dg-navy); }
    .dg-scope .payslip-table tr.payslip-total td { border-top: 1px solid var(--dg-border-strong); font-weight: 700; }
    .dg-scope .payslip-table tr.payslip-net td { border-top: 2px solid var(--dg-navy); font-weight: 700; font-size: 16px; color: var(--dg-navy); }
    .payslip-info { display: grid; grid-template-columns: max-content 1fr; gap: 6px 18px; margin: 0; font-size: 14px; }
    .payslip-info dt { font-weight: 500; color: var(--dg-muted); }
    .payslip-info dd { margin: 0; }
    .payslip-mini-total td { border-top: 2px solid var(--dg-border-strong) !important; font-weight: 700; color: var(--dg-navy); }
</style>
@endsection
