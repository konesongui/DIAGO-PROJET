@extends('admin.layout')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-5">
    <div><div class="text-uppercase text-muted fs-8 fw-bold">Commercial</div><h2 class="fs-2 fw-bold mb-1">Objectifs commerciaux</h2><p class="text-muted mb-0">Définissez les objectifs annuels et attribuez-les aux employés commerciaux.</p></div>
    <a href="{{ route('admin.commercial') }}" class="btn btn-light">Retour à l’interface principale</a>
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="row g-5">
    <div class="col-lg-4">
        <div class="card border-0"><div class="card-header border-0"><h3 class="card-title">Ajouter un objectif annuel</h3></div><div class="card-body">
            <form method="POST" action="{{ route('admin.commercial.objectives.store') }}">@csrf
                <label class="form-label">Montant (FCFA) <span class="text-danger">*</span></label><input name="amount" type="number" min="1" step="0.01" class="form-control mb-4" required>
                <label class="form-label">Date <span class="text-danger">*</span></label><input name="objective_date" type="date" class="form-control mb-5" value="{{ now()->format('Y-m-d') }}" required>
                <button class="btn btn-primary w-100">Enregistrer</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0"><div class="card-header border-0"><h3 class="card-title">Objectifs annuels</h3></div><div class="card-body">
            @forelse($objectives as $objective)
                <div class="border rounded p-4 mb-5">
                    <div class="d-flex justify-content-between align-items-center mb-4"><div><strong class="fs-2">{{ number_format($objective->amount, 0, ',', ' ') }} FCFA</strong><div class="text-muted">Date : {{ $objective->objective_date->format('d/m/Y') }}</div></div><div class="d-flex gap-2"><button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#assign{{ $objective->id }}">Attribuer</button><button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#editObjective{{ $objective->id }}">Modifier</button><form method="POST" action="{{ route('admin.commercial.objectives.destroy',$objective) }}" onsubmit="return confirm('Supprimer cet objectif ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-light-danger">Supprimer</button></form></div></div>
                    <h5>Attributions</h5><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Commercial</th><th>Montant</th><th>Période</th><th>Actions</th></tr></thead><tbody>
                    @forelse($objective->assignments as $assignment)<tr><td>{{ $assignment->employee->full_name }}</td><td>{{ number_format($assignment->amount, 0, ',', ' ') }} FCFA</td><td>{{ $assignment->starts_at->format('d/m/Y') }} - {{ $assignment->ends_at->format('d/m/Y') }}</td><td><button class="btn btn-sm btn-light-primary" data-bs-toggle="modal" data-bs-target="#editAssignment{{ $assignment->id }}">Modifier</button><form class="d-inline" method="POST" action="{{ route('admin.commercial.objectives.assignments.destroy',$assignment) }}" onsubmit="return confirm('Supprimer cette attribution ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-light-danger">Supprimer</button></form></td></tr>
                    <div class="modal fade" id="editAssignment{{ $assignment->id }}"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('admin.commercial.objectives.assignments.update',$assignment) }}">@csrf @method('PUT')<div class="modal-header"><h5>Modifier l’attribution</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div><div class="modal-body"><label class="form-label">Commercial</label><select name="employee_id" class="form-select mb-3" required>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected($employee->id === $assignment->employee_id)>{{ $employee->full_name }}</option>@endforeach</select><label class="form-label">Montant</label><input name="amount" type="number" min="1" step="0.01" value="{{ $assignment->amount }}" class="form-control mb-3" required><div class="row"><div class="col"><label class="form-label">Du</label><input name="starts_at" type="date" value="{{ $assignment->starts_at->format('Y-m-d') }}" class="form-control" required></div><div class="col"><label class="form-label">Au</label><input name="ends_at" type="date" value="{{ $assignment->ends_at->format('Y-m-d') }}" class="form-control" required></div></div></div><div class="modal-footer"><button class="btn btn-primary">Enregistrer</button></div></form></div></div></div>
                    @empty<tr><td colspan="4" class="text-muted">Aucune attribution.</td></tr>@endforelse</tbody></table></div>
                </div>
                <div class="modal fade" id="assign{{ $objective->id }}"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="POST" action="{{ route('admin.commercial.objectives.assignments.store',$objective) }}">@csrf<div class="modal-header"><h5>Attribuer l’objectif annuel</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div><div class="modal-body"><div class="alert alert-light-primary">Objectif annuel : <strong>{{ number_format($objective->amount, 0, ',', ' ') }} FCFA</strong><br>Montant déjà attribué : <strong id="allocated{{ $objective->id }}">0 FCFA</strong><br>Reste disponible : <strong id="remaining{{ $objective->id }}">{{ number_format($objective->amount - $objective->assignments->sum('amount'), 0, ',', ' ') }} FCFA</strong></div><div id="assignmentRows{{ $objective->id }}"><div class="row g-3 assignment-row mb-3"><div class="col-md-5"><label class="form-label">Commercial <span class="text-danger">*</span></label><select name="assignments[0][employee_id]" class="form-select" required><option value="">Sélectionner</option>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->full_name }} — {{ $employee->position }}</option>@endforeach</select></div><div class="col-md-3"><label class="form-label">Montant <span class="text-danger">*</span></label><input name="assignments[0][amount]" type="number" min="1" step="0.01" class="form-control assignment-amount" required></div><div class="col-md-2"><label class="form-label">Du</label><input name="assignments[0][starts_at]" type="date" class="form-control" value="{{ $objective->objective_date->startOfYear()->format('Y-m-d') }}" required></div><div class="col-md-2"><label class="form-label">Au</label><input name="assignments[0][ends_at]" type="date" class="form-control" value="{{ $objective->objective_date->endOfYear()->format('Y-m-d') }}" required></div><div class="col-12 text-end"><button type="button" class="btn btn-sm btn-light-danger remove-assignment-row">Supprimer la ligne</button></div></div></div>                <button type="button" class="btn btn-sm btn-light-primary add-assignment-row" data-target="assignmentRows{{ $objective->id }}" data-total="{{ $objective->amount }}" data-allocated="{{ $objective->assignments->sum('amount') }}">+ Ajouter un commercial</button></div><div class="modal-footer"><button class="btn btn-primary">Enregistrer les attributions</button></div></form></div></div></div>
                <div class="modal fade" id="editObjective{{ $objective->id }}"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('admin.commercial.objectives.update',$objective) }}">@csrf @method('PUT')<div class="modal-header"><h5>Modifier l’objectif</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div><div class="modal-body"><label class="form-label">Montant</label><input name="amount" type="number" min="1" step="0.01" value="{{ $objective->amount }}" class="form-control mb-3" required><label class="form-label">Date</label><input name="objective_date" type="date" value="{{ $objective->objective_date->format('Y-m-d') }}" class="form-control" required></div><div class="modal-footer"><button class="btn btn-primary">Enregistrer</button></div></form></div></div></div>
            @empty <div class="text-center text-muted py-5">Aucun objectif annuel enregistré.</div>@endforelse
        </div></div>
    </div>
</div>
@endsection
@push('scripts')
<script>
document.querySelectorAll('.add-assignment-row').forEach(button => {
    const container = document.getElementById(button.dataset.target);
    const total = Number(button.dataset.total);
    const alreadyAllocated = Number(button.dataset.allocated);
    const allocated = document.getElementById('allocated' + button.dataset.target.replace('assignmentRows', ''));
    const remaining = document.getElementById('remaining' + button.dataset.target.replace('assignmentRows', ''));
    const refresh = () => {
        const entered = [...container.querySelectorAll('.assignment-amount')].reduce((sum, field) => sum + (Number(field.value) || 0), 0);
        const allocatedTotal = alreadyAllocated + entered;
        allocated.textContent = allocatedTotal.toLocaleString('fr-FR') + ' FCFA';
        remaining.textContent = Math.max(0, total - allocatedTotal).toLocaleString('fr-FR') + ' FCFA';
        const selected = [...container.querySelectorAll('select[name*="[employee_id]"]')].map(select => select.value).filter(Boolean);
        container.querySelectorAll('select[name*="[employee_id]"]').forEach(select => {
            select.querySelectorAll('option').forEach(option => {
                option.disabled = option.value && option.value !== select.value && selected.includes(option.value);
            });
        });
    };
    button.addEventListener('click', () => {
        const index = container.querySelectorAll('.assignment-row').length;
        const row = container.querySelector('.assignment-row').cloneNode(true);
        row.querySelectorAll('input, select').forEach(field => {
            field.name = field.name.replace(/\[\d+\]/, '[' + index + ']');
            field.value = field.tagName === 'INPUT' && field.type === 'date' ? field.value : '';
        });
        container.appendChild(row);
        refresh();
    });
    container.addEventListener('input', refresh);
    container.addEventListener('click', event => {
        if (event.target.closest('.remove-assignment-row')) {
            event.target.closest('.assignment-row').remove();
            refresh();
        }
    });
    refresh();
});
</script>
@endpush
