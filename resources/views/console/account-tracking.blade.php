@extends('admin.layout')
@section('content')
<div class="card border-0 shadow-sm overflow-hidden">
    <div class="card-header bg-white border-0 p-5 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div><div class="text-uppercase text-muted small fw-bold">Console Super Administrateur</div><h1 class="h4 fw-bold mb-1">Suivi des comptes</h1><p class="text-muted mb-0">Consultez et gérez les espaces entreprises, leurs abonnements et leurs comptes administrateurs.</p></div>
        <a href="{{ route('console.entreprises.create') }}" class="btn btn-primary">+ Nouvelle entreprise</a>
    </div>
    @if(session('success'))<div class="mx-5 mt-4 alert alert-success">{{ session('success') }}</div>@endif
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th class="ps-5">Entreprise</th><th>Slug</th><th>Administrateurs</th><th>Abonnement jusqu’au</th><th>Statut</th><th class="text-end pe-5">Action</th></tr></thead><tbody>
    @forelse($entreprises as $entreprise)
        <tr><td class="ps-5 fw-bold">{{ $entreprise->name }}</td><td>{{ $entreprise->slug }}</td><td>{{ $entreprise->users_count }}</td><td>{{ data_get($entreprise->settings, 'subscription_expires_at') ? \Carbon\Carbon::parse(data_get($entreprise->settings, 'subscription_expires_at'))->format('d/m/Y') : '—' }}</td><td><span class="badge {{ $entreprise->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $entreprise->is_active ? 'Actif' : 'Inactif' }}</span></td><td class="text-end pe-5"><div class="dropdown"><button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">Actions</button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="{{ route('console.entreprises.show', $entreprise) }}">Voir détails</a></li><li><a class="dropdown-item" href="{{ route('console.entreprises.edit', $entreprise) }}">Modifier</a></li><li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#renew-{{ $entreprise->id }}">Réabonnement</button></li><li><hr class="dropdown-divider"></li><li><form method="POST" action="{{ route('console.entreprises.toggle', $entreprise) }}">@csrf @method('PATCH')<button class="dropdown-item {{ $entreprise->is_active ? 'text-danger' : 'text-success' }}">{{ $entreprise->is_active ? 'Désactiver le compte' : 'Activer le compte' }}</button></form></li></ul></div></td></tr>
        <div class="modal fade" id="renew-{{ $entreprise->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('console.entreprises.renew', $entreprise) }}">@csrf @method('PATCH')<div class="modal-header"><h5 class="modal-title">Réabonnement — {{ $entreprise->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><label class="form-label">Nouvelle date de fin</label><input type="date" name="subscription_expires_at" min="{{ now()->toDateString() }}" class="form-control" value="{{ data_get($entreprise->settings, 'subscription_expires_at') }}" required></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Enregistrer</button></div></form></div></div></div>
    @empty
        <tr><td colspan="6" class="text-center text-muted py-5">Aucune entreprise créée.</td></tr>
    @endforelse
    </tbody></table></div>
</div>
@endsection
