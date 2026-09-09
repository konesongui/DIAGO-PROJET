@extends('admin.layout')

@section('content')
<div class="card border-0"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-5"><h2 class="fs-2 fw-bold mb-0">Générer un bulletin</h2><a href="{{ route('admin.rh.payroll') }}" class="btn btn-light">Annuler et revenir aux bulletins</a></div>
    <form method="POST" action="{{ route('admin.rh.payroll.generate') }}" class="row g-4" id="payrollForm">
        @csrf
        <div class="col-md-6"><label class="form-label">Employé</label><select name="employee_id" id="employeeSelect" class="form-select" required><option value="">Sélectionner</option>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->full_name }} ({{ $employee->matricule }})</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Mois</label><select name="month" class="form-select">@for($i=1;$i<=12;$i++)<option value="{{ $i }}" @selected($month===$i)>{{ \Carbon\Carbon::create()->month($i)->translatedFormat('F') }}</option>@endfor</select></div>
        <div class="col-md-3"><label class="form-label">Année</label><input type="number" name="year" value="{{ $year }}" class="form-control" required></div>
        <div id="employeeSummary" class="col-12 d-none"><div class="alert alert-light-primary mb-0"><strong id="summaryName"></strong><span class="ms-3">Contrat : <strong id="summaryContract">-</strong></span><span class="ms-3">Catégorie : <strong id="summaryCategory">-</strong></span><span class="ms-3">Date de départ : <strong id="summaryEndDate">-</strong></span></div></div>
        @foreach(['monthly_salary'=>'Salaire de base','sursalary'=>'Sursalaire','seniority_bonus'=>'Prime d’ancienneté','transport_allowance'=>'Prime de transport','overtime_hours'=>'Forfait d’heure supplémentaire','responsibility_bonus'=>'Prime de responsabilité','bonus'=>'Bonus','performance_bonus'=>'Prime de rendement','risk_bonus'=>'Prime de risque','attendance_bonus'=>'Prime d’assiduité','gratification'=>'Prime Gratification','leave_pay'=>'Congé','indemnities'=>'Les indemnités','income_tax'=>'Imp. sur Trait. et Sal. (IS)','cmu'=>'CMU','other_deductions'=>'Autres retenues'] as $name => $label)
            <div class="col-md-3"><label class="form-label">{{ $label }}</label><input type="number" step="0.01" min="0" name="{{ $name }}" value="0" class="form-control payroll-input"></div>
        @endforeach
        <div class="col-md-2"><label class="form-label">Part IGR</label><input type="number" step="0.5" min="1" max="5" name="part_igr" value="1" class="form-control payroll-input"></div>
        <div class="col-md-2"><label class="form-label">Nombre d’enfants</label><input type="number" min="0" name="children_count" value="0" class="form-control payroll-input"></div>
        <div class="col-md-3"><label class="form-label">Mode de paie</label><select name="payment_mode" id="paymentMode" class="form-select"><option value="cash">Espèces</option><option value="bank">Banque</option><option value="transfer" selected>Virement</option></select></div>
        <div class="col-md-4 payment-account-field d-none" id="cashAccountField"><label class="form-label">Caisse de paiement</label><select name="cash_account_id" class="form-select"><option value="">Sélectionner une caisse</option>@foreach($cashAccounts as $account)<option value="{{ $account->id }}">{{ $account->name }} ({{ number_format($account->balance, 0, ',', ' ') }} XOF)</option>@endforeach</select></div>
        <div class="col-md-4 payment-account-field" id="bankAccountField"><label class="form-label">Banque de paiement</label><select name="bank_account_id" class="form-select"><option value="">Sélectionner une banque</option>@foreach($bankAccounts as $account)<option value="{{ $account->id }}">{{ $account->name }} - {{ $account->bank_name }} ({{ number_format($account->current_balance, 0, ',', ' ') }} XOF)</option>@endforeach</select></div>
        <div class="col-12"><div class="card bg-light border-0 mt-2"><div class="card-body"><h4 class="fw-bold">Aperçu du bulletin avant génération</h4><div class="row g-3"><div class="col-md-3">Brut<strong class="d-block fs-4" id="previewGross">0 XOF</strong></div><div class="col-md-3">Brut fiscal<strong class="d-block fs-4" id="previewFiscal">0 XOF</strong></div><div class="col-md-3">Retenues<strong class="d-block fs-4" id="previewDeductions">0 XOF</strong></div><div class="col-md-3">Net à payer<strong class="d-block fs-4 text-success" id="previewNet">0 XOF</strong></div></div></div></div></div>
        <div class="col-12"><button class="btn btn-primary">Générer le bulletin</button></div>
    </form>
</div></div>
@endsection

@push('scripts')
@php
    $employeePayload = $employees->map(function ($employee) {
        return [
            'id' => $employee->id,
            'name' => $employee->full_name,
            'contract_type' => $employee->contract_type,
            'salary_category' => $employee->salary_category,
            'contract_end_date' => optional($employee->contract_end_date)->format('d/m/Y'),
            'monthly_salary' => $employee->monthly_salary,
            'children_count' => $employee->children_count,
            'sursalary' => $employee->sursalary,
            'seniority_bonus' => $employee->seniority_bonus,
            'transport_allowance' => $employee->transport_allowance,
            'overtime_hours' => $employee->overtime_hours,
            'responsibility_bonus' => $employee->responsibility_bonus,
            'bonus' => $employee->bonus,
            'performance_bonus' => $employee->performance_bonus,
            'risk_bonus' => $employee->risk_bonus,
            'attendance_bonus' => $employee->attendance_bonus,
            'gratification' => $employee->gratification,
            'leave_pay' => $employee->leave_pay,
            'income_tax' => $employee->income_tax,
            'cmu' => $employee->cmu,
            'other_deductions' => $employee->other_deductions,
            'indemnities' => $employee->indemnities,
            'part_igr' => $employee->part_igr,
            'payment_mode' => in_array($employee->payment_mode, ['cash', 'bank', 'transfer'], true) ? $employee->payment_mode : 'transfer',
        ];
    })->values();
@endphp
<script>
(() => {
    const employees = @json($employeePayload);
    const form = document.getElementById('payrollForm'), select = document.getElementById('employeeSelect');
    const paymentMode = document.getElementById('paymentMode'), cashField = document.getElementById('cashAccountField'), bankField = document.getElementById('bankAccountField');
    const money = value => `${Math.round(value).toLocaleString('fr-FR')} XOF`;
    const field = name => form.elements[name];
    const recalculate = () => {
        const n = name => parseFloat(field(name)?.value || 0);
        const allowances = ['sursalary','seniority_bonus','transport_allowance','responsibility_bonus','bonus','performance_bonus','risk_bonus','attendance_bonus','gratification','leave_pay','indemnities'].reduce((sum, key) => sum + n(key), 0);
        const gross = n('monthly_salary') + allowances, fiscal = n('monthly_salary') + n('sursalary') + Math.max(n('transport_allowance') - 30000, 0);
        const deductions = n('income_tax') + n('cmu') + n('other_deductions') + Math.min(Math.max(gross - n('transport_allowance'), 0), 3375000) * .063;
        document.getElementById('previewGross').textContent = money(gross); document.getElementById('previewFiscal').textContent = money(fiscal); document.getElementById('previewDeductions').textContent = money(deductions); document.getElementById('previewNet').textContent = money(Math.max(gross - deductions, 0));
    };
    const syncPaymentAccount = () => {
        const cash = paymentMode.value === 'cash';
        cashField.classList.toggle('d-none', !cash);
        bankField.classList.toggle('d-none', cash);
        cashField.querySelector('select').required = cash;
        bankField.querySelector('select').required = !cash;
    };
    paymentMode.addEventListener('change', syncPaymentAccount);
    syncPaymentAccount();
    select.addEventListener('change', () => {
        const employee = employees.find(item => String(item.id) === select.value);
        document.getElementById('employeeSummary').classList.toggle('d-none', !employee);
        if (!employee) return;
        document.getElementById('summaryName').textContent = employee.name; document.getElementById('summaryContract').textContent = employee.contract_type || '-'; document.getElementById('summaryCategory').textContent = employee.salary_category || '-'; document.getElementById('summaryEndDate').textContent = employee.contract_end_date || '-';
        Object.keys(employee).forEach(key => { if (field(key) && employee[key] !== null) field(key).value = employee[key]; });
        recalculate();
    });
    form.querySelectorAll('.payroll-input').forEach(input => input.addEventListener('input', recalculate));
})();
</script>
@endpush
