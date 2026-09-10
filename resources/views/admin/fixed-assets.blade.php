@extends('admin.layout')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body p-6">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-6">
            <div><div class="text-uppercase text-muted fs-8 fw-bold ls-1">Comptabilité</div><h3 class="fs-2 fw-bold text-dark mb-1">{{ $title }}</h3><p class="text-muted mb-0">{{ $subtitle }}</p></div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.comptabilite') }}" class="btn btn-light">Retour</a>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createFixedAssetModal">
                    <i class="bi bi-plus-lg me-2"></i>Nouvelle immobilisation
                </button>
            </div>
        </div>
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

        <div class="row g-4 mb-6">
            <div class="col-md-3"><div class="p-4 rounded-3 bg-light-primary"><small>Immobilisations</small><h3>{{ $summary['count'] }}</h3></div></div>
            <div class="col-md-3"><div class="p-4 rounded-3 bg-light-success"><small>Valeur brute</small><h3>{{ money($summary['gross']) }}</h3></div></div>
            <div class="col-md-3"><div class="p-4 rounded-3 bg-light-warning"><small>Valeur nette</small><h3>{{ money($summary['net']) }}</h3></div></div>
            <div class="col-md-3"><div class="p-4 rounded-3 bg-light-info"><small>Actifs en service</small><h3>{{ $summary['active'] }}</h3></div></div>
        </div>

        <form method="GET" class="row g-3 align-items-end mb-4">
            <div class="col-md-4"><label class="form-label">Recherche</label><input name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Nom, référence, catégorie"></div>
            <div class="col-md-3"><label class="form-label">État</label><select name="status" class="form-select"><option value="">Tous</option><option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>En service</option><option value="sold" {{ ($filters['status'] ?? '') === 'sold' ? 'selected' : '' }}>Cédée</option><option value="retired" {{ ($filters['status'] ?? '') === 'retired' ? 'selected' : '' }}>Mise au rebut</option></select></div>
            <div class="col-md-2"><button class="btn btn-light-primary">Filtrer</button></div>
        </form>

        <div class="table-responsive"><table class="table align-middle table-row-dashed">
            <thead><tr class="text-muted text-uppercase fs-7"><th>Immobilisation</th><th>Catégorie</th><th>Acquisition</th><th>Valeur brute</th><th>Amortissement</th><th>Valeur nette</th><th>État</th><th></th></tr></thead>
            <tbody>@forelse($assets as $asset)<tr>
                <td><strong>{{ $asset->name }}</strong><br><small class="text-muted">{{ $asset->reference ?: 'Sans référence' }} · {{ $asset->location ?: 'Emplacement non défini' }}</small></td>
                <td>{{ $asset->asset_category }}</td><td>{{ $asset->acquisition_date?->format('d/m/Y') }}</td>
                <td>{{ money((float)$asset->acquisition_value) }}</td><td>{{ money($asset->depreciation_amount) }}</td><td class="fw-bold">{{ money($asset->net_value) }}</td>
                <td><span class="badge {{ $asset->status === 'active' ? 'badge-light-success' : 'badge-light-secondary' }}">{{ $asset->status === 'active' ? 'En service' : ($asset->status === 'sold' ? 'Cédée' : 'Mise au rebut') }}</span></td>
                <td class="text-end"><form method="POST" action="{{ route('admin.comptabilite.fixedAssets.destroy', $asset) }}" onsubmit="return confirm('Supprimer cette immobilisation ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-light-danger">Supprimer</button></form></td>
            </tr>@empty<tr><td colspan="8" class="text-center text-muted py-8">Aucune immobilisation enregistrée.</td></tr>@endforelse</tbody>
        </table></div>
    </div>
</div>

<div class="modal fade" id="createFixedAssetModal" tabindex="-1" aria-labelledby="createFixedAssetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.comptabilite.fixedAssets.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="createFixedAssetModalLabel">Nouvelle immobilisation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Désignation</label><input name="name" class="form-control" required value="{{ old('name') }}"></div>
                        <div class="col-md-3"><label class="form-label">Catégorie</label><select name="asset_category" class="form-select"><option>Matériel</option><option>Mobilier</option><option>Véhicule</option><option>Informatique</option><option>Immobilier</option><option>Autre</option></select></div>
                        <div class="col-md-2"><label class="form-label">Référence</label><input name="reference" class="form-control" value="{{ old('reference') }}"></div>
                        <div class="col-md-3"><label class="form-label">Fournisseur</label><input name="supplier" class="form-control" value="{{ old('supplier') }}"></div>
                        <div class="col-md-3"><label class="form-label">Date acquisition</label><input type="date" name="acquisition_date" class="form-control" required value="{{ old('acquisition_date', now()->format('Y-m-d')) }}"></div>
                        <div class="col-md-3"><label class="form-label">Valeur acquisition</label><input type="number" name="acquisition_value" class="form-control" min="0.01" step="0.01" required value="{{ old('acquisition_value') }}"></div>
                        <div class="col-md-2"><label class="form-label">Valeur résiduelle</label><input type="number" name="residual_value" class="form-control" min="0" step="0.01" value="{{ old('residual_value', 0) }}"></div>
                        <div class="col-md-2"><label class="form-label">Durée (ans)</label><input type="number" name="useful_life_years" class="form-control" min="1" value="{{ old('useful_life_years', 5) }}" required></div>
                        <div class="col-md-2"><label class="form-label">État</label><select name="status" class="form-select"><option value="active">En service</option><option value="sold">Cédée</option><option value="retired">Mise au rebut</option></select></div>
                        <div class="col-md-4"><label class="form-label">Emplacement</label><input name="location" class="form-control" value="{{ old('location') }}"></div>
                        <div class="col-md-8"><label class="form-label">Description</label><input name="description" class="form-control" value="{{ old('description') }}"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
