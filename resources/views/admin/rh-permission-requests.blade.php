@extends('admin.layout')

@section('content')
@php
    $today = now()->startOfDay();
    $states = [
        'pending' => ['En attente', 'warning', 'bi-hourglass-split', 'En attente'],
        'approved' => ['Acceptée', 'success', 'bi-check-circle', 'Acceptées'],
        'rejected' => ['Refusée', 'danger', 'bi-x-circle', 'Refusées'],
    ];
    $byState = $requests->groupBy('status');
    $pending = $byState->get('pending', collect());
    $approved = $byState->get('approved', collect());
    $days = fn ($request) => $request->start_date->diffInDays($request->end_date) + 1;
    $inProgress = $approved->filter(fn ($request) => $request->start_date->lte($today) && $request->end_date->gte($today));
    $stats = [
        ['En attente', $pending->count(), 'bi-hourglass-split', 'orange', 'à traiter'],
        ['Acceptées', $approved->count(), 'bi-check-circle', 'green', $approved->sum($days) . ' jour(s) d’absence accordés'],
        ['Absences en cours', $inProgress->count(), 'bi-person-dash', 'blue', 'permissions couvrant le ' . $today->format('d/m/Y')],
        ['Refusées', $byState->get('rejected', collect())->count(), 'bi-x-circle', 'red', 'sur l’ensemble des demandes'],
    ];
    $formHasErrors = $errors->any() && old('_permission_form');
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.rh')" back-label="RH & Paie">
        <x-slot:actions>
            <a href="{{ route('admin.rh.leaves') }}" class="dg-btn dg-btn--outline"><i class="bi bi-calendar-range"></i>Congés</a>
            @if($isAdmin || $employee)
                <button type="button" class="dg-btn dg-btn--primary" data-bs-toggle="modal" data-bs-target="#permissionModal">
                    <i class="bi bi-plus-lg"></i>{{ $isAdmin ? 'Enregistrer une permission' : 'Demander une permission' }}
                </button>
            @endif
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
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-purple"><i class="bi bi-pencil-square"></i></span>Demandes de permission</h2>
            @if($requests->isNotEmpty())
                <label class="dg-search" style="max-width:240px">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input id="permissionSearch" type="search" placeholder="Employé, motif…" aria-label="Rechercher une demande">
                </label>
            @endif
        </div>

        @if($requests->isNotEmpty())
            <div class="dg-tabs mb-4" role="group" aria-label="Filtrer par état">
                <button type="button" class="dg-tab is-active" data-state-filter="" aria-pressed="true">Toutes ({{ $requests->count() }})</button>
                @foreach($states as $value => [, , , $tabLabel])
                    <button type="button" class="dg-tab" data-state-filter="{{ $value }}" aria-pressed="false">{{ $tabLabel }} ({{ $byState->get($value, collect())->count() }})</button>
                @endforeach
            </div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle mb-0 permissions-table no-export" id="permissionsTable">
                <thead>
                    <tr>
                        @if($isAdmin)<th>Employé</th>@endif
                        <th>Motif</th>
                        <th>Période</th>
                        <th class="text-end">Jours</th>
                        <th>Explication</th>
                        <th>État</th>
                        @if($isAdmin)<th class="text-end"><span class="visually-hidden">Actions</span></th>@endif
                    </tr>
                </thead>
                <tbody>
                @forelse($requests as $request)
                    @php [$stateLabel, $stateTone, $stateIcon] = $states[$request->status] ?? $states['pending']; @endphp
                    <tr data-state="{{ $request->status }}">
                        @if($isAdmin)
                            <td>
                                <span class="d-block fw-semibold">{{ $request->employee?->full_name ?? 'Employé supprimé' }}</span>
                                <span class="d-block dg-muted" style="font-size:12.5px">{{ $request->employee?->department ?: 'Service non renseigné' }}</span>
                            </td>
                        @endif
                        <td>{{ $request->typeLabel() }}</td>
                        <td class="text-nowrap">
                            <span class="d-block">{{ $request->start_date->format('d/m/Y') }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">au {{ $request->end_date->format('d/m/Y') }}</span>
                        </td>
                        <td class="text-end dg-cell-num">{{ $days($request) }}</td>
                        <td><span class="d-block text-truncate" style="max-width:230px">{{ $request->reason ?: '—' }}</span></td>
                        <td>
                            <span class="dg-badge dg-badge--{{ $stateTone }}"><i class="bi {{ $stateIcon }}"></i>{{ $stateLabel }}</span>
                            @if($request->reviewer)<span class="d-block dg-muted" style="font-size:12px">par {{ $request->reviewer->name }}</span>@endif
                        </td>
                        @if($isAdmin)
                            <td class="text-end">
                                @if($request->status === 'pending')
                                    <button type="button" class="dg-btn dg-btn--outline dg-btn--sm" data-bs-toggle="modal" data-bs-target="#reviewPermissionModal"
                                        data-review="{{ json_encode([
                                            'action' => route('admin.rh.permissions.review', $request),
                                            'employee' => $request->employee?->full_name ?? 'Employé supprimé',
                                            'type' => $request->typeLabel(),
                                            'period' => $request->start_date->format('d/m/Y') . ' au ' . $request->end_date->format('d/m/Y'),
                                            'days' => $days($request),
                                            'reason' => $request->reason,
                                        ]) }}"><i class="bi bi-check2-circle"></i>Traiter</button>
                                @elseif($request->review_comment)
                                    <span class="dg-muted" style="font-size:12.5px">{{ $request->review_comment }}</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isAdmin ? 7 : 5 }}" class="p-5">
                            <div class="dg-chart-empty" style="min-height:200px">
                                <span class="dg-tile dg-tone-purple"><i class="bi bi-pencil-square"></i></span>
                                <div><strong>Aucune demande de permission</strong>Une permission est une absence courte : rendez-vous médical, démarche administrative, événement familial.</div>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="dg-chart-empty mt-4 d-none" id="permissionsNoResult" style="min-height:140px">
            <span class="dg-tile dg-tone-purple"><i class="bi bi-search"></i></span>
            <div><strong>Aucune demande ne correspond</strong>Modifiez la recherche ou l’onglet.</div>
        </div>
        <p class="dg-muted mt-3 mb-0" style="font-size:12.5px"><i class="bi bi-info-circle me-1"></i>Une permission ne touche pas au solde des congés. Deux permissions ne peuvent pas se chevaucher pour le même employé.</p>
    </div>

    {{-- Nouvelle demande, ou permission enregistrée par l'administration. --}}
    @if($isAdmin || $employee)
        <div class="modal fade" id="permissionModal" tabindex="-1" aria-labelledby="permissionModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.rh.permissions.store') }}">
                        @csrf
                        <input type="hidden" name="_permission_form" value="1">
                        <div class="modal-header">
                            <h5 class="modal-title" id="permissionModalTitle"><i class="bi bi-pencil-square me-2"></i>{{ $isAdmin ? 'Enregistrer une permission' : 'Demander une permission' }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body">
                            @if($formHasErrors)<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
                            <div class="row g-3">
                                @if($isAdmin)
                                    <div class="col-12">
                                        <label class="form-label" for="permissionEmployee">Employé <span class="text-danger">*</span></label>
                                        <select id="permissionEmployee" name="employee_id" class="form-select @error('employee_id') is-invalid @enderror" required>
                                            <option value="">Choisir un employé</option>
                                            @foreach($employees as $item)
                                                <option value="{{ $item->id }}" @selected((int) old('employee_id') === $item->id)>{{ $item->full_name }}{{ $item->department ? ' — ' . $item->department : '' }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div class="col-12">
                                    <label class="form-label" for="permissionType">Motif <span class="text-danger">*</span></label>
                                    <select id="permissionType" name="type" class="form-select @error('type') is-invalid @enderror" required>
                                        <option value="">Choisir un motif</option>
                                        @foreach($types as $value => $label)
                                            <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="permissionStart">Du <span class="text-danger">*</span></label>
                                    <input id="permissionStart" name="start_date" type="date" class="form-control @error('start_date') is-invalid @enderror" required value="{{ old('start_date') }}">
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="permissionEnd">Au <span class="text-danger">*</span></label>
                                    <input id="permissionEnd" name="end_date" type="date" class="form-control @error('end_date') is-invalid @enderror" required value="{{ old('end_date') }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="permissionReason">Explication <span class="text-danger">*</span></label>
                                    <textarea id="permissionReason" name="reason" rows="2" maxlength="2000" class="form-control @error('reason') is-invalid @enderror" required>{{ old('reason') }}</textarea>
                                </div>
                            </div>
                            <p class="form-text mt-3 mb-0">{{ $isAdmin ? 'La permission est enregistrée et acceptée directement : c’est l’administration qui valide.' : 'Votre demande part en validation auprès de l’administration.' }}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>{{ $isAdmin ? 'Enregistrer' : 'Envoyer la demande' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Validation ou refus. --}}
    @if($isAdmin)
        <div class="modal fade" id="reviewPermissionModal" tabindex="-1" aria-labelledby="reviewPermissionTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" id="reviewPermissionForm" action="#">
                        @csrf @method('PATCH')
                        <div class="modal-header">
                            <h5 class="modal-title" id="reviewPermissionTitle"><i class="bi bi-check2-circle me-2"></i>Traiter la demande</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body">
                            <div class="permission-review mb-3">
                                <div><span>Employé</span><strong data-review-field="employee"></strong></div>
                                <div><span>Motif</span><strong data-review-field="type"></strong></div>
                                <div><span>Période</span><strong data-review-field="period"></strong></div>
                                <div><span>Jours</span><strong data-review-field="days"></strong></div>
                            </div>
                            <p class="dg-muted mb-3" style="font-size:13px" data-review-field="reason"></p>
                            <label class="form-label" for="permissionComment">Commentaire</label>
                            <textarea id="permissionComment" name="review_comment" rows="2" maxlength="2000" class="form-control" placeholder="Motif du refus, conditions…"></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" name="status" value="rejected" class="btn btn-light-danger"><i class="bi bi-x-lg me-1"></i>Refuser</button>
                            <button type="submit" name="status" value="approved" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Accepter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

<style>
    .permissions-table { min-width: 780px; }
    .dg-scope .permissions-table > thead > tr > th, .dg-scope .permissions-table > tbody > tr > td { padding-left: 10px !important; padding-right: 10px !important; }
    .permission-review { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .permission-review > div { padding: 10px 12px; border-radius: var(--dg-radius); background: var(--dg-table-head); }
    .permission-review span { display: block; font-size: 12.5px; color: var(--dg-muted); }
    .permission-review strong { font-size: 14px; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = Array.from(document.querySelectorAll('#permissionsTable tbody tr[data-state]'));
    const search = document.getElementById('permissionSearch');
    const tabs = Array.from(document.querySelectorAll('[data-state-filter]'));
    const noResult = document.getElementById('permissionsNoResult');
    let state = '';
    const filterRows = function () {
        const term = search ? search.value.trim().toLowerCase() : '';
        let visible = 0;
        rows.forEach(function (row) {
            const show = (!term || row.textContent.toLowerCase().includes(term)) && (!state || row.dataset.state === state);
            row.style.display = show ? '' : 'none';
            visible += show ? 1 : 0;
        });
        noResult.classList.toggle('d-none', !rows.length || visible > 0);
    };
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            state = tab.dataset.stateFilter;
            tabs.forEach(function (item) {
                item.classList.toggle('is-active', item === tab);
                item.setAttribute('aria-pressed', item === tab ? 'true' : 'false');
            });
            filterRows();
        });
    });
    search?.addEventListener('input', filterRows);

    const review = document.getElementById('reviewPermissionModal');
    if (review) {
        const form = document.getElementById('reviewPermissionForm');
        review.addEventListener('show.bs.modal', function (event) {
            const data = event.relatedTarget?.dataset.review ? JSON.parse(event.relatedTarget.dataset.review) : null;
            if (!data) return;
            form.action = data.action;
            form.reset();
            review.querySelectorAll('[data-review-field]').forEach(function (field) {
                const key = field.dataset.reviewField;
                field.textContent = key === 'reason' ? (data.reason ? 'Explication : ' + data.reason : 'Aucune explication fournie.') : (data[key] ?? '—');
            });
        });
    }
    @if($formHasErrors)
        window.addEventListener('load', () => new bootstrap.Modal(document.getElementById('permissionModal')).show());
    @endif
});
</script>
@endsection
