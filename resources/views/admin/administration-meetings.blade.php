@extends('admin.layout')

@section('content')
@php
    $states = [
        'planned' => ['Prévue', 'warning', 'bi-calendar-event', 'Prévues'],
        'held' => ['Tenue', 'success', 'bi-check-circle', 'Tenues'],
        'cancelled' => ['Annulée', 'danger', 'bi-x-circle', 'Annulées'],
    ];
    $byState = $meetings->groupBy('status');
    $heldInPeriod = $meetings->where('status', 'held')->filter(fn ($meeting) => $meeting->starts_at && $meeting->starts_at->between($from, $to));
    $totalMinutes = $heldInPeriod->sum(fn ($meeting) => (int) $meeting->durationMinutes());
    $duration = function (?int $value) {
        if (! $value) {
            return '—';
        }
        return $value >= 60 ? intdiv($value, 60) . ' h ' . str_pad($value % 60, 2, '0', STR_PAD_LEFT) : $value . ' min';
    };

    $stats = [
        ['Réunions à venir', $upcoming, 'bi-calendar-event', 'blue', 'convocations encore à honorer'],
        ['Tenues sur la période', $heldInPeriod->count(), 'bi-check-circle', 'green', 'réunions effectivement tenues'],
        ['Comptes-rendus manquants', $missingMinutes, 'bi-journal-x', 'orange', 'réunions tenues sans compte-rendu'],
        ['Temps en réunion', $duration($totalMinutes), 'bi-stopwatch', 'purple', 'sur les réunions tenues de la période'],
    ];

    $formHasErrors = $errors->any() && old('_meeting_form');
    $editedId = old('meeting_id');
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.administration')" back-label="Administration">
        <x-slot:actions>
            <button type="button" class="dg-btn dg-btn--primary" data-bs-toggle="modal" data-bs-target="#meetingModal"><i class="bi bi-plus-lg"></i>Planifier une réunion</button>
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($periodError)<div class="alert alert-warning">{{ $periodError }}</div>@endif
    @if($errors->any() && ! $formHasErrors)<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="dg-card mb-5">
        <form method="GET" action="{{ route('admin.administration.meetings') }}" class="row g-3 align-items-end">
            <div class="col-sm-6 col-lg-3">
                <label class="form-label" for="meetingFrom">Du</label>
                <input id="meetingFrom" type="date" name="from" value="{{ $from->toDateString() }}" class="form-control">
            </div>
            <div class="col-sm-6 col-lg-3">
                <label class="form-label" for="meetingTo">Au</label>
                <input id="meetingTo" type="date" name="to" value="{{ $to->toDateString() }}" class="form-control">
            </div>
            <div class="col-12 col-lg-6 d-flex gap-2">
                <button type="submit" class="dg-btn dg-btn--primary"><i class="bi bi-funnel"></i>Afficher</button>
                <a href="{{ route('admin.administration.meetings') }}" class="dg-btn dg-btn--outline"><i class="bi bi-arrow-counterclockwise"></i>Mois en cours</a>
            </div>
        </form>
    </div>

    <div class="dg-kpi-grid">
        @foreach($stats as [$label, $value, $icon, $color, $hint])
            <x-dg.kpi :label="$label" :value="$value" :icon="$icon" :color="$color" :hint="$hint" />
        @endforeach
    </div>

    <div class="dg-card dg-card--table">
        <div class="dg-card__header flex-wrap">
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-orange"><i class="bi bi-calendar-event"></i></span>Réunions</h2>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="dg-card__meta">{{ $from->format('d/m/Y') }} au {{ $to->format('d/m/Y') }}</span>
                @if($meetings->isNotEmpty())
                    <label class="dg-search" style="max-width:240px">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input id="meetingSearch" type="search" placeholder="Intitulé, lieu, organisateur…" aria-label="Rechercher une réunion">
                    </label>
                @endif
            </div>
        </div>

        @if($meetings->isNotEmpty())
            <div class="dg-tabs mb-4" role="group" aria-label="Filtrer par état">
                <button type="button" class="dg-tab is-active" data-state-filter="" aria-pressed="true">Toutes ({{ $meetings->count() }})</button>
                @foreach($states as $value => [, , , $tabLabel])
                    @if($byState->get($value, collect())->isNotEmpty())
                        <button type="button" class="dg-tab" data-state-filter="{{ $value }}" aria-pressed="false">{{ $tabLabel }} ({{ $byState->get($value)->count() }})</button>
                    @endif
                @endforeach
            </div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle mb-0 meetings-table no-export" id="meetingsTable">
                <thead>
                    <tr>
                        <th>Réunion</th>
                        <th>Date et horaire</th>
                        <th class="text-end">Durée</th>
                        <th>Organisation</th>
                        <th>Compte-rendu</th>
                        <th>État</th>
                        <th class="text-end"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($meetings as $meeting)
                    @php
                        [$stateLabel, $stateTone, $stateIcon] = $states[$meeting->status] ?? $states['planned'];
                        $attendees = $meeting->attendeesList();
                        $minutesPayload = json_encode([
                            'action' => route('admin.administration.meetings.minutes', $meeting),
                            'title' => $meeting->title,
                            'when' => $meeting->starts_at?->format('d/m/Y à H:i') ?? 'Date non renseignée',
                            'attendees' => $attendees ? implode(', ', $attendees) : 'Participants non renseignés',
                            'minutes' => $meeting->minutes,
                        ]);
                    @endphp
                    <tr data-state="{{ $meeting->status }}">
                        <td>
                            <span class="d-block fw-semibold" style="max-width:220px">{{ $meeting->title }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ $meeting->location ?: 'Lieu non précisé' }}</span>
                        </td>
                        <td class="text-nowrap">
                            @if($meeting->starts_at)
                                <span class="d-block">{{ $meeting->starts_at->format('d/m/Y') }}</span>
                                <span class="d-block dg-muted" style="font-size:12.5px">
                                    {{ $meeting->starts_at->format('H:i') }}@if($meeting->ends_at) → {{ $meeting->ends_at->format('H:i') }}@endif{{ $meeting->isOverdue() ? ' · passée' : '' }}
                                </span>
                            @else
                                <span class="dg-muted">Non renseignée</span>
                            @endif
                        </td>
                        <td class="text-end dg-cell-num">{{ $duration($meeting->durationMinutes()) }}</td>
                        <td>
                            <span class="d-block">{{ $meeting->organizer ?: 'Organisateur non précisé' }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ count($attendees) > 0 ? count($attendees) . ' participant(s)' : 'Participants non renseignés' }}</span>
                        </td>
                        <td>
                            @if($meeting->hasMinutes())
                                <button type="button" class="dg-link-btn" data-bs-toggle="modal" data-bs-target="#minutesModal" data-minutes="{{ $minutesPayload }}"><i class="bi bi-journal-text"></i>Lire</button>
                            @elseif($meeting->needsMinutes())
                                <span class="dg-badge dg-badge--warning"><i class="bi bi-journal-x"></i>Manquant</span>
                            @else
                                <span class="dg-muted">—</span>
                            @endif
                        </td>
                        <td><span class="dg-badge dg-badge--{{ $stateTone }}"><i class="bi {{ $stateIcon }}"></i>{{ $stateLabel }}</span></td>
                        <td class="text-end">
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                @if($meeting->status === 'planned')
                                    <form method="POST" action="{{ route('admin.administration.meetings.status', $meeting) }}">
                                        @csrf<input type="hidden" name="status" value="held">
                                        <button class="dg-btn dg-btn--primary dg-btn--sm"><i class="bi bi-check2"></i>Tenue</button>
                                    </form>
                                @elseif($meeting->needsMinutes())
                                    <button type="button" class="dg-btn dg-btn--primary dg-btn--sm" data-bs-toggle="modal" data-bs-target="#minutesModal" data-minutes="{{ $minutesPayload }}"><i class="bi bi-journal-text"></i>Rédiger</button>
                                @endif
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light action-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions pour la réunion {{ $meeting->title }}"></button>
                                    <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#meetingModal"
                                            data-meeting="{{ json_encode([
                                                'id' => $meeting->id,
                                                'title' => $meeting->title,
                                                'location' => $meeting->location,
                                                'starts_at' => optional($meeting->starts_at)->format('Y-m-d\TH:i'),
                                                'ends_at' => optional($meeting->ends_at)->format('Y-m-d\TH:i'),
                                                'organizer' => $meeting->organizer,
                                                'attendees' => $meeting->attendees,
                                                'status' => $meeting->status,
                                                'action' => route('admin.administration.meetings.update', $meeting),
                                            ]) }}"><i class="bi bi-pencil"></i>Modifier</button></li>
                                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#minutesModal" data-minutes="{{ $minutesPayload }}"><i class="bi bi-journal-text"></i>Compte-rendu</button></li>
                                        @if($meeting->status !== 'cancelled')
                                            <li>
                                                <form method="POST" action="{{ route('admin.administration.meetings.status', $meeting) }}" onsubmit="return confirm('Annuler la réunion « {{ addslashes($meeting->title) }} » ?')">
                                                    @csrf<input type="hidden" name="status" value="cancelled">
                                                    <button class="dropdown-item"><i class="bi bi-x-circle"></i>Annuler la réunion</button>
                                                </form>
                                            </li>
                                        @endif
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="{{ route('admin.administration.meetings.destroy', $meeting) }}" onsubmit="return confirm('Supprimer la réunion « {{ addslashes($meeting->title) }} » et son compte-rendu ?')">
                                                @csrf @method('DELETE')
                                                <button class="dropdown-item text-danger"><i class="bi bi-trash"></i>Supprimer</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-5">
                            <div class="dg-chart-empty" style="min-height:200px">
                                <span class="dg-tile dg-tone-orange"><i class="bi bi-calendar-event"></i></span>
                                <div><strong>Aucune réunion sur la période</strong>Planifiez vos réunions et consignez-en le compte-rendu : c’est lui qui garde la trace des décisions.</div>
                                <button type="button" class="dg-btn dg-btn--primary dg-btn--sm" data-bs-toggle="modal" data-bs-target="#meetingModal"><i class="bi bi-plus-lg"></i>Planifier une réunion</button>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="dg-chart-empty mt-4 d-none" id="meetingsNoResult" style="min-height:140px">
            <span class="dg-tile dg-tone-orange"><i class="bi bi-search"></i></span>
            <div><strong>Aucune réunion ne correspond</strong>Modifiez la recherche ou l’onglet.</div>
        </div>
        <p class="dg-muted mt-3 mb-0" style="font-size:12.5px"><i class="bi bi-info-circle me-1"></i>Rédiger le compte-rendu d’une réunion encore annoncée la note automatiquement « tenue ». Les réunions prévues et celles dont le compte-rendu manque restent affichées même hors de la période choisie.</p>
    </div>

    {{-- Planification et modification. --}}
    <div class="modal fade" id="meetingModal" tabindex="-1" aria-labelledby="meetingModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg dg-tone-orange">
            <div class="modal-content">
                <form method="POST" id="meetingForm"
                    action="{{ $editedId ? route('admin.administration.meetings.update', $editedId) : route('admin.administration.meetings.store') }}"
                    data-store-action="{{ route('admin.administration.meetings.store') }}">
                    @csrf
                    <input type="hidden" name="_meeting_form" value="1">
                    <input type="hidden" name="from" value="{{ $from->toDateString() }}">
                    <input type="hidden" name="to" value="{{ $to->toDateString() }}">
                    <input type="hidden" name="_method" value="PUT" id="meetingMethod" @disabled(! $editedId)>
                    <input type="hidden" name="meeting_id" value="{{ $editedId }}" id="meetingId" @disabled(! $editedId)>
                    <div class="modal-header">
                        <h5 class="modal-title" id="meetingModalTitle"><i class="bi bi-calendar-event me-2"></i><span>{{ $editedId ? 'Modifier la réunion' : 'Planifier une réunion' }}</span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        @if($formHasErrors)<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label" for="meetingTitle">Intitulé <span class="text-danger">*</span></label>
                                <input id="meetingTitle" name="title" class="form-control @error('title') is-invalid @enderror" required maxlength="255" value="{{ old('title') }}" placeholder="Ex. Comité de direction">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="meetingLocation">Lieu</label>
                                <input id="meetingLocation" name="location" class="form-control @error('location') is-invalid @enderror" maxlength="255" value="{{ old('location') }}" placeholder="Salle de réunion, visioconférence…">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="meetingStart">Début <span class="text-danger">*</span></label>
                                <input id="meetingStart" name="starts_at" type="datetime-local" class="form-control @error('starts_at') is-invalid @enderror" required value="{{ old('starts_at') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="meetingEnd">Fin <span class="text-danger">*</span></label>
                                <input id="meetingEnd" name="ends_at" type="datetime-local" class="form-control @error('ends_at') is-invalid @enderror" required value="{{ old('ends_at') }}">
                                @error('ends_at')<span class="dg-field-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="meetingOrganizer">Organisateur</label>
                                <input id="meetingOrganizer" name="organizer" class="form-control @error('organizer') is-invalid @enderror" maxlength="255" value="{{ old('organizer') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="meetingStatus">État <span class="text-danger">*</span></label>
                                <select id="meetingStatus" name="status" class="form-select @error('status') is-invalid @enderror" required>
                                    @foreach(\App\Models\AdminMeeting::STATES as $value => $label)
                                        <option value="{{ $value }}" @selected(old('status', 'planned') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="meetingAttendees">Participants</label>
                                <textarea id="meetingAttendees" name="attendees" rows="3" maxlength="2000" class="form-control @error('attendees') is-invalid @enderror" placeholder="Un participant par ligne">{{ old('attendees') }}</textarea>
                                <span class="form-text">Un participant par ligne, ou séparés par des virgules.</span>
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

    {{-- Compte-rendu, lu et rédigé à part de la convocation. --}}
    <div class="modal fade" id="minutesModal" tabindex="-1" aria-labelledby="minutesModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg dg-tone-orange">
            <div class="modal-content">
                <form method="POST" id="minutesForm" action="#">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="minutesModalTitle"><i class="bi bi-journal-text me-2"></i>Compte-rendu</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <div class="minutes-head mb-3">
                            <div><span>Réunion</span><strong data-minutes-field="title"></strong></div>
                            <div><span>Date</span><strong data-minutes-field="when"></strong></div>
                            <div class="minutes-head__wide"><span>Participants</span><strong data-minutes-field="attendees"></strong></div>
                        </div>
                        <label class="form-label" for="minutesText">Décisions et suites données</label>
                        <textarea id="minutesText" name="minutes" rows="10" maxlength="20000" class="form-control" placeholder="Points abordés, décisions prises, actions à mener…"></textarea>
                        <span class="form-text">Rédiger un compte-rendu sur une réunion encore annoncée la note « tenue ».</span>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer le compte-rendu</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .meetings-table { min-width: 920px; }
    .dg-scope .meetings-table > thead > tr > th, .dg-scope .meetings-table > tbody > tr > td { padding-left: 10px !important; padding-right: 10px !important; }
    .minutes-head { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .minutes-head > div { padding: 10px 12px; border-radius: var(--dg-radius); background: var(--dg-table-head); }
    .minutes-head__wide { grid-column: 1 / -1; }
    .minutes-head span { display: block; font-size: 12.5px; color: var(--dg-muted); }
    .minutes-head strong { font-size: 14px; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = Array.from(document.querySelectorAll('#meetingsTable tbody tr[data-state]'));
    const search = document.getElementById('meetingSearch');
    const tabs = Array.from(document.querySelectorAll('[data-state-filter]'));
    const noResult = document.getElementById('meetingsNoResult');
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

    const modal = document.getElementById('meetingModal');
    const form = document.getElementById('meetingForm');
    const method = document.getElementById('meetingMethod');
    const id = document.getElementById('meetingId');
    modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) return;
        const meeting = trigger.dataset.meeting ? JSON.parse(trigger.dataset.meeting) : null;
        form.action = meeting ? meeting.action : form.dataset.storeAction;
        method.disabled = !meeting;
        id.disabled = !meeting;
        id.value = meeting ? meeting.id : '';
        modal.querySelector('.modal-title span').textContent = meeting ? 'Modifier la réunion' : 'Planifier une réunion';
        ['title', 'location', 'starts_at', 'ends_at', 'organizer', 'attendees'].forEach(function (field) {
            form.elements[field].value = meeting && meeting[field] ? meeting[field] : '';
        });
        form.elements.status.value = meeting ? meeting.status : 'planned';
    });

    const minutes = document.getElementById('minutesModal');
    const minutesForm = document.getElementById('minutesForm');
    minutes.addEventListener('show.bs.modal', function (event) {
        const data = event.relatedTarget?.dataset.minutes ? JSON.parse(event.relatedTarget.dataset.minutes) : null;
        if (!data) return;
        minutesForm.action = data.action;
        minutes.querySelectorAll('[data-minutes-field]').forEach(function (field) {
            field.textContent = data[field.dataset.minutesField] ?? '—';
        });
        minutesForm.elements.minutes.value = data.minutes ?? '';
    });
    @if($formHasErrors)
        window.addEventListener('load', () => new bootstrap.Modal(modal).show());
    @endif
});
</script>
@endsection
