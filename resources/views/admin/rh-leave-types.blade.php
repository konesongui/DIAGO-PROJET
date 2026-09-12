@extends('admin.layout')

@section('content')
@php
    $active = $types->where('is_active', true);
    $stats = [
        ['Types de congé', $types->count(), 'bi-gear', 'blue', $active->count() . ' ouvert(s) aux demandes'],
        ['Jours accordés', $active->sum('days'), 'bi-calendar-check', 'green', 'par an et par employé, tous types confondus'],
        ['Demandes déposées', (int) $usage->sum('requests'), 'bi-inbox', 'purple', 'depuis la mise en service'],
        ['Jours validés', (int) $usage->sum('days'), 'bi-airplane', 'orange', 'congés acceptés, toutes années'],
    ];
    // Après une erreur, la fenêtre se rouvre sur le type en cours de saisie.
    $formHasErrors = $errors->any() && old('_leave_type_form');
    $editedId = old('leave_type_id');
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.rh')" back-label="RH & Paie">
        <x-slot:actions>
            <a href="{{ route('admin.rh.leaves') }}" class="dg-btn dg-btn--outline"><i class="bi bi-calendar-range"></i>Liste des congés</a>
            <button type="button" class="dg-btn dg-btn--primary" data-bs-toggle="modal" data-bs-target="#leaveTypeModal"><i class="bi bi-plus-lg"></i>Nouveau type de congé</button>
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any() && ! $formHasErrors)<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="dg-kpi-grid">
        @foreach($stats as [$label, $value, $icon, $color, $hint])
            <x-dg.kpi :label="$label" :value="$value" :icon="$icon" :color="$color" :hint="$hint" />
        @endforeach
    </div>

    <div class="dg-card dg-card--table">
        <div class="dg-card__header flex-wrap">
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-blue"><i class="bi bi-gear"></i></span>Types de congé</h2>
            <span class="dg-card__meta">{{ $active->count() }} ouvert(s) · {{ $types->count() - $active->count() }} fermé(s)</span>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 leave-types-table no-export">
                <thead>
                    <tr>
                        <th>Type de congé</th>
                        <th class="text-end">Jours par an</th>
                        <th class="text-end">Utilisation</th>
                        <th>État</th>
                        <th class="text-end"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($types as $type)
                    @php $used = $usage->get($type->id); @endphp
                    <tr>
                        <td>
                            <span class="d-block fw-semibold">{{ $type->name }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ $type->description ?: 'Sans précision' }}</span>
                        </td>
                        <td class="text-end dg-cell-num">{{ $type->days }}</td>
                        <td class="text-end text-nowrap">
                            <span class="d-block">{{ (int) ($used->requests ?? 0) }} demande(s)</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ (int) ($used->days ?? 0) }} jour(s) validé(s)</span>
                        </td>
                        <td>
                            <span class="dg-badge dg-badge--{{ $type->is_active ? 'success' : 'neutral' }}">
                                <i class="bi {{ $type->is_active ? 'bi-check-circle' : 'bi-slash-circle' }}"></i>{{ $type->is_active ? 'Ouvert' : 'Fermé' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light action-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions pour {{ $type->name }}"></button>
                                <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#leaveTypeModal"
                                        data-leave-type="{{ json_encode(['id' => $type->id, 'name' => $type->name, 'days' => $type->days, 'description' => $type->description, 'is_active' => (bool) $type->is_active, 'action' => route('admin.rh.leaveTypes.update', $type)]) }}"><i class="bi bi-pencil"></i>Modifier</button></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('admin.rh.leaveTypes.destroy', $type) }}" onsubmit="return confirm('Supprimer le type de congé « {{ addslashes($type->name) }} » ?')">
                                            @csrf @method('DELETE')
                                            <button class="dropdown-item text-danger"><i class="bi bi-trash"></i>Supprimer</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-5">
                            <div class="dg-chart-empty" style="min-height:200px">
                                <span class="dg-tile dg-tone-blue"><i class="bi bi-gear"></i></span>
                                <div><strong>Aucun type de congé</strong>Créez les congés de l’entreprise (annuel, maternité, maladie…) : chaque employé pourra alors en faire la demande.</div>
                                <button type="button" class="dg-btn dg-btn--primary dg-btn--sm" data-bs-toggle="modal" data-bs-target="#leaveTypeModal"><i class="bi bi-plus-lg"></i>Nouveau type de congé</button>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <p class="dg-muted mt-3 mb-0" style="font-size:12.5px"><i class="bi bi-info-circle me-1"></i>Le nombre de jours est le droit annuel de chaque employé : les congés validés dans l’année s’en déduisent. Un type utilisé par des demandes ne se supprime pas, il se ferme.</p>
    </div>

    {{-- Création et modification d'un type de congé. --}}
    <div class="modal fade" id="leaveTypeModal" tabindex="-1" aria-labelledby="leaveTypeModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="leaveTypeForm"
                    action="{{ $editedId ? route('admin.rh.leaveTypes.update', $editedId) : route('admin.rh.leaveTypes.store') }}"
                    data-store-action="{{ route('admin.rh.leaveTypes.store') }}">
                    @csrf
                    <input type="hidden" name="_leave_type_form" value="1">
                    <input type="hidden" name="_method" value="PUT" id="leaveTypeMethod" @disabled(! $editedId)>
                    <input type="hidden" name="leave_type_id" value="{{ $editedId }}" id="leaveTypeId" @disabled(! $editedId)>
                    <div class="modal-header">
                        <h5 class="modal-title" id="leaveTypeModalTitle"><i class="bi bi-gear me-2"></i><span>{{ $editedId ? 'Modifier le type de congé' : 'Nouveau type de congé' }}</span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        @if($formHasErrors)<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
                        <div class="row g-3">
                            <div class="col-sm-7">
                                <label class="form-label" for="leaveTypeName">Nom <span class="text-danger">*</span></label>
                                <input id="leaveTypeName" name="name" class="form-control @error('name') is-invalid @enderror" required maxlength="100" value="{{ old('name') }}" placeholder="Ex. Congé annuel">
                            </div>
                            <div class="col-sm-5">
                                <label class="form-label" for="leaveTypeDays">Jours par an <span class="text-danger">*</span></label>
                                <input id="leaveTypeDays" name="days" type="number" min="1" max="365" class="form-control @error('days') is-invalid @enderror" required value="{{ old('days') }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="leaveTypeDescription">Précision</label>
                                <textarea id="leaveTypeDescription" name="description" rows="2" maxlength="1000" class="form-control @error('description') is-invalid @enderror" placeholder="À qui s’adresse ce congé, conditions…">{{ old('description') }}</textarea>
                            </div>
                            <div class="col-12" id="leaveTypeActiveRow">
                                <label class="form-check d-flex align-items-center gap-2">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="leaveTypeActive" @checked(old('is_active', true))>
                                    <span class="form-check-label">Ouvert aux demandes</span>
                                </label>
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
</div>

<style>
    .leave-types-table { min-width: 700px; }
    .dg-scope .leave-types-table > thead > tr > th, .dg-scope .leave-types-table > tbody > tr > td { padding-left: 10px !important; padding-right: 10px !important; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('leaveTypeModal');
    const form = document.getElementById('leaveTypeForm');
    const method = document.getElementById('leaveTypeMethod');
    const id = document.getElementById('leaveTypeId');
    const activeRow = document.getElementById('leaveTypeActiveRow');
    modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) return;
        const type = trigger.dataset.leaveType ? JSON.parse(trigger.dataset.leaveType) : null;
        form.action = type ? type.action : form.dataset.storeAction;
        method.disabled = !type;
        id.disabled = !type;
        id.value = type ? type.id : '';
        modal.querySelector('.modal-title span').textContent = type ? 'Modifier le type de congé' : 'Nouveau type de congé';
        form.elements.name.value = type ? type.name : '';
        form.elements.days.value = type ? type.days : '';
        form.elements.description.value = type && type.description ? type.description : '';
        form.elements.is_active.checked = type ? type.is_active : true;
        // À la création, le congé est forcément ouvert : la case n'a pas lieu d'être.
        activeRow.classList.toggle('d-none', !type);
    });
    activeRow.classList.toggle('d-none', !{{ $editedId ? 'true' : 'false' }});
    @if($formHasErrors)
        window.addEventListener('load', () => new bootstrap.Modal(modal).show());
    @endif
});
</script>
@endsection
