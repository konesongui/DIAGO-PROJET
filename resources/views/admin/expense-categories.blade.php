@extends('admin.layout')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body p-6">
        <div class="d-flex justify-content-between align-items-center mb-6">
            <div>
                <div class="text-uppercase text-muted fs-8 fw-bold ls-1">Comptabilité</div>
                <h3 class="fs-2 fw-bold text-dark mb-1">{{ $title }}</h3>
                <p class="text-muted mb-0">{{ $subtitle }}</p>
            </div>
            <a href="{{ route('admin.comptabilite') }}" class="btn btn-light">Retour</a>
        </div>
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

        <form method="POST" action="{{ route('admin.comptabilite.expenseCategories.store') }}" class="row g-3 align-items-end border rounded-3 p-4 bg-light mb-6">
            @csrf
            <div class="col-md-4"><label class="form-label fw-bold">Nom</label><input name="name" class="form-control" required placeholder="Ex: Fournitures"></div>
            <div class="col-md-6"><label class="form-label fw-bold">Description</label><input name="description" class="form-control" placeholder="Description facultative"></div>
            <div class="col-md-2 d-grid"><button class="btn btn-primary">Ajouter</button></div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle table-row-dashed">
                <thead><tr class="text-muted text-uppercase fs-7"><th>Catégorie</th><th>Description</th><th>Mouvements</th><th>État</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                @forelse($categories as $category)
                    <tr>
                        <td class="fw-bold">{{ $category->name }}</td>
                        <td>{{ $category->description ?: '-' }}</td>
                        <td>{{ $category->cash_movements_count }}</td>
                        <td><span class="badge {{ $category->is_active ? 'badge-light-success' : 'badge-light-danger' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('admin.comptabilite.expenseCategories.update', $category) }}" class="d-inline-flex gap-2">
                                @csrf @method('PUT')
                                <input type="hidden" name="name" value="{{ $category->name }}">
                                <input type="hidden" name="description" value="{{ $category->description }}">
                                <input type="hidden" name="is_active" value="{{ $category->is_active ? 0 : 1 }}">
                                <button class="btn btn-sm btn-light">{{ $category->is_active ? 'Désactiver' : 'Activer' }}</button>
                            </form>
                            @if($category->cash_movements_count === 0)
                                <form method="POST" action="{{ route('admin.comptabilite.expenseCategories.destroy', $category) }}" class="d-inline" onsubmit="return confirm('Supprimer cette catégorie ?')">
                                    @csrf @method('DELETE') <button class="btn btn-sm btn-light-danger">Supprimer</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-8">Aucune catégorie créée.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
