@extends('admin.layout')

@section('content')
<div class="card border-0 mb-5">
    <div class="card-body">
        @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
        @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
        <div class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <div class="text-uppercase text-muted fs-8 fw-bold">RH &amp; Paie</div>
                <h2 class="fs-2 fw-bold mb-1">Bulletins de paie</h2>
                <p class="text-muted mb-0">Générez, consultez et envoyez les bulletins individuels.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.rh.payroll.create') }}" class="btn btn-primary">Générer un bulletin</a>
                <a href="{{ route('admin.rh') }}" class="btn btn-light">Retour à RH &amp; Paie</a>
            </div>
        </div>
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3"><label class="form-label">Mois</label><select name="month" class="form-select">
                @for($i=1;$i<=12;$i++) <option value="{{ $i }}" @selected($month === $i)>{{ \Carbon\Carbon::create()->month($i)->translatedFormat('F') }}</option> @endfor
            </select></div>
            <div class="col-md-2"><label class="form-label">Année</label><input type="number" name="year" value="{{ $year }}" min="2000" max="2100" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Employé</label><select name="employee_id" class="form-select"><option value="">Tous les employés actifs</option>
                @foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string)$employeeId === (string)$employee->id)>{{ $employee->full_name }} ({{ $employee->matricule }})</option>@endforeach
            </select></div>
            <div class="col-md-3"><button class="btn btn-primary w-100">Afficher</button></div>
        </form>
    </div>
</div>

<div class="card border-0">
    <div class="card-header border-0 d-flex justify-content-between align-items-center">
        <h3 class="fs-4 fw-bold mb-0">Paie de {{ \Carbon\Carbon::create()->month($month)->translatedFormat('F') }} {{ $year }}</h3>
        <form method="POST" action="{{ route('admin.rh.payroll.emailBulk') }}" id="bulkEmailForm">
            @csrf
            <button class="btn btn-success" id="bulkEmailButton" disabled>Envoyer la sélection par email (<span id="selectedPayrollCount">0</span>)</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th><input type="checkbox" id="selectAllPayrolls" aria-label="Sélectionner tous les bulletins"></th><th>Employé</th><th>Salaire de base</th><th>Brut</th><th>Retenues</th><th>Net à payer</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($payrolls as $payroll)
                <tr><td><input type="checkbox" class="payroll-checkbox" value="{{ $payroll->id }}" aria-label="Sélectionner le bulletin de {{ $payroll->employee->full_name }}"></td><td><strong>{{ $payroll->employee->full_name }}</strong><div class="text-muted small">{{ $payroll->employee->matricule }} · {{ $payroll->employee->email }}</div></td>
                    <td>{{ number_format($payroll->base_salary, 0, ',', ' ') }} XOF</td><td>{{ number_format($payroll->gross_salary, 0, ',', ' ') }} XOF</td>
                    <td>{{ number_format($payroll->gross_salary - $payroll->net_salary, 0, ',', ' ') }} XOF</td><td class="fw-bold">{{ number_format($payroll->net_salary, 0, ',', ' ') }} XOF</td>
                    <td>@if($payroll->sent_at)<span class="badge badge-light-success">Envoyé le {{ $payroll->sent_at->format('d/m/Y H:i') }}</span>@else<span class="badge badge-light-warning">Non envoyé</span>@endif</td>
                    <td class="text-end"><div class="dropdown"><button class="btn btn-sm btn-light-primary" data-bs-toggle="dropdown">•••</button><div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item" href="{{ route('admin.rh.payroll.show', $payroll) }}">Voir le bulletin</a>
                        <a class="dropdown-item" target="_blank" href="{{ route('admin.rh.payroll.pdf', $payroll) }}">Télécharger PDF</a>
                        <form method="POST" action="{{ route('admin.rh.payroll.email', $payroll) }}">@csrf<button class="dropdown-item">Envoyer par email</button></form>
                    </div></div></td>
                </tr>
            @empty <tr><td colspan="8" class="text-center text-muted py-8">Aucun bulletin généré pour cette période.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('bulkEmailForm');
    const all = document.getElementById('selectAllPayrolls');
    const boxes = [...document.querySelectorAll('.payroll-checkbox')];
    const button = document.getElementById('bulkEmailButton');
    const count = document.getElementById('selectedPayrollCount');
    const sync = () => {
        const selected = boxes.filter(box => box.checked);
        form.querySelectorAll('input[name="payroll_ids[]"]').forEach(input => input.remove());
        selected.forEach(box => {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'payroll_ids[]'; input.value = box.value;
            form.appendChild(input);
        });
        count.textContent = selected.length;
        button.disabled = selected.length === 0;
        all.checked = boxes.length > 0 && selected.length === boxes.length;
    };
    all?.addEventListener('change', () => { boxes.forEach(box => { box.checked = all.checked; }); sync(); });
    boxes.forEach(box => box.addEventListener('change', sync));
})();
</script>
@endpush
