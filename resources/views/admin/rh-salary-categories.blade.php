@extends('admin.layout')

@section('content')
<div class="card border-0 mb-5">
    <div class="card-body p-5 d-flex justify-content-between align-items-center">
        <div><div class="text-uppercase text-muted fs-8 fw-bold">RH &amp; Paie</div><h2 class="fs-2 fw-bold mb-0">Catégories salariales</h2><p class="text-muted mb-0">Définissez les niveaux et montants de rémunération.</p></div>
        <a href="{{ route('admin.rh') }}" class="btn btn-light">Retour à RH &amp; Paie</a>
    </div>
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="row g-5">
    <div class="col-lg-4">
        <div class="card border-0"><div class="card-header"><h3 class="card-title">{{ isset($editingCategory) ? 'Modifier la catégorie' : 'Nouvelle catégorie' }}</h3></div><div class="card-body">
            <form method="POST" action="{{ isset($editingCategory) ? route('admin.rh.salaryCategories.update', $editingCategory) : route('admin.rh.salaryCategories.store') }}">
                @csrf
                @if(isset($editingCategory)) @method('PUT') @endif
                <div class="mb-4"><label class="form-label">Nom *</label><input name="name" class="form-control" placeholder="Ex : 1A" value="{{ old('name', $editingCategory->name ?? '') }}" required></div>
                <div class="mb-4"><label class="form-label">Montant mensuel</label><input name="amount" type="number" min="0" step="0.01" class="form-control" placeholder="75000" value="{{ old('amount', $editingCategory->amount ?? '') }}"></div>
                <div class="mb-4"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3">{{ old('description', $editingCategory->description ?? '') }}</textarea></div>
                @if(isset($editingCategory))
                    <label class="form-check mb-4"><input type="checkbox" name="is_active" value="1" class="form-check-input" {{ $editingCategory->is_active ? 'checked' : '' }}> Active</label>
                    <div class="d-flex gap-2"><button class="btn btn-primary">Enregistrer</button><a href="{{ route('admin.rh.salaryCategories') }}" class="btn btn-light">Annuler</a></div>
                @else
                    <button class="btn btn-primary">Ajouter</button>
                @endif
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0"><div class="card-header"><h3 class="card-title">Catégories enregistrées</h3></div><div class="card-body p-0"><div class="table-responsive">
            <table class="table align-middle mb-0"><thead><tr><th>Nom</th><th>Montant</th><th>Statut</th><th class="text-end">Actions</th></tr></thead><tbody>
            @forelse($categories as $category)
                <tr><td class="fw-semibold">{{ $category->name }}<div class="text-muted fs-7">{{ $category->description }}</div></td><td>{{ $category->amount !== null ? number_format($category->amount, 0, ',', ' ') . ' XOF' : '-' }}</td><td><span class="badge {{ $category->is_active ? 'badge-light-success' : 'badge-light-secondary' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span></td><td class="text-end"><div class="dropdown"><button class="btn btn-sm btn-light-primary" data-bs-toggle="dropdown">•••</button><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="{{ route('admin.rh.salaryCategories.edit', $category) }}">Modifier</a><div class="dropdown-divider"></div><form method="POST" action="{{ route('admin.rh.salaryCategories.destroy', $category) }}" onsubmit="return confirm('Supprimer cette catégorie ?')">@csrf @method('DELETE')<button class="dropdown-item text-danger">Supprimer</button></form></div></div></td></tr>
            @empty<tr><td colspan="4" class="text-center text-muted py-8">Aucune catégorie salariale.</td></tr>@endforelse
            </tbody></table>
        </div></div></div>
    </div>
</div>
@endsection
