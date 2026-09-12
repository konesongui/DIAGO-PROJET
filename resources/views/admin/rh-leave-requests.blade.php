@extends('admin.layout')

@section('content')
@php
    $today = now()->startOfDay();
    $states = [
        'pending' => ['En attente', 'warning', 'bi-hourglass-split', 'En attente'],
        'approved' => ['Validé', 'success', 'bi-check-circle', 'Validés'],
        'rejected' => ['Refusé', 'danger', 'bi-x-circle', 'Refusés'],
    ];
    $byState = $requests->groupBy('status');
    $pending = $byState->get('pending', collect());
    $approved = $byState->get('approved', collect());
    $thisYear = $approved->filter(fn ($request) => $request->start_date?->year === $year);
    $onLeaveNow = $approved->filter(fn ($request) => $request->start_date->lte($today) && $request->end_date->gte($today));
    $stats = [
        ['En attente', $pending->count(), 'bi-hourglass-split', 'orange', $pending->sum('days') . ' jour(s) demandé(s)'],
        ['Validés en ' . $year, $thisYear->count(), 'bi-check-circle', 'green', $thisYear->sum('days') . ' jour(s) accordé(s)'],
        ['En congé aujourd’hui', $onLeaveNow->count(), 'bi-airplane', 'blue', 'congés en cours le ' . $today->format('d/m/Y')],
        ['Refusés', $byState->get('rejected', collect())->count(), 'bi-x-circle', 'red', 'sur l’ensemble des demandes'],
    ];
    // Solde par type pour l'employé connecté (ou pour l'employé choisi, côté administration).
    $balanceOf = fn ($employeeId, $type) => max(0, $type->days - (int) ($balances[$employeeId . '-' . $type->id] ?? 0));
    $formHasErrors = $errors->any() && old('_leave_form');
    $reviewErrors = $errors->has('request');
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.rh')" back-label="RH & Paie">
        <x-slot:actions>
            <a href="{{ route('admin.rh.leaveCalendar') }}" class="dg-btn dg-btn--outline"><i class="bi bi-calendar3"></i>Calendrier</a>
            @if($isAdmin)
                <a href="{{ route('admin.rh.leaveTypes') }}" class="dg-btn dg-btn--outline"><i class="bi bi-gear"></i>Paramétrage</a>
            @endif
            @if($types->isNotEmpty() && ($isAdmin || $employee))
                <button type="button" class="dg-btn dg-btn--primary" data-bs-toggle="modal" data-bs-target="#leaveModal">
                    <i class="bi bi-plus-lg"></i>{{ $isAdmin ? 'Enregistrer un congé' : 'Demander un congé' }}
                </button>
            @endif
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any() && ! $formHasErrors)<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    @if($types->isEmpty())
        <div class="alert alert-warning">
            Aucun type de congé n’est ouvert.
            @if($isAdmin)<a href="{{ route('admin.rh.leaveTypes') }}" class="alert-link">Paramétrez les congés</a> avant d’enregistrer une demande.@else Contactez l’administration.@endif
        </div>
    @endif

    <div class="dg-kpi-grid">
        @foreach($stats as [$label, $value, $icon, $color, $hint])
            <x-dg.kpi :label="$label" :value="$value" :icon="$icon" :color="$color" :hint="$hint" />
        @endforeach
    </div>

    @if(! $isAdmin && $employee && $types->isNotEmpty())
        <x-dg.card title="Vos soldes {{ $year }}" icon="bi-calendar-check" color="green" class="mb-5">
            <div class="leave-balances">
                @foreach($types as $type)
                    @php $left = $balanceOf($employee->id, $type); @endphp
                    <div>
                        <span>{{ $type->name }}</span>
                        <strong>{{ $left }} <small>/ {{ $type->days }} jour(s)</small></strong>
                        <span class="leave-balance-bar"><span style="width:{{ $type->days > 0 ? min(100, ($type->days - $left) / $type->days * 100) : 0 }}%"></span></span>
                    </div>
                @endforeach
            </div>
        </x-dg.card>
    @endif

    <div class="dg-card dg-card--table">
        <div class="dg-card__header flex-wrap">
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-green"><i class="bi bi-calendar-range"></i></span>Demandes de congé</h2>
            @if($requests->isNotEmpty())
                <label class="dg-search" style="max-width:240px">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input id="leaveSearch" type="search" placeholder="Employé, type, motif…" aria-label="Rechercher une demande">
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
            <table class="table align-middle mb-0 leaves-table no-export" id="leavesTable">
                <thead>
                    <tr>
                        @if($isAdmin)<th>Employé</th>@endif
                        <th>Type</th>
                        <th>Période</th>
                        <th class="text-end">Jours</th>
                        <th>Motif</th>
                        <th>État</th>
                        @if($isAdmin)<th class="text-end"><span class="visually-hidden">Actions</span></th>@endif
                    </tr>
                </thead>
                <tbody>
                @forelse($requests as $request)
                    @php
                        [$stateLabel, $stateTone, $stateIcon] = $states[$request->status] ?? $states['pending'];
                        $inProgress = $request->status === 'approved' && $request->start_date->lte($today) && $request->end_date->gte($today);
                    @endphp
                    <tr data-state="{{ $request->status }}">
                        @if($isAdmin)
                            <td>
                                <span class="d-block fw-semibold">{{ $request->employee?->full_name ?? 'Employé supprimé' }}</span>
                                <span class="d-block dg-muted" style="font-size:12.5px">{{ $request->employee?->department ?: 'Service non renseigné' }}</span>
                            </td>
                        @endif
                        <td>{{ $request->leaveType?->name ?? '—' }}</td>
                        <td class="text-nowrap">
                            <span class="d-block">{{ $request->start_date->format('d/m/Y') }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">au {{ $request->end_date->format('d/m/Y') }}</span>
                        </td>
                        <td class="text-end dg-cell-num">{{ $request->days }}</td>
                        <td><span class="d-block text-truncate" style="max-width:220px">{{ $request->reason ?: '—' }}</span></td>
                        <td>
                            <span class="dg-badge dg-badge--{{ $stateTone }}"><i class="bi {{ $stateIcon }}"></i>{{ $stateLabel }}</span>
                            @if($inProgress)<span class="d-block dg-muted" style="font-size:12px">en cours, retour le {{ $request->end_date->copy()->addDay()->format('d/m/Y') }}</span>
                            @elseif($request->reviewer)<span class="d-block dg-muted" style="font-size:12px">par {{ $request->reviewer->name }}</span>@endif
                        </td>
                        @if($isAdmin)
                            <td class="text-end">
                                @if($request->status === 'pending')
                                    <button type="button" class="dg-btn dg-btn--outline dg-btn--sm" data-bs-toggle="modal" data-bs-target="#reviewLeaveModal"
                                        data-review="{{ json_encode([
                                            'action' => route('admin.rh.leaves.review', $request),
                                            'employee' => $request->employee?->full_name ?? 'Employé supprimé',
                                            'type' => $request->leaveType?->name,
                                            'period' => $request->start_date->format('d/m/Y') . ' au ' . $request->end_date->format('d/m/Y'),
                                            'days' => $request->days,
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
                                <span class="dg-tile dg-tone-green"><i class="bi bi-calendar-range"></i></span>
                                <div><strong>Aucune demande de congé</strong>{{ $isAdmin ? 'Enregistrez le congé d’un employé, ou attendez ses demandes : elles arriveront ici pour validation.' : 'Vos demandes de congé et leur suivi s’afficheront ici.' }}</div>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="dg-chart-empty mt-4 d-none" id="leavesNoResult" style="min-height:140px">
            <span class="dg-tile dg-tone-green"><i class="bi bi-search"></i></span>
            <div><strong>Aucune demande ne correspond</strong>Modifiez la recherche ou l’onglet.</div>
        </div>
        <p class="dg-muted mt-3 mb-0" style="font-size:12.5px"><i class="bi bi-info-circle me-1"></i>Les jours sont comptés en jours calendaires, week-ends compris. Le solde annuel d’un type de congé se réduit à chaque congé validé.</p>
    </div>

    {{-- Nouvelle demande, ou congé enregistré par l'administration. --}}
    @if($types->isNotEmpty() && ($isAdmin || $employee))
        <div class="modal fade" id="leaveModal" tabindex="-1" aria-labelledby="leaveModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.rh.leaves.store') }}">
                        @csrf
                        <input type="hidden" name="_leave_form" value="1">
                        <div class="modal-header">
                            <h5 class="modal-title" id="leaveModalTitle"><i class="bi bi-calendar-plus me-2"></i>{{ $isAdmin ? 'Enregistrer un congé' : 'Demander un congé' }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body">
                            @if($formHasErrors)<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
                            <div class="row g-3">
                                @if($isAdmin)
                                    <div class="col-12">
                                        <label class="form-label" for="leaveEmployee">Employé <span class="text-danger">*</span></label>
                                        <select id="leaveEmployee" name="employee_id" class="form-select @error('employee_id') is-invalid @enderror" required>
                                            <option value="">Choisir un employé</option>
                                            @foreach($employees as $item)
                                                <option value="{{ $item->id }}" @selected((int) old('employee_id') === $item->id)>{{ $item->full_name }}{{ $item->department ? ' — ' . $item->department : '' }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div class="col-12">
                                    <label class="form-label" for="leaveType">Type de congé <span class="text-danger">*</span></label>
                                    <select id="leaveType" name="leave_type_id" class="form-select @error('leave_type_id') is-invalid @enderror" required>
                                        <option value="">Choisir un type</option>
                                        @foreach($types as $type)
                                            @php $left = (! $isAdmin && $employee) ? $balanceOf($employee->id, $type) : null; @endphp
                                            <option value="{{ $type->id }}" @selected((int) old('leave_type_id') === $type->id)
                                                data-days="{{ $type->days }}" @if($left !== null) data-left="{{ $left }}" @endif>
                                                {{ $type->name }} — {{ $type->days }} jour(s) par an{{ $left !== null ? ', il vous reste ' . $left . ' jour(s)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="leaveStart">Du <span class="text-danger">*</span></label>
                                    <input id="leaveStart" name="start_date" type="date" class="form-control @error('start_date') is-invalid @enderror" required value="{{ old('start_date') }}">
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="leaveEnd">Au <span class="text-danger">*</span></label>
                                    <input id="leaveEnd" name="end_date" type="date" class="form-control @error('end_date') is-invalid @enderror" required value="{{ old('end_date') }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="leaveReason">Motif</label>
                                    <textarea id="leaveReason" name="reason" rows="2" maxlength="2000" class="form-control @error('reason') is-invalid @enderror">{{ old('reason') }}</textarea>
                                </div>
                            </div>
                            <p class="form-text mt-3 mb-0" id="leaveDaysHint">
                                {{ $isAdmin ? 'Le congé est enregistré et validé directement : c’est l’administration qui valide.' : 'Votre demande part en validation auprès de l’administration.' }}
                            </p>
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

    {{-- Validation ou refus d'une demande. --}}
    @if($isAdmin)
        <div class="modal fade" id="reviewLeaveModal" tabindex="-1" aria-labelledby="reviewLeaveTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" id="reviewLeaveForm" action="#">
                        @csrf @method('PATCH')
                        <div class="modal-header">
                            <h5 class="modal-title" id="reviewLeaveTitle"><i class="bi bi-check2-circle me-2"></i>Traiter la demande</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body">
                            <div class="leave-review mb-3">
                                <div><span>Employé</span><strong data-review-field="employee"></strong></div>
                                <div><span>Type</span><strong data-review-field="type"></strong></div>
                                <div><span>Période</span><strong data-review-field="period"></strong></div>
                                <div><span>Jours</span><strong data-review-field="days"></strong></div>
                            </div>
                            <p class="dg-muted mb-3" style="font-size:13px" data-review-field="reason"></p>
                            <label class="form-label" for="reviewComment">Commentaire</label>
                            <textarea id="reviewComment" name="review_comment" rows="2" maxlength="2000" class="form-control" placeholder="Motif du refus, consignes de remplacement…"></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" name="status" value="rejected" class="btn btn-light-danger"><i class="bi bi-x-lg me-1"></i>Refuser</button>
                            <button type="submit" name="status" value="approved" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Valider</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

<style>
    .leaves-table { min-width: 760px; }
    .dg-scope .leaves-table > thead > tr > th, .dg-scope .leaves-table > tbody > tr > td { padding-left: 10px !important; padding-right: 10px !important; }
    .leave-balances { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; }
    .leave-balances > div { padding: 12px 14px; border: 1px solid var(--dg-border); border-radius: var(--dg-radius); background: var(--dg-surface); }
    .leave-balances span { display: block; font-size: 12.5px; color: var(--dg-muted); }
    .leave-balances strong { display: block; margin: 2px 0 8px; font-size: 18px; }
    .leave-balances strong small { font-size: 12.5px; font-weight: 500; color: var(--dg-muted); }
    .leave-balance-bar { display: block; height: 6px; border-radius: 99px; background: var(--dg-neutral-bg, #eceff6); }
    .leave-balance-bar span { display: block; height: 100%; border-radius: inherit; background: #059669; }
    .leave-review { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .leave-review > div { padding: 10px 12px; border-radius: var(--dg-radius); background: var(--dg-table-head); }
    .leave-review span { display: block; font-size: 12.5px; color: var(--dg-muted); }
    .leave-review strong { font-size: 14px; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Recherche et filtre par état.
    const rows = Array.from(document.querySelectorAll('#leavesTable tbody tr[data-state]'));
    const search = document.getElementById('leaveSearch');
    const tabs = Array.from(document.querySelectorAll('[data-state-filter]'));
    const noResult = document.getElementById('leavesNoResult');
    let state = '';
    const filterLeaves = function () {
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
            filterLeaves();
        });
    });
    search?.addEventListener('input', filterLeaves);

    // Nombre de jours calculé pendant la saisie.
    const modal = document.getElementById('leaveModal');
    if (modal) {
        const start = document.getElementById('leaveStart');
        const end = document.getElementById('leaveEnd');
        const type = document.getElementById('leaveType');
        const hint = document.getElementById('leaveDaysHint');
        const base = hint.textContent.trim();
        const refresh = function () {
            if (!start.value || !end.value) { hint.textContent = base; return; }
            const days = Math.round((new Date(end.value) - new Date(start.value)) / 86400000) + 1;
            if (days <= 0) { hint.textContent = 'Le dernier jour doit suivre le premier.'; return; }
            const option = type.selectedOptions[0];
            const left = option ? option.dataset.left : null;
            hint.textContent = days + ' jour(s) calendaires.' + (left !== undefined && left !== null ? ' Solde restant : ' + left + ' jour(s).' : '') + ' ' + base;
        };
        [start, end, type].forEach(field => field.addEventListener('change', refresh));
        @if($formHasErrors)
            window.addEventListener('load', () => new bootstrap.Modal(modal).show());
        @endif
    }

    // Fenêtre de traitement d'une demande.
    const review = document.getElementById('reviewLeaveModal');
    if (review) {
        const form = document.getElementById('reviewLeaveForm');
        review.addEventListener('show.bs.modal', function (event) {
            const data = event.relatedTarget?.dataset.review ? JSON.parse(event.relatedTarget.dataset.review) : null;
            if (!data) return;
            form.action = data.action;
            form.reset();
            review.querySelectorAll('[data-review-field]').forEach(function (field) {
                const key = field.dataset.reviewField;
                field.textContent = key === 'reason' ? (data.reason ? 'Motif : ' + data.reason : 'Aucun motif indiqué.') : (data[key] ?? '—');
            });
        });
    }
});
</script>
@endsection
