@extends('admin.layout')
@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 p-4 d-flex justify-content-between align-items-center">
        <div><h2 class="h4 fw-bold mb-1">Succursales</h2><p class="text-muted mb-0">Gérez les établissements et suivez leur statut.</p></div>
        @if($canManageAll)<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSuccursale">+ Nouvelle succursale</button>@endif
    </div>
    @if(session('success'))<div class="alert alert-success mx-4">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger mx-4">{{ $errors->first() }}</div>@endif
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th class="ps-4">Nom</th><th>Code</th><th>Ville</th><th>Téléphone</th><th>Statut</th><th class="text-end pe-4">Actions</th></tr></thead><tbody>
    @forelse($succursales as $succursale)
        <tr><td class="ps-4 fw-bold">{{ $succursale->name }}</td><td>{{ $succursale->code }}</td><td>{{ $succursale->city ?: '—' }}</td><td>{{ $succursale->phone ?: '—' }}</td><td><span class="badge {{ $succursale->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $succursale->is_active ? 'Active' : 'Inactive' }}</span><small class="d-block text-muted">{{ $succursale->users->count() }} compte(s) admin</small></td><td class="text-end pe-4">@if($canManageAll)<button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#edit-{{ $succursale->id }}">Modifier</button><form class="d-inline" method="POST" action="{{ route('admin.succursales.toggle', $succursale) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary">{{ $succursale->is_active ? 'Désactiver' : 'Activer' }}</button></form><form class="d-inline" method="POST" action="{{ route('admin.succursales.destroy', $succursale) }}" onsubmit="return confirm('Supprimer cette succursale ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Supprimer</button></form>@else<span class="text-muted">Consultation</span>@endif</td></tr>
    @empty
        <tr class="empty-table-row">
            <td class="text-center text-muted py-5">Aucune succursale enregistrée.</td>
            <td></td><td></td><td></td><td></td><td></td>
        </tr>
    @endforelse
    </tbody></table></div>
</div>
@if($canManageAll)
    @foreach($succursales as $succursale)
        <div class="modal fade" id="edit-{{ $succursale->id }}" tabindex="-1">
            <div class="modal-dialog"><div class="modal-content">
                <form method="POST" action="{{ route('admin.succursales.update', $succursale) }}">
                    @csrf @method('PUT')
                    <div class="modal-header"><h5 class="modal-title">Modifier la succursale</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">@include('admin.succursales-form', ['succursale' => $succursale])</div>
                    <div class="modal-footer"><button class="btn btn-primary">Enregistrer</button></div>
                </form>
            </div></div>
        </div>
    @endforeach
@endif
@if($canManageAll)<div class="modal fade" id="createSuccursale" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('admin.succursales.store') }}">@csrf<div class="modal-header"><h5 class="modal-title">Nouvelle succursale et son compte admin</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">@include('admin.succursales-form', ['includeAdmin' => true])</div><div class="modal-footer"><button class="btn btn-primary">Créer la succursale</button></div></form></div></div></div>@endif
@endsection
