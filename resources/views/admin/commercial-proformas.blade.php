@extends('admin.layout')

@section('content')
@php
    $states = \App\Models\CommercialProforma::states();
    $byState = $proformas->groupBy(fn ($proforma) => $proforma->state());
    $active = $proformas->filter(fn ($proforma) => $proforma->state() !== 'expired');
    $sent = $byState->get('sent', collect());
    $expired = $byState->get('expired', collect());
    $stats = [
        ['Proformas', $proformas->count(), 'bi-file-earmark-richtext', 'purple', 'au total'],
        ['Montant proposé', money((float) $active->sum('total_ttc')), 'bi-cash-stack', 'blue', $active->count() . ' proforma(s) encore valable(s)'],
        ['Envoyées', $sent->count(), 'bi-send-check', 'green', money((float) $sent->sum('total_ttc')) . ' envoyés par e-mail'],
        ['Expirées', $expired->count(), 'bi-calendar-x', 'red', 'date limite dépassée'],
    ];
    $emailProformaId = old('email_proforma_id');
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.commercial')" back-label="Commercial">
        <x-slot:actions>
            <a href="{{ route('admin.commercial.proforma.create') }}" class="dg-btn dg-btn--primary"><i class="bi bi-plus-lg"></i>Créer une proforma</a>
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="dg-kpi-grid">
        @foreach($stats as [$label, $value, $icon, $color, $hint])
            <x-dg.kpi :label="$label" :value="$value" :icon="$icon" :color="$color" :hint="$hint" />
        @endforeach
    </div>

    <form method="POST" action="{{ route('admin.commercial.proforma.bulkDestroy') }}" id="bulkProformaForm">@csrf @method('DELETE')</form>

    <div class="dg-card dg-card--table">
        <div class="dg-card__header flex-wrap">
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-purple"><i class="bi bi-file-earmark-richtext"></i></span>Proformas</h2>
            <div class="d-flex flex-wrap align-items-center gap-2" role="search" aria-label="Filtrer les proformas">
                <label class="dg-search" style="max-width:260px">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input id="proformaSearch" type="search" placeholder="Numéro, client, objet…" aria-label="Rechercher une proforma">
                </label>
                <div class="dg-period">
                    <input id="proformaDateStart" type="date" class="dg-input" aria-label="Du">
                    <span class="dg-period__sep">au</span>
                    <input id="proformaDateEnd" type="date" class="dg-input" aria-label="Au">
                </div>
            </div>
        </div>

        @if($proformas->isNotEmpty())
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                <div class="dg-tabs" role="group" aria-label="Filtrer par état">
                    <button type="button" class="dg-tab is-active" data-state-filter="" aria-pressed="true">Toutes ({{ $proformas->count() }})</button>
                    @foreach($states as $value => [, , , $tabLabel])
                        <button type="button" class="dg-tab" data-state-filter="{{ $value }}" aria-pressed="false">{{ $tabLabel }} ({{ $byState->get($value, collect())->count() }})</button>
                    @endforeach
                </div>
                <div class="proforma-bulk" id="proformaBulk" hidden>
                    <span class="fw-semibold" id="proformaBulkLabel"></span>
                    <button type="button" class="dg-btn dg-btn--outline dg-btn--sm" id="proformaBulkPrint"><i class="bi bi-printer"></i>Imprimer</button>
                    <button type="button" class="dg-btn dg-btn--outline dg-btn--sm dg-btn--danger" id="proformaBulkDelete"><i class="bi bi-trash"></i>Supprimer</button>
                </div>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle mb-0 proformas-table" id="proformasTable">
                <thead>
                    <tr>
                        <th class="proformas-table__check"><input class="form-check-input" type="checkbox" id="selectAllProformas" aria-label="Tout sélectionner"></th>
                        <th>Proforma</th>
                        <th>Client</th>
                        <th>Date limite</th>
                        <th class="text-end">Total TTC</th>
                        <th>État</th>
                        <th class="text-end"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($proformas as $proforma)
                    @php
                        $state = $proforma->state();
                        [$stateLabel, $stateTone, $stateIcon] = $states[$state];
                        $number = $proforma->reference ?: 'N° ' . $proforma->id;
                    @endphp
                    <tr data-state="{{ $state }}" data-date="{{ $proforma->creation_date?->format('Y-m-d') }}">
                        <td class="proformas-table__check">
                            <input class="form-check-input proforma-checkbox" form="bulkProformaForm" type="checkbox" name="proforma_ids[]" value="{{ $proforma->id }}"
                                data-print="{{ route('admin.commercial.proforma.print', $proforma) }}" aria-label="Sélectionner la proforma {{ $number }}">
                        </td>
                        <td class="text-nowrap">
                            <a href="{{ route('admin.commercial.proforma.edit', $proforma) }}" class="d-block fw-semibold text-reset text-decoration-none">{{ $number }}</a>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ $proforma->creation_date?->format('d/m/Y') ?: '—' }} · {{ $proforma->lines_count }} ligne(s)</span>
                        </td>
                        <td>
                            <span class="d-block fw-semibold">{{ $proforma->client_name ?: '—' }}</span>
                            <span class="d-block dg-muted text-truncate" style="font-size:12.5px;max-width:230px">{{ $proforma->subject ?: ($proforma->client_phone ?: '—') }}</span>
                        </td>
                        <td class="text-nowrap {{ $state === 'expired' ? 'dg-amount-negative' : '' }}">{{ $proforma->due_date?->format('d/m/Y') ?: 'Aucune' }}</td>
                        <td class="text-end">
                            <span class="d-block dg-cell-num">{{ money((float) $proforma->total_ttc) }}</span>
                            <span class="d-block dg-muted text-nowrap" style="font-size:12.5px">HT net : {{ money((float) $proforma->net_ht) }}</span>
                        </td>
                        <td><span class="dg-badge dg-badge--{{ $stateTone }}"><i class="bi {{ $stateIcon }}"></i>{{ $stateLabel }}</span></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light action-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions pour la proforma {{ $number }}"></button>
                                <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                                    <li><a class="dropdown-item" href="{{ route('admin.commercial.proforma.edit', $proforma) }}"><i class="bi bi-pencil"></i>Modifier</a></li>
                                    <li><a class="dropdown-item" target="_blank" href="{{ route('admin.commercial.proforma.print', $proforma) }}"><i class="bi bi-printer"></i>Imprimer</a></li>
                                    <li>
                                        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#emailProformaModal"
                                            data-email-proforma="{{ $proforma->id }}"
                                            data-action="{{ route('admin.commercial.proforma.email', $proforma) }}"
                                            data-label="{{ $number }} · {{ $proforma->client_name }}"
                                            data-amount="{{ money((float) $proforma->total_ttc) }}"
                                            data-email="{{ $proforma->client?->email }}"><i class="bi bi-envelope"></i>Envoyer par e-mail</button>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><form method="POST" action="{{ route('admin.commercial.proforma.destroy', $proforma) }}" onsubmit="return confirm('Supprimer cette proforma ?')">@csrf @method('DELETE')<button class="dropdown-item text-danger"><i class="bi bi-trash"></i>Supprimer</button></form></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-5">
                            <div class="dg-chart-empty" style="min-height:220px">
                                <span class="dg-tile dg-tone-purple"><i class="bi bi-file-earmark-richtext"></i></span>
                                <div><strong>Aucune proforma</strong>Une offre chiffrée à remettre au client avant la commande : produits, services, remises et TVA.</div>
                                <a href="{{ route('admin.commercial.proforma.create') }}" class="dg-btn dg-btn--primary dg-btn--sm"><i class="bi bi-plus-lg"></i>Créer une proforma</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Hors du tableau : une ligne fusionnée dans le tbody empêcherait DataTables de s'initialiser. --}}
        <div class="dg-chart-empty mt-4 d-none" id="proformasNoResult" style="min-height:160px">
            <span class="dg-tile dg-tone-purple"><i class="bi bi-search"></i></span>
            <div><strong>Aucune proforma ne correspond à vos filtres</strong>Modifiez la recherche, la période ou l’état.</div>
        </div>
    </div>

    {{-- Envoi par e-mail : l'adresse du client est proposée, et reste modifiable. --}}
    <div class="modal fade" id="emailProformaModal" tabindex="-1" aria-labelledby="emailProformaTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="emailProformaForm" action="{{ $emailProformaId ? route('admin.commercial.proforma.email', $emailProformaId) : '#' }}">
                    @csrf
                    <input type="hidden" name="email_proforma_id" id="emailProformaId" value="{{ $emailProformaId }}">
                    <div class="modal-header">
                        <h5 class="modal-title" id="emailProformaTitle"><i class="bi bi-envelope me-2"></i>Envoyer la proforma</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <div class="proforma-email-summary mb-4">
                            <span class="dg-tile dg-tile--sm dg-tone-purple"><i class="bi bi-file-earmark-richtext"></i></span>
                            <span class="flex-grow-1">
                                <span class="d-block fw-semibold" id="emailProformaLabel"></span>
                                <span class="d-block dg-muted" style="font-size:13px">Montant TTC proposé</span>
                            </span>
                            <strong class="proforma-email-summary__amount" id="emailProformaAmount"></strong>
                        </div>
                        <label class="form-label" for="emailProformaAddress">Adresse du destinataire <span class="text-danger">*</span></label>
                        <input id="emailProformaAddress" type="email" name="email" class="form-control" required maxlength="190" value="{{ $emailProformaId ? old('email') : '' }}" placeholder="client@exemple.ci">
                        <div class="form-text">Le client reçoit le numéro, le montant et la date limite. La proforma passe à l’état « Envoyée ».</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                        <button class="btn btn-primary"><i class="bi bi-send me-1"></i>Envoyer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .proformas-table { min-width: 860px; }
    .proformas-table td:nth-child(3) { min-width: 170px; }
    .dg-scope .proformas-table > thead > tr > th,
    .dg-scope .proformas-table > tbody > tr > td { padding-left: 12px !important; padding-right: 12px !important; }
    .dg-scope .proformas-table > thead > tr > th.proformas-table__check,
    .dg-scope .proformas-table > tbody > tr > td.proformas-table__check { width: 44px; padding-right: 0 !important; }
    .proforma-bulk { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 6px 8px 6px 14px; border-radius: var(--dg-radius); background: rgba(124, 58, 237, .08); font-size: 13.5px; color: #7c3aed; }
    .proforma-bulk[hidden] { display: none; }
    .proforma-email-summary { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: var(--dg-radius); background: rgba(124, 58, 237, .07); border: 1px solid rgba(124, 58, 237, .22); }
    .proforma-email-summary__amount { font-size: 17px; color: #7c3aed; white-space: nowrap; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Filtres : recherche, période et état, appliqués sur les lignes déjà chargées.
    const rows = Array.from(document.querySelectorAll('#proformasTable tbody tr[data-state]'));
    const search = document.getElementById('proformaSearch');
    const dateStart = document.getElementById('proformaDateStart');
    const dateEnd = document.getElementById('proformaDateEnd');
    const tabs = Array.from(document.querySelectorAll('[data-state-filter]'));
    const noResult = document.getElementById('proformasNoResult');
    let state = '';

    const filterProformas = function () {
        const term = search.value.trim().toLowerCase();
        let visible = 0;
        rows.forEach(function (row) {
            const rowDate = row.dataset.date || '';
            const show = (!term || row.textContent.toLowerCase().includes(term))
                && (!state || row.dataset.state === state)
                && (!dateStart.value || !rowDate || rowDate >= dateStart.value)
                && (!dateEnd.value || !rowDate || rowDate <= dateEnd.value);
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
            filterProformas();
        });
    });
    search.addEventListener('input', filterProformas);
    dateStart.addEventListener('change', filterProformas);
    dateEnd.addEventListener('change', filterProformas);

    // Sélection multiple : imprimer ou supprimer plusieurs proformas.
    const all = document.getElementById('selectAllProformas');
    const boxes = Array.from(document.querySelectorAll('.proforma-checkbox'));
    const bulk = document.getElementById('proformaBulk');
    const selected = () => boxes.filter((box) => box.checked);
    const updateSelection = function () {
        const count = selected().length;
        if (bulk) {
            bulk.hidden = count === 0;
            document.getElementById('proformaBulkLabel').textContent = count + ' sélectionnée(s)';
        }
        all.checked = boxes.length > 0 && count === boxes.length;
        all.indeterminate = count > 0 && count < boxes.length;
    };
    all.addEventListener('change', function () { boxes.forEach((box) => { box.checked = all.checked; }); updateSelection(); });
    boxes.forEach((box) => box.addEventListener('change', updateSelection));
    document.getElementById('proformaBulkPrint')?.addEventListener('click', () => selected().forEach((box) => window.open(box.dataset.print, '_blank')));
    document.getElementById('proformaBulkDelete')?.addEventListener('click', function () {
        if (confirm('Supprimer les ' + selected().length + ' proforma(s) sélectionnée(s) ?')) document.getElementById('bulkProformaForm').submit();
    });

    // Fenêtre d'envoi unique, remplie à partir de la ligne choisie.
    const modal = document.getElementById('emailProformaModal');
    const address = document.getElementById('emailProformaAddress');
    modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger || !trigger.dataset.emailProforma) return;
        document.getElementById('emailProformaForm').action = trigger.dataset.action;
        document.getElementById('emailProformaId').value = trigger.dataset.emailProforma;
        document.getElementById('emailProformaLabel').textContent = trigger.dataset.label;
        document.getElementById('emailProformaAmount').textContent = trigger.dataset.amount;
        if (!trigger.dataset.reopen) address.value = trigger.dataset.email || '';
    });
    modal.addEventListener('shown.bs.modal', () => address.focus());
    @if($emailProformaId && $errors->any())
        window.addEventListener('load', function () {
            const trigger = document.querySelector('[data-email-proforma="{{ (int) $emailProformaId }}"]');
            if (!trigger) return;
            trigger.dataset.reopen = '1';
            bootstrap.Modal.getOrCreateInstance(modal).show(trigger);
            delete trigger.dataset.reopen;
        });
    @endif
});
</script>
@endsection
