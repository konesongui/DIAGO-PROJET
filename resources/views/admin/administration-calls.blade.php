@extends('admin.layout')

@section('content')
@php
    $states = [
        'planned' => ['À passer', 'warning', 'bi-clock-history', 'À passer'],
        'completed' => ['Abouti', 'success', 'bi-check-circle', 'Aboutis'],
        'missed' => ['Manqué', 'danger', 'bi-telephone-x', 'Manqués'],
    ];
    $byState = $calls->groupBy('status');
    $inPeriod = $calls->filter(fn ($call) => $call->call_at && $call->call_at->between($from, $to));
    $minutes = $calls->where('status', 'completed')->sum(fn ($call) => (int) $call->effectiveDuration());
    $duration = function (?int $value) {
        if ($value === null) {
            return '—';
        }
        return $value >= 60 ? intdiv($value, 60) . ' h ' . str_pad($value % 60, 2, '0', STR_PAD_LEFT) : $value . ' min';
    };

    $stats = [
        ['Appels manqués', $missed, 'bi-telephone-x', 'red', 'à rappeler'],
        ['Appels à passer', $planned, 'bi-clock-history', 'orange', $overdue > 0 ? $overdue . ' en retard sur l’heure prévue' : 'aucun retard'],
        ['Appels de la période', $inPeriod->count(), 'bi-telephone', 'blue', $inPeriod->where('direction', 'incoming')->count() . ' entrant(s) · ' . $inPeriod->where('direction', 'outgoing')->count() . ' sortant(s)'],
        ['Temps au téléphone', $duration($minutes ?: null), 'bi-stopwatch', 'purple', 'appels aboutis de la période'],
    ];

    $formHasErrors = $errors->any() && old('_call_form');
    $editedId = old('call_id');
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.administration')" back-label="Administration">
        <x-slot:actions>
            <button type="button" class="dg-btn dg-btn--primary" data-bs-toggle="modal" data-bs-target="#callModal"><i class="bi bi-plus-lg"></i>Enregistrer un appel</button>
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($periodError)<div class="alert alert-warning">{{ $periodError }}</div>@endif
    @if($errors->any() && ! $formHasErrors)<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="dg-card mb-5">
        <form method="GET" action="{{ route('admin.administration.calls') }}" class="row g-3 align-items-end">
            <div class="col-sm-6 col-lg-3">
                <label class="form-label" for="callFrom">Du</label>
                <input id="callFrom" type="date" name="from" value="{{ $from->toDateString() }}" class="form-control">
            </div>
            <div class="col-sm-6 col-lg-3">
                <label class="form-label" for="callTo">Au</label>
                <input id="callTo" type="date" name="to" value="{{ $to->toDateString() }}" class="form-control">
            </div>
            <div class="col-12 col-lg-6 d-flex gap-2">
                <button type="submit" class="dg-btn dg-btn--primary"><i class="bi bi-funnel"></i>Afficher</button>
                <a href="{{ route('admin.administration.calls') }}" class="dg-btn dg-btn--outline"><i class="bi bi-arrow-counterclockwise"></i>Mois en cours</a>
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
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-green"><i class="bi bi-telephone"></i></span>Journal des appels</h2>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="dg-card__meta">{{ $from->format('d/m/Y') }} au {{ $to->format('d/m/Y') }}</span>
                @if($calls->isNotEmpty())
                    <label class="dg-search" style="max-width:240px">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input id="callSearch" type="search" placeholder="Correspondant, objet…" aria-label="Rechercher un appel">
                    </label>
                @endif
            </div>
        </div>

        @if($calls->isNotEmpty())
            <div class="dg-tabs mb-4" role="group" aria-label="Filtrer par état">
                <button type="button" class="dg-tab is-active" data-state-filter="" aria-pressed="true">Tous ({{ $calls->count() }})</button>
                @foreach($states as $value => [, , , $tabLabel])
                    @if($byState->get($value, collect())->isNotEmpty())
                        <button type="button" class="dg-tab" data-state-filter="{{ $value }}" aria-pressed="false">{{ $tabLabel }} ({{ $byState->get($value)->count() }})</button>
                    @endif
                @endforeach
            </div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle mb-0 calls-table no-export" id="callsTable">
                <thead>
                    <tr>
                        <th>Correspondant</th>
                        <th>Sens</th>
                        <th>Objet</th>
                        <th>Date et heure</th>
                        <th class="text-end">Durée</th>
                        <th>État</th>
                        <th class="text-end"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($calls as $call)
                    @php [$stateLabel, $stateTone, $stateIcon] = $states[$call->status] ?? $states['planned']; @endphp
                    <tr data-state="{{ $call->status }}">
                        <td>
                            <span class="d-block fw-semibold">{{ $call->contact_name }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ $call->phone ?: 'Numéro non renseigné' }}</span>
                        </td>
                        <td>
                            <span class="dg-badge dg-badge--neutral"><i class="bi {{ $call->direction === 'incoming' ? 'bi-telephone-inbound' : 'bi-telephone-outbound' }}"></i>{{ $call->directionLabel() }}</span>
                        </td>
                        <td><span class="d-block" style="max-width:210px">{{ $call->subject ?: 'Objet non précisé' }}</span></td>
                        <td class="text-nowrap">
                            @if($call->call_at)
                                <span class="d-block">{{ $call->call_at->format('d/m/Y') }}</span>
                                <span class="d-block dg-muted" style="font-size:12.5px">
                                    {{ $call->call_at->format('H:i') }}@if($call->isOverdue()) · en retard @elseif($call->call_at->isFuture()) · prévu @endif
                                </span>
                            @else
                                <span class="dg-muted">Non renseignée</span>
                            @endif
                        </td>
                        <td class="text-end dg-cell-num">{{ $duration($call->effectiveDuration()) }}</td>
                        <td><span class="dg-badge dg-badge--{{ $stateTone }}"><i class="bi {{ $stateIcon }}"></i>{{ $stateLabel }}</span></td>
                        <td class="text-end">
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                @if($call->status !== 'completed')
                                    <form method="POST" action="{{ route('admin.administration.calls.status', $call) }}">
                                        @csrf<input type="hidden" name="status" value="completed">
                                        <button class="dg-btn dg-btn--primary dg-btn--sm"><i class="bi bi-check2"></i>Abouti</button>
                                    </form>
                                @endif
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light action-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions pour l’appel avec {{ $call->contact_name }}"></button>
                                    <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#callModal"
                                            data-call="{{ json_encode([
                                                'id' => $call->id,
                                                'contact_name' => $call->contact_name,
                                                'phone' => $call->phone,
                                                'subject' => $call->subject,
                                                'direction' => $call->direction,
                                                'call_at' => optional($call->call_at)->format('Y-m-d\TH:i'),
                                                'duration' => $call->duration,
                                                'status' => $call->status,
                                                'notes' => $call->notes,
                                                'action' => route('admin.administration.calls.update', $call),
                                            ]) }}"><i class="bi bi-pencil"></i>Modifier</button></li>
                                        @if($call->status !== 'missed')
                                            <li>
                                                <form method="POST" action="{{ route('admin.administration.calls.status', $call) }}">
                                                    @csrf<input type="hidden" name="status" value="missed">
                                                    <button class="dropdown-item"><i class="bi bi-telephone-x"></i>Noter manqué</button>
                                                </form>
                                            </li>
                                        @endif
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="{{ route('admin.administration.calls.destroy', $call) }}" onsubmit="return confirm('Retirer du journal l’appel avec {{ addslashes($call->contact_name) }} ?')">
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
                                <span class="dg-tile dg-tone-green"><i class="bi bi-telephone"></i></span>
                                <div><strong>Aucun appel sur la période</strong>Consignez les appels reçus et les appels à passer : les manqués et les appels en attente restent affichés jusqu’à leur traitement.</div>
                                <button type="button" class="dg-btn dg-btn--primary dg-btn--sm" data-bs-toggle="modal" data-bs-target="#callModal"><i class="bi bi-plus-lg"></i>Enregistrer un appel</button>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="dg-chart-empty mt-4 d-none" id="callsNoResult" style="min-height:140px">
            <span class="dg-tile dg-tone-green"><i class="bi bi-search"></i></span>
            <div><strong>Aucun appel ne correspond</strong>Modifiez la recherche ou l’onglet.</div>
        </div>
        <p class="dg-muted mt-3 mb-0" style="font-size:12.5px"><i class="bi bi-info-circle me-1"></i>Seul un appel abouti porte une durée. Les appels manqués et les appels à passer restent affichés même hors de la période choisie, tant qu’ils n’ont pas été traités.</p>
    </div>

    {{-- Enregistrement et modification d'un appel. --}}
    <div class="modal fade" id="callModal" tabindex="-1" aria-labelledby="callModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg dg-tone-green">
            <div class="modal-content">
                <form method="POST" id="callForm"
                    action="{{ $editedId ? route('admin.administration.calls.update', $editedId) : route('admin.administration.calls.store') }}"
                    data-store-action="{{ route('admin.administration.calls.store') }}">
                    @csrf
                    <input type="hidden" name="_call_form" value="1">
                    <input type="hidden" name="from" value="{{ $from->toDateString() }}">
                    <input type="hidden" name="to" value="{{ $to->toDateString() }}">
                    <input type="hidden" name="_method" value="PUT" id="callMethod" @disabled(! $editedId)>
                    <input type="hidden" name="call_id" value="{{ $editedId }}" id="callId" @disabled(! $editedId)>
                    <div class="modal-header">
                        <h5 class="modal-title" id="callModalTitle"><i class="bi bi-telephone me-2"></i><span>{{ $editedId ? 'Modifier l’appel' : 'Enregistrer un appel' }}</span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        @if($formHasErrors)<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="callContact">Correspondant <span class="text-danger">*</span></label>
                                <input id="callContact" name="contact_name" class="form-control @error('contact_name') is-invalid @enderror" required maxlength="255" value="{{ old('contact_name') }}" placeholder="Personne ou société">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="callPhone">Téléphone</label>
                                <input id="callPhone" name="phone" class="form-control @error('phone') is-invalid @enderror" maxlength="50" value="{{ old('phone') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="callDirection">Sens <span class="text-danger">*</span></label>
                                <select id="callDirection" name="direction" class="form-select @error('direction') is-invalid @enderror" required>
                                    @foreach(\App\Models\AdminCall::DIRECTIONS as $value => $label)
                                        <option value="{{ $value }}" @selected(old('direction', 'incoming') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="callAt">Date et heure <span class="text-danger">*</span></label>
                                <input id="callAt" name="call_at" type="datetime-local" class="form-control @error('call_at') is-invalid @enderror" required value="{{ old('call_at', now()->format('Y-m-d\TH:i')) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="callSubject">Objet</label>
                                <input id="callSubject" name="subject" class="form-control @error('subject') is-invalid @enderror" maxlength="255" value="{{ old('subject') }}" placeholder="Ex. Relance de la facture FA-2026-014">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="callStatus">État <span class="text-danger">*</span></label>
                                <select id="callStatus" name="status" class="form-select @error('status') is-invalid @enderror" required>
                                    @foreach(\App\Models\AdminCall::STATES as $value => $label)
                                        <option value="{{ $value }}" @selected(old('status', 'completed') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6" id="callDurationRow">
                                <label class="form-label" for="callDuration">Durée (minutes)</label>
                                <input id="callDuration" name="duration" type="number" min="0" max="1440" class="form-control @error('duration') is-invalid @enderror" value="{{ old('duration') }}">
                                <span class="form-text">Seul un appel abouti porte une durée.</span>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="callNotes">Notes</label>
                                <textarea id="callNotes" name="notes" rows="2" maxlength="2000" class="form-control @error('notes') is-invalid @enderror" placeholder="Suite à donner, message laissé…">{{ old('notes') }}</textarea>
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
    .calls-table { min-width: 900px; }
    .dg-scope .calls-table > thead > tr > th, .dg-scope .calls-table > tbody > tr > td { padding-left: 10px !important; padding-right: 10px !important; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = Array.from(document.querySelectorAll('#callsTable tbody tr[data-state]'));
    const search = document.getElementById('callSearch');
    const tabs = Array.from(document.querySelectorAll('[data-state-filter]'));
    const noResult = document.getElementById('callsNoResult');
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

    const modal = document.getElementById('callModal');
    const form = document.getElementById('callForm');
    const method = document.getElementById('callMethod');
    const id = document.getElementById('callId');
    const durationRow = document.getElementById('callDurationRow');
    const status = document.getElementById('callStatus');
    // La durée ne concerne que les appels aboutis.
    const toggleDuration = function () {
        durationRow.classList.toggle('d-none', status.value !== 'completed');
    };
    status.addEventListener('change', toggleDuration);
    toggleDuration();

    modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) return;
        const call = trigger.dataset.call ? JSON.parse(trigger.dataset.call) : null;
        form.action = call ? call.action : form.dataset.storeAction;
        method.disabled = !call;
        id.disabled = !call;
        id.value = call ? call.id : '';
        modal.querySelector('.modal-title span').textContent = call ? 'Modifier l’appel' : 'Enregistrer un appel';
        form.elements.contact_name.value = call ? call.contact_name : '';
        form.elements.phone.value = call && call.phone ? call.phone : '';
        form.elements.subject.value = call && call.subject ? call.subject : '';
        form.elements.direction.value = call ? call.direction : 'incoming';
        form.elements.call_at.value = call && call.call_at ? call.call_at : '{{ now()->format('Y-m-d\TH:i') }}';
        form.elements.duration.value = call && call.duration !== null ? call.duration : '';
        form.elements.status.value = call ? call.status : 'completed';
        form.elements.notes.value = call && call.notes ? call.notes : '';
        toggleDuration();
    });
    @if($formHasErrors)
        window.addEventListener('load', () => new bootstrap.Modal(modal).show());
    @endif
});
</script>
@endsection
