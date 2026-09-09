@extends('admin.layout')

@section('content')
<style>
    .modal-dialog-scrollable.permissions-dialog {
        height: calc(100% - 2rem);
        max-height: calc(100vh - 2rem);
    }

    .permissions-dialog .modal-content {
        display: flex;
        flex-direction: column;
        height: 100%;
        max-height: 100%;
    }

    .permissions-dialog .modal-body {
        min-height: 0;
        flex: 1 1 auto;
        max-height: calc(100vh - 180px);
        overflow-y: auto;
        overscroll-behavior: contain;
        scrollbar-width: auto;
    }

    .permissions-dialog .modal-footer {
        position: sticky;
        bottom: 0;
        z-index: 2;
        flex-shrink: 0;
        border-top: 1px solid #e4e9f0;
    }

    .permissions-dialog .modal-header {
        flex-shrink: 0;
    }
</style>
<div class="card border-0 mb-6">
    <div class="card-header border-0 px-5 py-4">
        <div class="card-title fs-4 fw-bold text-dark">Liste des utilisateurs</div>
        <div class="card-toolbar">
            @if(auth()->user()->hasPermission('users', 'edit'))
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm px-4 py-3">
                <i class="ki-duotone ki-plus fs-2 me-2"></i>
                Nouvel utilisateur
            </a>
            @endif
        </div>
    </div>

    <div class="card-body p-0">
        @if(session('success'))
            <div class="mx-5 mt-5 alert alert-success d-flex align-items-center" role="alert">
                <i class="ki-duotone ki-check-circle fs-2 me-3"></i>
                <div>{{ session('success') }}</div>
            </div>
        @endif
        @if(session('error'))
            <div class="mx-5 mt-5 alert alert-danger d-flex align-items-center" role="alert">
                <i class="ki-duotone ki-information-5 fs-2 me-3"></i>
                <div>{{ session('error') }}</div>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Entreprise</th>
                        <th>Statut</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-40px symbol-circle bg-light-primary text-primary fw-bold me-3">
                                        {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark">{{ $user->name }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $user->email }}</td>
                            <td><span class="badge badge-light-primary">{{ $user->role?->label ?? '—' }}</span></td>
                            <td>{{ $user->entreprise?->name ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $user->is_active ? 'badge-light-success' : 'badge-light-danger' }}">
                                    {{ $user->is_active ? 'Actif' : 'Désactivé' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    @if(auth()->user()->hasPermission('users', 'edit'))
                                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-light-primary">Éditer</a>
                                    @endif
                                    <div class="dropdown">
                                        <button type="button" class="btn btn-sm btn-light action-menu-button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions"></button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            @if(auth()->user()->hasPermission('users', 'edit'))
                                                <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#permissionsModal-{{ $user->id }}">Droits d'accès</button></li>
                                            @endif
                                            @if(auth()->id() !== $user->id && auth()->user()->hasPermission('users', 'edit'))
                                                <li>
                                                    <form method="POST" action="{{ route('admin.users.toggleStatus', $user) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="dropdown-item {{ $user->is_active ? 'text-danger' : 'text-success' }}">
                                                            {{ $user->is_active ? 'Désactiver le compte' : 'Activer le compte' }}
                                                        </button>
                                                    </form>
                                                </li>
                                            @endif
                                            @if(auth()->id() !== $user->id && auth()->user()->hasPermission('users', 'delete'))
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <button type="submit" form="delete-user-{{ $user->id }}" class="dropdown-item text-danger">Supprimer</button>
                                                </li>
                                            @endif
                                        </ul>
                                    </div>
                                    @if(auth()->id() !== $user->id)
                                        <form id="delete-user-{{ $user->id }}" action="{{ route('admin.users.destroy', $user) }}" method="POST" onsubmit="return confirm('Supprimer cet utilisateur ?');" class="d-none">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-8">Aucun utilisateur pour le moment.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@foreach($users as $user)
    @if(auth()->user()->hasPermission('users', 'edit'))
    <div class="modal fade" id="permissionsModal-{{ $user->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable permissions-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.users.permissions.update', $user) }}">
                    @csrf
                    @method('PATCH')
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title mb-1">Droits d'accès de {{ $user->name }}</h5>
                            <small class="text-muted">Attribuez les droits par rubrique et par module.</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body bg-light">
                        <div class="row g-4">
                            @foreach($permissionModules as $rubrique)
                                <div class="col-12 col-xl-6">
                                    <div class="card border shadow-none h-100">
                                        <div class="card-header bg-white border-bottom px-4 py-3">
                                            <h6 class="mb-0 fw-bold text-dark">{{ $rubrique['label'] }}</h6>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-row-bordered align-middle mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th class="ps-4">Module</th>
                                                            <th class="text-center">Voir</th>
                                                            <th class="text-center">Modifier</th>
                                                            <th class="text-center pe-4">Supprimer</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($rubrique['modules'] as $moduleKey => $moduleLabel)
                                                            @php($modulePermissions = $user->permissions[$moduleKey] ?? [])
                                                            <tr>
                                                                <td class="ps-4 fw-semibold">{{ $moduleLabel }}</td>
                                                                @foreach(['view' => 'Voir', 'edit' => 'Modifier', 'delete' => 'Supprimer'] as $permissionKey => $permissionLabel)
                                                                    <td class="text-center {{ $permissionKey === 'delete' ? 'pe-4' : '' }}">
                                                                        <input type="checkbox" class="form-check-input" aria-label="{{ $permissionLabel }} {{ $moduleLabel }}" name="permissions[{{ $moduleKey }}][{{ $permissionKey }}]" value="1" {{ !empty($modulePermissions[$permissionKey]) ? 'checked' : '' }}>
                                                                    </td>
                                                                @endforeach
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="modal-footer bg-white">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer les permissions</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
@endforeach
@endsection
