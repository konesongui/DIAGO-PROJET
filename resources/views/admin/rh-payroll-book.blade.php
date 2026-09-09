@extends('admin.layout')

@section('content')
<div class="card border-0 mb-5">
    <div class="card-body p-5">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-5">
            <div>
                <div class="text-uppercase text-muted fs-8 fw-bold">RH &amp; Paie</div>
                <h2 class="fs-2 fw-bold mb-1">Livre de paie</h2>
                <p class="text-muted mb-0">Synthèse des rémunérations, retenues et salaires nets.</p>
            </div>
            <a href="{{ route('admin.rh') }}" class="btn btn-light">Retour à RH &amp; Paie</a>
        </div>
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Période</label>
                <select name="period" id="payrollBookPeriod" class="form-select">
                    @foreach([
                        'today' => "Aujourd'hui", 'this_week' => 'Cette semaine', 'last_week' => 'La semaine dernière',
                        'current_month' => 'Ce mois-ci', 'last_month' => 'Le mois dernier', 'last_3_months' => '3 derniers mois',
                        'last_6_months' => '6 derniers mois', 'last_12_months' => '12 derniers mois',
                        'this_year' => "Cette année", 'last_year' => "L'année dernière", 'custom' => 'Période personnalisée'
                    ] as $key => $label)
                        <option value="{{ $key }}" @selected($period === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 custom-period-field">
                <label class="form-label fw-semibold">Du</label>
                <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control">
            </div>
            <div class="col-md-3 custom-period-field">
                <label class="form-label fw-semibold">Au</label>
                <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control">
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Afficher</button></div>
        </form>
    </div>
</div>

<div class="card border-0">
    <div class="card-header border-0 d-flex justify-content-between align-items-center">
        <div>
            <h3 class="fs-4 fw-bold mb-1">Livre de paie</h3>
            <span class="text-muted">Du {{ $from->format('d/m/Y') }} au {{ $to->format('d/m/Y') }}</span>
        </div>
        <span class="badge badge-light-primary">{{ $payrolls->count() }} bulletin(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Matricule</th><th>Nom et prénom</th><th>Total brut</th><th>Total social</th>
                    <th>Total fiscal</th><th>Salaire de base</th><th>CNPS</th><th>CMU</th>
                    <th>ITS</th><th>Total retenu</th><th>Salaire net</th>
                </tr>
            </thead>
            <tbody>
            @forelse($payrolls as $payroll)
                <tr>
                    <td><span class="badge badge-light-primary">{{ $payroll->employee->matricule }}</span></td>
                    <td class="fw-semibold">{{ $payroll->employee->full_name }}</td>
                    <td>{{ number_format($payroll->gross_salary, 0, ',', ' ') }}</td>
                    <td>{{ number_format($payroll->social_gross, 0, ',', ' ') }}</td>
                    <td>{{ number_format($payroll->fiscal_gross, 0, ',', ' ') }}</td>
                    <td>{{ number_format($payroll->base_salary, 0, ',', ' ') }}</td>
                    <td>{{ number_format($payroll->cnps_employee, 0, ',', ' ') }}</td>
                    <td>{{ number_format($payroll->cmu, 0, ',', ' ') }}</td>
                    <td>{{ number_format($payroll->income_tax, 0, ',', ' ') }}</td>
                    <td>{{ number_format($payroll->total_employee_deductions, 0, ',', ' ') }}</td>
                    <td class="fw-bold text-success">{{ number_format($payroll->net_salary, 0, ',', ' ') }}</td>
                </tr>
            @empty
                <tr><td colspan="11" class="text-center text-muted py-8">Aucun bulletin pour cette période.</td></tr>
            @endforelse
            </tbody>
            @if($payrolls->isNotEmpty())
                <tfoot class="fw-bold">
                    <tr>
                        <td colspan="2">Total général</td>
                        <td>{{ number_format($payrolls->sum('gross_salary'), 0, ',', ' ') }}</td>
                        <td>{{ number_format($payrolls->sum('social_gross'), 0, ',', ' ') }}</td>
                        <td>{{ number_format($payrolls->sum('fiscal_gross'), 0, ',', ' ') }}</td>
                        <td>{{ number_format($payrolls->sum('base_salary'), 0, ',', ' ') }}</td>
                        <td>{{ number_format($payrolls->sum('cnps_employee'), 0, ',', ' ') }}</td>
                        <td>{{ number_format($payrolls->sum('cmu'), 0, ',', ' ') }}</td>
                        <td>{{ number_format($payrolls->sum('income_tax'), 0, ',', ' ') }}</td>
                        <td>{{ number_format($payrolls->sum('total_employee_deductions'), 0, ',', ' ') }}</td>
                        <td class="text-success">{{ number_format($payrolls->sum('net_salary'), 0, ',', ' ') }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const period = document.getElementById('payrollBookPeriod');
    const fields = document.querySelectorAll('.custom-period-field');
    const toggle = () => fields.forEach(field => field.style.display = period.value === 'custom' ? '' : 'none');
    period.addEventListener('change', toggle);
    toggle();
})();
</script>
@endpush
