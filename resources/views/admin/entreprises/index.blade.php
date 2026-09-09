@extends('admin.layout')

@section('content')
<div class="card border-0 mb-6">
    <div class="card-header border-0 px-5 py-4">
        <div class="card-title fs-4 fw-bold text-dark">Entreprises</div>
        <div class="card-toolbar">
            <a href="{{ route('admin.entreprises.create') }}" class="btn btn-primary btn-sm px-4 py-3">
                <i class="ki-duotone ki-plus fs-2 me-2"></i>
                Nouvelle entreprise
            </a>
        </div>
    </div>

    <div class="card-body p-0">
        @if(session('success'))
            <div class="mx-5 mt-5 alert alert-success d-flex align-items-center" role="alert">
                <i class="ki-duotone ki-check-circle fs-2 me-3"></i>
                <div>{{ session('success') }}</div>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Slug</th>
                        <th>Base</th>
                        <th>Statut</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entreprises as $entreprise)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-40px symbol-circle bg-light-info text-info fw-bold me-3">
                                        {{ strtoupper(substr($entreprise->name ?? 'E', 0, 1)) }}
                                    </div>
                                    <div class="fw-bold text-dark">{{ $entreprise->name }}</div>
                                </div>
                            </td>
                            <td>{{ $entreprise->slug }}</td>
                            <td>{{ $entreprise->database_name ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $entreprise->is_active ? 'badge-light-success' : 'badge-light-secondary' }} px-3 py-2">
                                    {{ $entreprise->is_active ? 'Actif' : 'Inactif' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('admin.entreprises.edit', $entreprise) }}" class="btn btn-sm btn-light-primary">Éditer</a>
                                    <form action="{{ route('admin.entreprises.destroy', $entreprise) }}" method="POST" onsubmit="return confirm('Supprimer cette entreprise ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-light-danger">Supprimer</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-8">Aucune entreprise pour le moment.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
