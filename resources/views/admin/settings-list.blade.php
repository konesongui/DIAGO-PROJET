@extends('admin.layout')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 p-5 d-flex justify-content-between align-items-center">
        <div>
            <div class="text-uppercase text-muted fs-8 fw-bold">Paramètres RH</div>
            <h2 class="h4 fw-bold mb-1">{{ $heading }}</h2>
            <p class="text-muted mb-0">{{ $description }}</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createItemModal">Ajouter</button>
    </div>
    <div class="card-body p-5">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Nom</th><th>Description</th><th>État</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse($items as $item)
                    <tr>
                        <td class="fw-semibold">{{ $item->name }}</td>
                        <td>{{ $item->description ?: '-' }}</td>
                        <td><span class="badge {{ $item->is_active ? 'badge-light-success' : 'badge-light-secondary' }}">{{ $item->is_active ? 'Actif' : 'Inactif' }}</span></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-light-primary" data-bs-toggle="modal" data-bs-target="#editItem{{ $item->id }}">Modifier</button>
                            <form method="POST" action="{{ route($routePrefix . '.destroy', $item) }}" class="d-inline" onsubmit="return confirm('Supprimer cet élément ?');">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-light-danger">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                    <div class="modal fade" id="editItem{{ $item->id }}" tabindex="-1">
                        <div class="modal-dialog"><div class="modal-content">
                            <form method="POST" action="{{ route($routePrefix . '.update', $item) }}">
                                @csrf @method('PUT')
                                <div class="modal-header"><h5 class="modal-title">Modifier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                <div class="modal-body">
                                    <label class="form-label">Nom</label><input name="name" class="form-control mb-3" value="{{ $item->name }}" required>
                                    <label class="form-label">Description</label><textarea name="description" class="form-control mb-3" rows="3">{{ $item->description }}</textarea>
                                    <div class="form-check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="form-check-input" {{ $item->is_active ? 'checked' : '' }}><label class="form-check-label">Actif</label></div>
                                </div>
                                <div class="modal-footer"><button type="submit" class="btn btn-primary">Enregistrer</button></div>
                            </form>
                        </div></div>
                    </div>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-5">Aucun élément enregistré.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="createItemModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route($routePrefix . '.store') }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Ajouter</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="form-label">Nom</label><input name="name" class="form-control mb-3" required>
                <label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Enregistrer</button></div>
        </form>
    </div></div>
</div>
@endsection
