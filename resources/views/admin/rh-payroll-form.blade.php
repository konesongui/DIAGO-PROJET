@extends('admin.layout')

@section('content')
@php
    $currency = currency_symbol();
    // Champs de saisie, regroupés par nature.
    $groups = [
        ['Rémunération', 'bi-cash-stack', 'blue', [
            'monthly_salary' => 'Salaire de base',
            'sursalary' => 'Sursalaire',
            'seniority_bonus' => 'Prime d’ancienneté',
            'transport_allowance' => 'Prime de transport',
            'overtime_hours' => 'Heures supplémentaires',
        ]],
        ['Primes et indemnités', 'bi-gift', 'purple', [
            'responsibility_bonus' => 'Prime de responsabilité',
            'bonus' => 'Bonus',
            'performance_bonus' => 'Prime de rendement',
            'risk_bonus' => 'Prime de risque',
            'attendance_bonus' => 'Prime d’assiduité',
            'gratification' => 'Gratification',
            'leave_pay' => 'Congé payé',
            'indemnities' => 'Indemnités',
        ]],
        ['Retenues', 'bi-dash-circle', 'orange', [
            'income_tax' => 'Impôt sur les traitements et salaires',
            'cmu' => 'CMU',
            'other_deductions' => 'Autres retenues',
        ]],
    ];
    $existing = $payrolls ?? collect();
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" subtitle="Les montants de la fiche de l’employé sont proposés : ajustez-les si le mois l’exige." :back="route('admin.rh.payroll')" back-label="Bulletins de paie" />

    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('admin.rh.payroll.generate') }}" id="payrollForm">
        @csrf
        <x-dg.card title="Employé et période" icon="bi-person-badge" color="navy" class="mb-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="employeeSelect">Employé <span class="text-danger">*</span></label>
                    <select id="employeeSelect" name="employee_id" class="form-select @error('employee_id') is-invalid @enderror" required>
                        <option value="">Choisir un employé</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((int) old('employee_id') === $employee->id)>{{ $employee->full_name }}{{ $employee->matricule ? ' — ' . $employee->matricule : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="payrollMonth">Mois <span class="text-danger">*</span></label>
                    <select id="payrollMonth" name="month" class="form-select @error('month') is-invalid @enderror" required>
                        @for($index = 1; $index <= 12; $index++)
                            <option value="{{ $index }}" @selected((int) old('month', $month) === $index)>{{ ucfirst(\Illuminate\Support\Carbon::create()->month($index)->translatedFormat('F')) }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="payrollYear">Année <span class="text-danger">*</span></label>
                    <input id="payrollYear" type="number" name="year" min="2000" max="2100" value="{{ old('year', $year) }}" class="form-control @error('year') is-invalid @enderror" required>
                </div>
                <div class="col-12 d-none" id="employeeSummary">
                    <div class="payroll-summary">
                        <div><span>Employé</span><strong id="summaryName">—</strong></div>
                        <div><span>Contrat</span><strong id="summaryContract">—</strong></div>
                        <div><span>Catégorie</span><strong id="summaryCategory">—</strong></div>
                        <div><span>Fin de contrat</span><strong id="summaryEndDate">—</strong></div>
                    </div>
                </div>
            </div>
        </x-dg.card>

        @foreach($groups as [$groupTitle, $groupIcon, $groupColor, $fields])
            <x-dg.card :title="$groupTitle" :icon="$groupIcon" :color="$groupColor" class="mb-4">
                <div class="row g-3">
                    @foreach($fields as $name => $label)
                        <div class="col-sm-6 col-lg-3">
                            <label class="form-label" for="field-{{ $name }}">{{ $label }}{{ $name === 'overtime_hours' ? ' (h)' : ' (' . $currency . ')' }}</label>
                            <input id="field-{{ $name }}" type="number" step="0.01" min="0" name="{{ $name }}" value="{{ old($name, 0) }}" class="form-control payroll-input @error($name) is-invalid @enderror">
                        </div>
                    @endforeach
                    @if($groupTitle === 'Retenues')
                        <div class="col-12">
                            <p class="form-text mb-0"><i class="bi bi-info-circle me-1"></i>Laissez l’impôt et la CMU à zéro pour qu’ils soient calculés automatiquement (barème ITS, parts IGR, 500 {{ $currency }} par personne à charge). La CNPS de 6,30 % est toujours calculée.</p>
                        </div>
                    @endif
                </div>
            </x-dg.card>
        @endforeach

        <x-dg.card title="Situation et paiement" icon="bi-wallet2" color="green" class="mb-4">
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="partIgr">Parts IGR</label>
                    <input id="partIgr" type="number" step="0.5" min="1" max="5" name="part_igr" value="{{ old('part_igr', 1) }}" class="form-control payroll-input @error('part_igr') is-invalid @enderror">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="childrenCount">Personnes à charge</label>
                    <input id="childrenCount" type="number" min="0" name="children_count" value="{{ old('children_count', 0) }}" class="form-control payroll-input @error('children_count') is-invalid @enderror">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="paymentMode">Mode de paiement <span class="text-danger">*</span></label>
                    <select id="paymentMode" name="payment_mode" class="form-select @error('payment_mode') is-invalid @enderror" required>
                        <option value="cash" @selected(old('payment_mode') === 'cash')>Espèces (caisse)</option>
                        <option value="bank" @selected(old('payment_mode') === 'bank')>Banque</option>
                        <option value="transfer" @selected(old('payment_mode', 'transfer') === 'transfer')>Virement</option>
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3 d-none" id="cashAccountField">
                    <label class="form-label" for="cashAccount">Caisse de paiement <span class="text-danger">*</span></label>
                    <select id="cashAccount" name="cash_account_id" class="form-select @error('cash_account_id') is-invalid @enderror">
                        <option value="">Choisir une caisse</option>
                        @foreach($cashAccounts as $account)
                            <option value="{{ $account->id }}" @selected((int) old('cash_account_id') === $account->id)>{{ $account->name }} — {{ money((float) $account->balance) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3" id="bankAccountField">
                    <label class="form-label" for="bankAccount">Compte bancaire <span class="text-danger">*</span></label>
                    <select id="bankAccount" name="bank_account_id" class="form-select @error('bank_account_id') is-invalid @enderror">
                        <option value="">Choisir un compte</option>
                        @foreach($bankAccounts as $account)
                            <option value="{{ $account->id }}" @selected((int) old('bank_account_id') === $account->id)>{{ $account->name }}{{ $account->bank_name ? ' — ' . $account->bank_name : '' }} — {{ money((float) $account->current_balance) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p class="form-text mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Le net à payer sort de la caisse ou du compte choisi dès la génération du bulletin.</p>
        </x-dg.card>

        <x-dg.card title="Estimation avant génération" icon="bi-calculator" color="teal" class="mb-4">
            <div class="payroll-preview">
                <div><span>Salaire brut</span><strong id="previewGross">—</strong></div>
                <div><span>Brut fiscal</span><strong id="previewFiscal">—</strong></div>
                <div><span>Retenues salariales</span><strong id="previewDeductions">—</strong></div>
                <div class="payroll-preview__net"><span>Net à payer</span><strong id="previewNet">—</strong></div>
            </div>
            <p class="form-text mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Estimation indicative : le bulletin définitif est calculé par l’application à l’enregistrement (barème ITS, plafond CNPS, charges patronales).</p>
        </x-dg.card>

        <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="dg-btn dg-btn--primary"><i class="bi bi-check-lg"></i>Générer le bulletin</button>
            <a href="{{ route('admin.rh.payroll') }}" class="dg-btn dg-btn--outline">Annuler</a>
        </div>
    </form>
</div>

@php
    $employeePayload = $employees->map(fn ($employee) => [
        'id' => $employee->id,
        'name' => $employee->full_name,
        'contract_type' => $employee->contract_type,
        'salary_category' => $employee->salary_category,
        'contract_end_date' => $employee->contract_end_date?->format('d/m/Y'),
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
    ])->values();
@endphp
<style>
    .payroll-summary, .payroll-preview { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
    .payroll-summary > div, .payroll-preview > div { padding: 12px 14px; border: 1px solid var(--dg-border); border-radius: var(--dg-radius); background: var(--dg-surface); }
    .payroll-summary span, .payroll-preview span { display: block; font-size: 12.5px; color: var(--dg-muted); }
    .payroll-summary strong, .payroll-preview strong { font-size: 16px; font-variant-numeric: tabular-nums; }
    .payroll-preview__net { background: rgba(5, 150, 105, .08) !important; border-color: rgba(5, 150, 105, .3) !important; }
    .payroll-preview__net strong { color: #059669; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const employees = @json($employeePayload);
    const form = document.getElementById('payrollForm');
    const select = document.getElementById('employeeSelect');
    const paymentMode = document.getElementById('paymentMode');
    const cashField = document.getElementById('cashAccountField');
    const bankField = document.getElementById('bankAccountField');
    const field = name => form.elements[name];
    const value = name => parseFloat(field(name)?.value || 0) || 0;

    // Estimation : mêmes règles que le serveur (barème ITS, parts IGR, CNPS 6,3 %
    // plafonnée). Le bulletin enregistré reste celui calculé par l'application.
    const incomeTax = function (fiscal) {
        const brackets = [[75000, 0], [240000, 0.16], [800000, 0.21], [2400000, 0.24], [8000000, 0.28], [Infinity, 0.32]];
        let remaining = fiscal, tax = 0, previous = 0;
        for (const [limit, rate] of brackets) {
            const taxable = Math.min(remaining, limit - previous);
            if (taxable > 0) tax += taxable * rate;
            remaining -= Math.max(taxable, 0);
            previous = limit;
            if (remaining <= 0) break;
        }
        return Math.max(tax, 0);
    };
    const igrReduction = function (parts) {
        const steps = [[5, 44000], [4.5, 38500], [4, 33000], [3.5, 27500], [3, 22000], [2.5, 16500], [2, 11000], [1.5, 5500]];
        for (const [threshold, reduction] of steps) {
            if (parts >= threshold) return reduction;
        }
        return 0;
    };
    const refresh = function () {
        const allowanceFields = ['sursalary', 'seniority_bonus', 'transport_allowance', 'responsibility_bonus', 'bonus',
            'performance_bonus', 'risk_bonus', 'attendance_bonus', 'gratification', 'leave_pay', 'indemnities'];
        const base = value('monthly_salary');
        const allowances = allowanceFields.reduce((sum, name) => sum + value(name), 0);
        const gross = base + allowances;
        const transport = value('transport_allowance');
        const taxableOver = amount => Math.max(amount - gross * 0.10, 0);
        const fiscal = base + value('sursalary') + Math.max(transport - 30000, 0)
            + taxableOver(value('seniority_bonus')) + taxableOver(value('performance_bonus'))
            + taxableOver(value('responsibility_bonus')) + taxableOver(value('risk_bonus')) + taxableOver(value('attendance_bonus'));
        const cnps = Math.min(Math.max(gross - transport, 0), 3375000) * 0.063;
        const tax = value('income_tax') || Math.max(incomeTax(fiscal) - igrReduction(value('part_igr')), 0);
        const cmu = value('cmu') || Math.max(value('children_count'), 1) * 500;
        const deductions = cnps + tax + cmu + value('other_deductions');
        document.getElementById('previewGross').textContent = window.formatMoney(gross);
        document.getElementById('previewFiscal').textContent = window.formatMoney(fiscal);
        document.getElementById('previewDeductions').textContent = window.formatMoney(deductions);
        document.getElementById('previewNet').textContent = window.formatMoney(Math.max(gross - deductions, 0));
    };

    const syncPaymentAccount = function () {
        const cash = paymentMode.value === 'cash';
        cashField.classList.toggle('d-none', !cash);
        bankField.classList.toggle('d-none', cash);
        cashField.querySelector('select').required = cash;
        bankField.querySelector('select').required = !cash;
    };
    paymentMode.addEventListener('change', syncPaymentAccount);
    syncPaymentAccount();

    select.addEventListener('change', function () {
        const employee = employees.find(item => String(item.id) === select.value);
        document.getElementById('employeeSummary').classList.toggle('d-none', !employee);
        if (!employee) return;
        document.getElementById('summaryName').textContent = employee.name;
        document.getElementById('summaryContract').textContent = employee.contract_type || '—';
        document.getElementById('summaryCategory').textContent = employee.salary_category || '—';
        document.getElementById('summaryEndDate').textContent = employee.contract_end_date || '—';
        Object.keys(employee).forEach(function (key) {
            if (field(key) && employee[key] !== null && employee[key] !== undefined) field(key).value = employee[key];
        });
        if (employee.payment_mode) {
            paymentMode.value = employee.payment_mode;
            syncPaymentAccount();
        }
        refresh();
    });
    form.querySelectorAll('.payroll-input').forEach(input => input.addEventListener('input', refresh));
    refresh();
});
</script>
@endsection
