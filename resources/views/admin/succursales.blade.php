@extends('admin.layout')

@section('content')
@php
    $accounts = $succursales->sum(fn ($succursale) => $succursale->users->count());
    $open = $succursales->where('is_active', true);
    $cities = $succursales->pluck('city')->filter()->map(fn ($city) => trim($city))->unique()->values();

    $stats = [
        ['Succursales', $succursales->count(), 'bi-shop', 'indigo', $succursales->count() - $open->count() . ' fermée(s)'],
        ['Ouvertes', $open->count(), 'bi-check-circle', 'green', 'accès ouvert à leurs comptes'],
        ['Comptes rattachés', $accounts, 'bi-people', 'purple', 'responsables de succursale'],
        ['Villes couvertes', $cities->count(), 'bi-geo-alt', 'teal', $cities->isNotEmpty() ? $cities->take(3)->implode(', ') : 'aucune ville renseignée'],
    ];

    $createHasErrors = $errors->any() && old('_succursale_form') === 'create';
    $editedId = old('_succursale_form') === 'edit' ? old('succursale_id') : null;
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle">
        <x-slot:actions>
            @if($canManageAll)
                <button type="button" class="dg-btn dg-btn--primary" data-bs-toggle="modal" data-bs-target="#succursaleModal"><i class="bi bi-plus-lg"></i>Nouvelle succursale</button>
            @endif
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any() && ! $createHasErrors && ! $editedId)<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="dg-kpi-grid">
        @foreach($stats as [$label, $value, $icon, $color, $hint])
            <x-dg.kpi :label="$label" :value="$value" :icon="$icon" :color="$color" :hint="$hint" />
        @endforeach
    </div>

    <div class="dg-card dg-card--table">
        <div class="dg-card__header flex-wrap">
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-indigo"><i class="bi bi-shop"></i></span>Établissements</h2>
            @if($succursales->count() > 1)
                <label class="dg-search" style="max-width:240px">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input id="succursaleSearch" type="search" placeholder="Nom, code, ville…" aria-label="Rechercher une succursale">
                </label>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 succursales-table no-export" id="succursalesTable">
                <thead>
                    <tr>
                        <th>Succursale</th>
                        <th>Adresse</th>
                        <th>Téléphone</th>
                        <th>Comptes rattachés</th>
                        <th>État</th>
                        @if($canManageAll)<th class="text-end"><span class="visually-hidden">Actions</span></th>@endif
                    </tr>
                </thead>
                <tbody>
                @forelse($succursales as $succursale)
                    <tr>
                        <td>
                            <span class="d-block fw-semibold">{{ $succursale->name }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">Code {{ $succursale->code }}</span>
                        </td>
                        <td>
                            <span class="d-block">{{ $succursale->city ?: 'Ville non renseignée' }}</span>
                            @if($succursale->address)<span class="d-block dg-muted" style="font-size:12.5px;max-width:220px">{{ $succursale->address }}</span>@endif
                        </td>
                        <td class="text-nowrap">{{ $succursale->phone ?: '—' }}</td>
                        <td>
                            @if($succursale->users->isNotEmpty())
                                <span class="d-block">{{ $succursale->users->count() }} compte(s)</span>
                                <span class="d-block dg-muted text-truncate" style="font-size:12.5px;max-width:200px">{{ $succursale->users->pluck('name')->implode(', ') }}</span>
                            @else
                                <span class="dg-muted">Aucun compte</span>
                            @endif
                        </td>
                        <td>
                            <span class="dg-badge dg-badge--{{ $succursale->is_active ? 'success' : 'neutral' }}">
                                <i class="bi {{ $succursale->is_active ? 'bi-door-open' : 'bi-door-closed' }}"></i>{{ $succursale->is_active ? 'Ouverte' : 'Fermée' }}
                            </span>
                        </td>
                        @if($canManageAll)
                            <td class="text-end">
                                <div class="d-flex align-items-center justify-content-end gap-2">
                                    <button type="button" class="dg-btn dg-btn--outline dg-btn--sm" data-bs-toggle="modal" data-bs-target="#succursaleEditModal"
                                        data-succursale="{{ json_encode([
                                            'id' => $succursale->id,
                                            'name' => $succursale->name,
                                            'code' => $succursale->code,
                                            'address' => $succursale->address,
                                            'city' => $succursale->city,
                                            'phone' => $succursale->phone,
                                            'action' => route('admin.succursales.update', $succursale),
                                        ]) }}"><i class="bi bi-pencil"></i>Modifier</button>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light action-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions pour {{ $succursale->name }}"></button>
                                        <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                                            <li>
                                                <form method="POST" action="{{ route('admin.succursales.toggle', $succursale) }}">
                                                    @csrf @method('PATCH')
                                                    <button class="dropdown-item"><i class="bi {{ $succursale->is_active ? 'bi-door-closed' : 'bi-door-open' }}"></i>{{ $succursale->is_active ? 'Fermer la succursale' : 'Rouvrir la succursale' }}</button>
                                                </form>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" action="{{ route('admin.succursales.destroy', $succursale) }}" onsubmit="return confirm('Supprimer la succursale « {{ addslashes($succursale->name) }} » ?')">
                                                    @csrf @method('DELETE')
                                                    <button class="dropdown-item text-danger"><i class="bi bi-trash"></i>Supprimer</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManageAll ? 6 : 5 }}" class="p-5">
                            <div class="dg-chart-empty" style="min-height:200px">
                                <span class="dg-tile dg-tone-indigo"><i class="bi bi-shop"></i></span>
                                <div><strong>Aucune succursale enregistrée</strong>Déclarez vos établissements : chacun reçoit son compte responsable, rattaché à l’entreprise.</div>
                                @if($canManageAll)
                                    <button type="button" class="dg-btn dg-btn--primary dg-btn--sm" data-bs-toggle="modal" data-bs-target="#succursaleModal"><i class="bi bi-plus-lg"></i>Nouvelle succursale</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="dg-chart-empty mt-4 d-none" id="succursalesNoResult" style="min-height:140px">
            <span class="dg-tile dg-tone-indigo"><i class="bi bi-search"></i></span>
            <div><strong>Aucune succursale ne correspond</strong>Modifiez la recherche.</div>
        </div>
        <p class="dg-muted mt-3 mb-0" style="font-size:12.5px"><i class="bi bi-info-circle me-1"></i>Fermer une succursale ferme aussi l’accès de ses comptes. Une succursale qui porte des comptes ne se supprime pas : ses responsables deviendraient administrateurs de toute l’entreprise.</p>
    </div>

    @if($canManageAll)
        {{-- Création : la succursale et son compte responsable. --}}
        <div class="modal fade" id="succursaleModal" tabindex="-1" aria-labelledby="succursaleModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg dg-tone-indigo">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.succursales.store') }}">
                        @csrf
                        <input type="hidden" name="_succursale_form" value="create">
                        <div class="modal-header">
                            <h5 class="modal-title" id="succursaleModalTitle"><i class="bi bi-shop me-2"></i>Nouvelle succursale</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body">
                            @if($createHasErrors)<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label" for="succName">Nom <span class="text-danger">*</span></label>
                                    <input id="succName" name="name" class="form-control @error('name') is-invalid @enderror" required maxlength="255" value="{{ old('name') }}" placeholder="Ex. Agence de Cocody">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="succCode">Code <span class="text-danger">*</span></label>
                                    <input id="succCode" name="code" class="form-control @error('code') is-invalid @enderror" required maxlength="50" value="{{ old('code') }}" placeholder="Ex. COC">
                                    @error('code')<span class="dg-field-error">{{ $message }}</span>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="succAddress">Adresse</label>
                                    <input id="succAddress" name="address" class="form-control @error('address') is-invalid @enderror" maxlength="255" value="{{ old('address') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="succCity">Ville</label>
                                    <input id="succCity" name="city" class="form-control @error('city') is-invalid @enderror" maxlength="100" value="{{ old('city') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="succPhone">Téléphone</label>
                                    <input id="succPhone" name="phone" class="form-control @error('phone') is-invalid @enderror" maxlength="50" value="{{ old('phone') }}">
                                </div>
                            </div>

                            <h6 class="dg-section-title mt-5 mb-3">Compte responsable de la succursale</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="succAdminName">Nom complet <span class="text-danger">*</span></label>
                                    <input id="succAdminName" name="admin_name" class="form-control @error('admin_name') is-invalid @enderror" required maxlength="255" value="{{ old('admin_name') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="succAdminEmail">E-mail <span class="text-danger">*</span></label>
                                    <input id="succAdminEmail" name="admin_email" type="email" class="form-control @error('admin_email') is-invalid @enderror" required maxlength="255" value="{{ old('admin_email') }}">
                                    @error('admin_email')<span class="dg-field-error">{{ $message }}</span>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="succAdminPassword">Mot de passe <span class="text-danger">*</span></label>
                                    <input id="succAdminPassword" name="admin_password" type="password" class="form-control @error('admin_password') is-invalid @enderror" required minlength="8">
                                    <span class="form-text">Huit caractères au moins.</span>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="succAdminPasswordConfirm">Confirmation <span class="text-danger">*</span></label>
                                    <input id="succAdminPasswordConfirm" name="admin_password_confirmation" type="password" class="form-control" required minlength="8">
                                </div>
                            </div>
                            <p class="form-text mt-3 mb-0">Ce compte administre la succursale uniquement : il ne voit pas les autres établissements.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Créer la succursale</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Modification de la fiche, sans toucher aux comptes. --}}
        <div class="modal fade" id="succursaleEditModal" tabindex="-1" aria-labelledby="succursaleEditTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered dg-tone-indigo">
                <div class="modal-content">
                    <form method="POST" id="succursaleEditForm" action="{{ $editedId ? route('admin.succursales.update', $editedId) : '#' }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="_succursale_form" value="edit">
                        <input type="hidden" name="succursale_id" id="succursaleEditId" value="{{ $editedId }}">
                        <div class="modal-header">
                            <h5 class="modal-title" id="succursaleEditTitle"><i class="bi bi-pencil me-2"></i>Modifier la succursale</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body">
                            @if($editedId)<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
                            <div class="row g-3">
                                <div class="col-sm-8">
                                    <label class="form-label" for="succEditName">Nom <span class="text-danger">*</span></label>
                                    <input id="succEditName" name="name" class="form-control" required maxlength="255" value="{{ old('name') }}">
                                </div>
                                <div class="col-sm-4">
                                    <label class="form-label" for="succEditCode">Code <span class="text-danger">*</span></label>
                                    <input id="succEditCode" name="code" class="form-control" required maxlength="50" value="{{ old('code') }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="succEditAddress">Adresse</label>
                                    <input id="succEditAddress" name="address" class="form-control" maxlength="255" value="{{ old('address') }}">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="succEditCity">Ville</label>
                                    <input id="succEditCity" name="city" class="form-control" maxlength="100" value="{{ old('city') }}">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="succEditPhone">Téléphone</label>
                                    <input id="succEditPhone" name="phone" class="form-control" maxlength="50" value="{{ old('phone') }}">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

<style>
    .succursales-table { min-width: 880px; }
    .dg-scope .succursales-table > thead > tr > th, .dg-scope .succursales-table > tbody > tr > td { padding-left: 10px !important; padding-right: 10px !important; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = Array.from(document.querySelectorAll('#succursalesTable tbody tr'));
    const search = document.getElementById('succursaleSearch');
    const noResult = document.getElementById('succursalesNoResult');
    search?.addEventListener('input', function () {
        const term = search.value.trim().toLowerCase();
        let visible = 0;
        rows.forEach(function (row) {
            const show = !term || row.textContent.toLowerCase().includes(term);
            row.style.display = show ? '' : 'none';
            visible += show ? 1 : 0;
        });
        noResult.classList.toggle('d-none', !rows.length || visible > 0);
    });

    const edit = document.getElementById('succursaleEditModal');
    if (edit) {
        const form = document.getElementById('succursaleEditForm');
        edit.addEventListener('show.bs.modal', function (event) {
            const trigger = event.relatedTarget;
            // Sans déclencheur (réouverture après une erreur), la saisie déjà en place est conservée.
            if (!trigger) return;
            const succursale = JSON.parse(trigger.dataset.succursale);
            form.action = succursale.action;
            document.getElementById('succursaleEditId').value = succursale.id;
            ['name', 'code', 'address', 'city', 'phone'].forEach(function (field) {
                form.elements[field].value = succursale[field] ?? '';
            });
        });
        @if($createHasErrors)
            window.addEventListener('load', () => new bootstrap.Modal(document.getElementById('succursaleModal')).show());
        @elseif($editedId)
            window.addEventListener('load', () => new bootstrap.Modal(edit).show());
        @endif
    }
});
</script>
@endsection
