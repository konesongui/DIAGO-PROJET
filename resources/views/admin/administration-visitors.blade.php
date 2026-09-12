@extends('admin.layout')

@section('content')
@php
    $states = [
        'expected' => ['Attendue', 'warning', 'bi-hourglass-split', 'Attendues'],
        'inside' => ['Sur place', 'success', 'bi-door-open', 'Sur place'],
        'completed' => ['Terminée', 'neutral', 'bi-check-circle', 'Terminées'],
        'cancelled' => ['Annulée', 'danger', 'bi-x-circle', 'Annulées'],
    ];
    $byState = $visits->groupBy('status');

    // Durée moyenne : uniquement sur les visites achevées, seules à avoir un départ.
    $finished = $visits->where('status', 'completed')->filter(fn ($visit) => $visit->durationMinutes() !== null);
    $averageMinutes = $finished->isNotEmpty() ? (int) round($finished->avg(fn ($visit) => $visit->durationMinutes())) : null;
    $duration = function (?int $minutes) {
        if ($minutes === null) {
            return '—';
        }
        return $minutes >= 60 ? intdiv($minutes, 60) . ' h ' . str_pad($minutes % 60, 2, '0', STR_PAD_LEFT) : $minutes . ' min';
    };

    $stats = [
        ['Sur place', $inside, 'bi-door-open', 'blue', 'visiteurs présents maintenant'],
        ['Attendus', $expected, 'bi-hourglass-split', 'orange', 'visites annoncées, arrivée non pointée'],
        ['Arrivées du jour', $arrivedToday, 'bi-box-arrow-in-right', 'green', 'pointées le ' . now()->format('d/m/Y')],
        ['Durée moyenne', $duration($averageMinutes), 'bi-stopwatch', 'purple', $finished->count() . ' visite(s) achevée(s) sur la période'],
    ];

    // Après une erreur de saisie, la fenêtre se rouvre sur ce qui a été tapé.
    $formHasErrors = $errors->any() && old('_visitor_form');
    $editedId = old('visitor_id');
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.administration')" back-label="Administration">
        <x-slot:actions>
            <button type="button" class="dg-btn dg-btn--primary" data-bs-toggle="modal" data-bs-target="#visitorModal"><i class="bi bi-plus-lg"></i>Nouveau visiteur</button>
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($periodError)<div class="alert alert-warning">{{ $periodError }}</div>@endif
    @if($errors->any() && ! $formHasErrors)<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="dg-card mb-5">
        <form method="GET" action="{{ route('admin.administration.visitors') }}" class="row g-3 align-items-end">
            <div class="col-sm-6 col-lg-3">
                <label class="form-label" for="visitorFrom">Du</label>
                <input id="visitorFrom" type="date" name="from" value="{{ $from->toDateString() }}" class="form-control">
            </div>
            <div class="col-sm-6 col-lg-3">
                <label class="form-label" for="visitorTo">Au</label>
                <input id="visitorTo" type="date" name="to" value="{{ $to->toDateString() }}" class="form-control">
            </div>
            <div class="col-12 col-lg-6 d-flex gap-2">
                <button type="submit" class="dg-btn dg-btn--primary"><i class="bi bi-funnel"></i>Afficher</button>
                <a href="{{ route('admin.administration.visitors') }}" class="dg-btn dg-btn--outline"><i class="bi bi-arrow-counterclockwise"></i>Mois en cours</a>
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
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-blue"><i class="bi bi-person-badge"></i></span>Registre des visites</h2>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="dg-card__meta">{{ $from->format('d/m/Y') }} au {{ $to->format('d/m/Y') }}</span>
                @if($visits->isNotEmpty())
                    <label class="dg-search" style="max-width:240px">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input id="visitorSearch" type="search" placeholder="Nom, société, motif…" aria-label="Rechercher un visiteur">
                    </label>
                @endif
            </div>
        </div>

        @if($visits->isNotEmpty())
            <div class="dg-tabs mb-4" role="group" aria-label="Filtrer par état">
                <button type="button" class="dg-tab is-active" data-state-filter="" aria-pressed="true">Toutes ({{ $visits->count() }})</button>
                @foreach($states as $value => [, , , $tabLabel])
                    @if($byState->get($value, collect())->isNotEmpty())
                        <button type="button" class="dg-tab" data-state-filter="{{ $value }}" aria-pressed="false">{{ $tabLabel }} ({{ $byState->get($value)->count() }})</button>
                    @endif
                @endforeach
            </div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle mb-0 visitors-table no-export" id="visitorsTable">
                <thead>
                    <tr>
                        <th>Visiteur</th>
                        <th>Contact</th>
                        <th>Objet de la visite</th>
                        <th>Arrivée</th>
                        <th>Départ</th>
                        <th>État</th>
                        <th class="text-end"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($visits as $visit)
                    @php [$stateLabel, $stateTone, $stateIcon] = $states[$visit->status] ?? $states['expected']; @endphp
                    <tr data-state="{{ $visit->status }}">
                        <td>
                            <span class="d-block fw-semibold">{{ $visit->name }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ $visit->company ?: 'Sans société' }}</span>
                        </td>
                        <td>
                            <span class="d-block">{{ $visit->phone ?: '—' }}</span>
                            @if($visit->email)<span class="d-block dg-muted text-truncate" style="font-size:12.5px;max-width:140px" title="{{ $visit->email }}">{{ $visit->email }}</span>@endif
                        </td>
                        <td>
                            <span class="d-block" style="max-width:200px">{{ $visit->purpose ?: 'Motif non précisé' }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ $visit->host ? 'reçu par ' . $visit->host : 'Personne visitée non précisée' }}</span>
                        </td>
                        <td class="text-nowrap">
                            @if($visit->check_in_at)
                                <span class="d-block">{{ $visit->check_in_at->format('d/m/Y') }}</span>
                                <span class="d-block dg-muted" style="font-size:12.5px">{{ $visit->check_in_at->format('H:i') }}{{ $visit->check_in_at->isFuture() ? ' (prévue)' : '' }}</span>
                            @else
                                <span class="dg-muted">—</span>
                            @endif
                        </td>
                        <td class="text-nowrap">
                            @if($visit->check_out_at)
                                <span class="d-block">{{ $visit->check_out_at->format('H:i') }}</span>
                                <span class="d-block dg-muted" style="font-size:12.5px">{{ $duration($visit->durationMinutes()) }} sur place</span>
                            @else
                                <span class="dg-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="dg-badge dg-badge--{{ $stateTone }}"><i class="bi {{ $stateIcon }}"></i>{{ $stateLabel }}</span>
                        </td>
                        <td class="text-end">
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                @if($visit->status === 'expected')
                                    <form method="POST" action="{{ route('admin.administration.visitors.presence', $visit) }}">
                                        @csrf<input type="hidden" name="action" value="arrivee">
                                        <button class="dg-btn dg-btn--outline dg-btn--sm"><i class="bi bi-box-arrow-in-right"></i>Arrivée</button>
                                    </form>
                                @elseif($visit->status === 'inside')
                                    <form method="POST" action="{{ route('admin.administration.visitors.presence', $visit) }}">
                                        @csrf<input type="hidden" name="action" value="depart">
                                        <button class="dg-btn dg-btn--primary dg-btn--sm"><i class="bi bi-box-arrow-right"></i>Départ</button>
                                    </form>
                                @endif
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light action-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions pour la visite de {{ $visit->name }}"></button>
                                    <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#visitorModal"
                                            data-visitor="{{ json_encode([
                                                'id' => $visit->id,
                                                'name' => $visit->name,
                                                'company' => $visit->company,
                                                'phone' => $visit->phone,
                                                'email' => $visit->email,
                                                'purpose' => $visit->purpose,
                                                'host' => $visit->host,
                                                'check_in_at' => optional($visit->check_in_at)->format('Y-m-d\TH:i'),
                                                'check_out_at' => optional($visit->check_out_at)->format('Y-m-d\TH:i'),
                                                'notes' => $visit->notes,
                                                'action' => route('admin.administration.visitors.update', $visit),
                                            ]) }}"><i class="bi bi-pencil"></i>Modifier</button></li>
                                        @if(in_array($visit->status, ['expected'], true))
                                            <li>
                                                <form method="POST" action="{{ route('admin.administration.visitors.presence', $visit) }}" onsubmit="return confirm('Annuler la visite de {{ addslashes($visit->name) }} ?')">
                                                    @csrf<input type="hidden" name="action" value="annuler">
                                                    <button class="dropdown-item"><i class="bi bi-x-circle"></i>Annuler la visite</button>
                                                </form>
                                            </li>
                                        @endif
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="{{ route('admin.administration.visitors.destroy', $visit) }}" onsubmit="return confirm('Supprimer du registre la visite de {{ addslashes($visit->name) }} ?')">
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
                                <span class="dg-tile dg-tone-blue"><i class="bi bi-person-badge"></i></span>
                                <div><strong>Aucune visite sur la période</strong>Enregistrez les visiteurs à l’accueil : l’arrivée et le départ se pointent en un clic depuis cette liste.</div>
                                <button type="button" class="dg-btn dg-btn--primary dg-btn--sm" data-bs-toggle="modal" data-bs-target="#visitorModal"><i class="bi bi-plus-lg"></i>Nouveau visiteur</button>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="dg-chart-empty mt-4 d-none" id="visitorsNoResult" style="min-height:140px">
            <span class="dg-tile dg-tone-blue"><i class="bi bi-search"></i></span>
            <div><strong>Aucune visite ne correspond</strong>Modifiez la recherche ou l’onglet.</div>
        </div>
        <p class="dg-muted mt-3 mb-0" style="font-size:12.5px"><i class="bi bi-info-circle me-1"></i>L’état d’une visite suit l’arrivée et le départ : attendue tant que personne n’a pointé, sur place après l’arrivée, terminée au départ. Les visites attendues restent affichées même hors de la période choisie.</p>
    </div>

    {{-- Création et modification d'une visite. --}}
    <div class="modal fade" id="visitorModal" tabindex="-1" aria-labelledby="visitorModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg dg-tone-blue">
            <div class="modal-content">
                <form method="POST" id="visitorForm"
                    action="{{ $editedId ? route('admin.administration.visitors.update', $editedId) : route('admin.administration.visitors.store') }}"
                    data-store-action="{{ route('admin.administration.visitors.store') }}">
                    @csrf
                    <input type="hidden" name="_visitor_form" value="1">
                    <input type="hidden" name="from" value="{{ $from->toDateString() }}">
                    <input type="hidden" name="to" value="{{ $to->toDateString() }}">
                    <input type="hidden" name="_method" value="PUT" id="visitorMethod" @disabled(! $editedId)>
                    <input type="hidden" name="visitor_id" value="{{ $editedId }}" id="visitorId" @disabled(! $editedId)>
                    <div class="modal-header">
                        <h5 class="modal-title" id="visitorModalTitle"><i class="bi bi-person-badge me-2"></i><span>{{ $editedId ? 'Modifier la visite' : 'Nouveau visiteur' }}</span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        @if($formHasErrors)<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="visitorName">Nom du visiteur <span class="text-danger">*</span></label>
                                <input id="visitorName" name="name" class="form-control @error('name') is-invalid @enderror" required maxlength="255" value="{{ old('name') }}" placeholder="Ex. Awa Koné">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="visitorCompany">Société</label>
                                <input id="visitorCompany" name="company" class="form-control @error('company') is-invalid @enderror" maxlength="255" value="{{ old('company') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="visitorPhone">Téléphone</label>
                                <input id="visitorPhone" name="phone" class="form-control @error('phone') is-invalid @enderror" maxlength="50" value="{{ old('phone') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="visitorEmail">E-mail</label>
                                <input id="visitorEmail" name="email" type="email" class="form-control @error('email') is-invalid @enderror" maxlength="255" value="{{ old('email') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="visitorPurpose">Motif de la visite</label>
                                <input id="visitorPurpose" name="purpose" class="form-control @error('purpose') is-invalid @enderror" maxlength="255" value="{{ old('purpose') }}" placeholder="Ex. Remise de dossier">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="visitorHost">Personne visitée</label>
                                <input id="visitorHost" name="host" class="form-control @error('host') is-invalid @enderror" maxlength="255" value="{{ old('host') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="visitorCheckIn">Arrivée</label>
                                <input id="visitorCheckIn" name="check_in_at" type="datetime-local" class="form-control @error('check_in_at') is-invalid @enderror" value="{{ old('check_in_at') }}">
                                <span class="form-text">Laissez vide pour une visite simplement annoncée.</span>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="visitorCheckOut">Départ</label>
                                <input id="visitorCheckOut" name="check_out_at" type="datetime-local" class="form-control @error('check_out_at') is-invalid @enderror" value="{{ old('check_out_at') }}">
                                @error('check_out_at')<span class="dg-field-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="visitorNotes">Notes</label>
                                <textarea id="visitorNotes" name="notes" rows="2" maxlength="2000" class="form-control @error('notes') is-invalid @enderror" placeholder="Pièce d’identité laissée, badge remis…">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                        <p class="form-text mt-3 mb-0">L’état de la visite n’est pas saisi : il découle des heures d’arrivée et de départ.</p>
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
    .visitors-table { min-width: 900px; }
    .dg-scope .visitors-table > thead > tr > th, .dg-scope .visitors-table > tbody > tr > td { padding-left: 10px !important; padding-right: 10px !important; }
    .dg-scope .visitors-table .dropdown-item form { margin: 0; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = Array.from(document.querySelectorAll('#visitorsTable tbody tr[data-state]'));
    const search = document.getElementById('visitorSearch');
    const tabs = Array.from(document.querySelectorAll('[data-state-filter]'));
    const noResult = document.getElementById('visitorsNoResult');
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

    const modal = document.getElementById('visitorModal');
    const form = document.getElementById('visitorForm');
    const method = document.getElementById('visitorMethod');
    const id = document.getElementById('visitorId');
    modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        // Sans déclencheur (réouverture après une erreur), la saisie déjà en place est conservée.
        if (!trigger) return;
        const visitor = trigger.dataset.visitor ? JSON.parse(trigger.dataset.visitor) : null;
        form.action = visitor ? visitor.action : form.dataset.storeAction;
        method.disabled = !visitor;
        id.disabled = !visitor;
        id.value = visitor ? visitor.id : '';
        modal.querySelector('.modal-title span').textContent = visitor ? 'Modifier la visite' : 'Nouveau visiteur';
        ['name', 'company', 'phone', 'email', 'purpose', 'host', 'check_in_at', 'check_out_at', 'notes'].forEach(function (field) {
            form.elements[field].value = visitor && visitor[field] ? visitor[field] : '';
        });
    });
    @if($formHasErrors)
        window.addEventListener('load', () => new bootstrap.Modal(modal).show());
    @endif
});
</script>
@endsection
