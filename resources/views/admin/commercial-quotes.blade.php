@extends('admin.layout')

@section('content')
@php
    $today = now()->startOfDay();
    // Un devis en attente dont la date limite est passée est expiré.
    $isExpired = fn ($quote) => $quote->status === 'pending_validation' && $quote->due_date && $quote->due_date->lt($today);
    $stateOf = fn ($quote) => $quote->status === 'validated' ? 'validated' : ($isExpired($quote) ? 'expired' : 'pending');
    $states = [
        'pending' => ['En attente', 'warning', 'bi-hourglass-split', 'En attente'],
        'validated' => ['Validé', 'success', 'bi-check-lg', 'Validés'],
        'expired' => ['Expiré', 'danger', 'bi-calendar-x', 'Expirés'],
    ];
    $byState = $quotes->groupBy($stateOf);
    $pending = $quotes->where('status', 'pending_validation');
    $validated = $byState->get('validated', collect());
    $conversion = $quotes->count() ? round($validated->count() / $quotes->count() * 100) : 0;
    $stats = [
        ['Devis', $quotes->count(), 'bi-file-earmark-text', 'blue', 'au total'],
        ['En attente de validation', money((float) $pending->sum('total_ttc')), 'bi-hourglass-split', 'orange', $pending->count() . ' devis dont ' . $byState->get('expired', collect())->count() . ' expiré(s)'],
        ['Validés', money((float) $validated->sum('total_ttc')), 'bi-check-circle', 'green', $validated->count() . ' devis · ' . $conversion . ' % transformés'],
        ['Expirés', $byState->get('expired', collect())->count(), 'bi-calendar-x', 'red', 'en attente, date limite dépassée'],
    ];
    $validateQuoteId = old('validate_quote_id');
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.commercial')" back-label="Commercial">
        <x-slot:actions>
            <a href="{{ route('admin.commercial.quotes.create') }}" class="dg-btn dg-btn--primary"><i class="bi bi-plus-lg"></i>Créer un devis</a>
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="dg-kpi-grid">
        @foreach($stats as [$label, $value, $icon, $color, $hint])
            <x-dg.kpi :label="$label" :value="$value" :icon="$icon" :color="$color" :hint="$hint" />
        @endforeach
    </div>

    <div class="dg-card dg-card--table">
        <div class="dg-card__header flex-wrap">
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-blue"><i class="bi bi-file-earmark-text"></i></span>Suivi des devis</h2>
            <div class="d-flex flex-wrap align-items-center gap-2" role="search" aria-label="Filtrer les devis">
                <label class="dg-search" style="max-width:260px">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input id="quoteSearch" type="search" placeholder="Référence, client…" aria-label="Rechercher un devis">
                </label>
                <div class="dg-period">
                    <input id="quoteDateStart" type="date" class="dg-input" aria-label="Du">
                    <span class="dg-period__sep">au</span>
                    <input id="quoteDateEnd" type="date" class="dg-input" aria-label="Au">
                </div>
            </div>
        </div>

        @if($quotes->isNotEmpty())
            <div class="dg-tabs mb-4" role="group" aria-label="Filtrer par statut">
                <button type="button" class="dg-tab is-active" data-state-filter="" aria-pressed="true">Tous ({{ $quotes->count() }})</button>
                @foreach($states as $value => [, , , $tabLabel])
                    <button type="button" class="dg-tab" data-state-filter="{{ $value }}" aria-pressed="false">{{ $tabLabel }} ({{ $byState->get($value, collect())->count() }})</button>
                @endforeach
            </div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle mb-0 quotes-table" id="quotesTable">
                <thead>
                    <tr>
                        <th>Devis</th>
                        <th>Client</th>
                        <th>Validité</th>
                        <th class="text-end">Total TTC</th>
                        <th>Statut</th>
                        <th class="text-end"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($quotes as $quote)
                    @php
                        $state = $stateOf($quote);
                        [$stateLabel, $stateTone, $stateIcon] = $states[$state];
                        $isPending = $quote->status === 'pending_validation';
                    @endphp
                    <tr data-state="{{ $state }}" data-date="{{ $quote->quote_date?->format('Y-m-d') }}">
                        <td class="text-nowrap">
                            <span class="d-block fw-semibold">{{ $quote->reference ?: '—' }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ $quote->quote_date?->format('d/m/Y') ?: '—' }}{{ $quote->creator ? ' · ' . $quote->creator->name : '' }}</span>
                        </td>
                        <td>
                            <span class="d-block fw-semibold">{{ $quote->client_name ?: '—' }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ $quote->customer_order_code ? 'BC ' . $quote->customer_order_code : ($quote->subject ?: 'Sans bon de commande') }}</span>
                        </td>
                        <td class="text-nowrap {{ $state === 'expired' ? 'dg-amount-negative' : '' }}">{{ $quote->due_date ? 'jusqu’au ' . $quote->due_date->format('d/m/Y') : '—' }}</td>
                        <td class="text-end dg-cell-num">{{ money((float) $quote->total_ttc) }}</td>
                        <td><span class="dg-badge dg-badge--{{ $stateTone }}"><i class="bi {{ $stateIcon }}"></i>{{ $stateLabel }}</span></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light action-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions pour le devis {{ $quote->reference }}"></button>
                                <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                                    @if($isPending)
                                        <li>
                                            <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#validateQuoteModal"
                                                data-validate-quote="{{ $quote->id }}"
                                                data-action="{{ route('admin.commercial.quotes.validate', $quote) }}"
                                                data-label="{{ $quote->reference }} · {{ $quote->client_name }}"
                                                data-amount="{{ money((float) $quote->total_ttc) }}"><i class="bi bi-check2-circle"></i>Valider le devis</button>
                                        </li>
                                        <li><a class="dropdown-item" href="{{ route('admin.commercial.quotes.edit', $quote) }}"><i class="bi bi-pencil"></i>Modifier</a></li>
                                    @endif
                                    <li><a class="dropdown-item" target="_blank" href="{{ route('admin.commercial.quotes.print', $quote) }}"><i class="bi bi-printer"></i>Imprimer</a></li>
                                    <li><form method="POST" action="{{ route('admin.commercial.quotes.email', $quote) }}">@csrf<button class="dropdown-item"><i class="bi bi-envelope"></i>Envoyer par e-mail</button></form></li>
                                    <li><form method="POST" action="{{ route('admin.commercial.quotes.duplicate', $quote) }}">@csrf<button class="dropdown-item"><i class="bi bi-copy"></i>Dupliquer</button></form></li>
                                    @if($isPending)
                                        <li><hr class="dropdown-divider"></li>
                                        <li><form method="POST" action="{{ route('admin.commercial.quotes.destroy', $quote) }}" onsubmit="return confirm('Supprimer ce devis ?')">@csrf @method('DELETE')<button class="dropdown-item text-danger"><i class="bi bi-trash"></i>Supprimer</button></form></li>
                                    @endif
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-5">
                            <div class="dg-chart-empty" style="min-height:220px">
                                <span class="dg-tile dg-tone-blue"><i class="bi bi-file-earmark-text"></i></span>
                                <div><strong>Aucun devis</strong>Un devis validé avec le bon de commande du client devient une commande à livrer.</div>
                                <a href="{{ route('admin.commercial.quotes.create') }}" class="dg-btn dg-btn--primary dg-btn--sm"><i class="bi bi-plus-lg"></i>Créer un devis</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Hors du tableau : une ligne fusionnée dans le tbody empêcherait DataTables de s'initialiser. --}}
        <div class="dg-chart-empty mt-4 d-none" id="quotesNoResult" style="min-height:160px">
            <span class="dg-tile dg-tone-blue"><i class="bi bi-search"></i></span>
            <div><strong>Aucun devis ne correspond à vos filtres</strong>Modifiez la recherche, la période ou le statut.</div>
        </div>
    </div>

    {{-- Validation : le bon de commande du client transforme le devis en commande à livrer. --}}
    <div class="modal fade" id="validateQuoteModal" tabindex="-1" aria-labelledby="validateQuoteTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="validateQuoteForm" action="{{ $validateQuoteId ? route('admin.commercial.quotes.validate', $validateQuoteId) : '#' }}">
                    @csrf
                    <input type="hidden" name="validate_quote_id" id="validateQuoteId" value="{{ $validateQuoteId }}">
                    <div class="modal-header">
                        <h5 class="modal-title" id="validateQuoteTitle"><i class="bi bi-check2-circle me-2"></i>Valider le devis</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <div class="quote-validate-summary mb-4">
                            <span class="dg-tile dg-tile--sm dg-tone-green"><i class="bi bi-file-earmark-check"></i></span>
                            <span class="flex-grow-1">
                                <span class="d-block fw-semibold" id="validateQuoteLabel"></span>
                                <span class="d-block dg-muted" style="font-size:13px">Le devis devient une commande, avec une livraison à préparer.</span>
                            </span>
                            <strong class="quote-validate-summary__amount" id="validateQuoteAmount"></strong>
                        </div>
                        <label class="form-label" for="customerOrderCode">Bon de commande du client <span class="text-danger">*</span></label>
                        <input id="customerOrderCode" name="customer_order_code" class="form-control" required maxlength="100" value="{{ old('customer_order_code') }}" placeholder="Ex : BC-2026-118">
                        <div class="form-text">Il figurera sur la livraison et sur la facture.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Valider le devis</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .quotes-table { min-width: 860px; }
    .quotes-table td:nth-child(2) { min-width: 180px; }
    .quote-validate-summary { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: var(--dg-radius); background: rgba(5, 150, 105, .08); border: 1px solid rgba(5, 150, 105, .25); }
    .quote-validate-summary__amount { font-size: 17px; color: #059669; white-space: nowrap; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Filtres : recherche, période et statut, appliqués sur les lignes déjà chargées.
    const rows = Array.from(document.querySelectorAll('#quotesTable tbody tr[data-state]'));
    const search = document.getElementById('quoteSearch');
    const dateStart = document.getElementById('quoteDateStart');
    const dateEnd = document.getElementById('quoteDateEnd');
    const tabs = Array.from(document.querySelectorAll('[data-state-filter]'));
    const noResult = document.getElementById('quotesNoResult');
    let state = '';

    const filterQuotes = function () {
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
            filterQuotes();
        });
    });
    search.addEventListener('input', filterQuotes);
    dateStart.addEventListener('change', filterQuotes);
    dateEnd.addEventListener('change', filterQuotes);

    // Fenêtre de validation unique, remplie à partir de la ligne choisie.
    const modal = document.getElementById('validateQuoteModal');
    modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger || !trigger.dataset.validateQuote) return;
        document.getElementById('validateQuoteForm').action = trigger.dataset.action;
        document.getElementById('validateQuoteId').value = trigger.dataset.validateQuote;
        document.getElementById('validateQuoteLabel').textContent = trigger.dataset.label;
        document.getElementById('validateQuoteAmount').textContent = trigger.dataset.amount;
        if (!trigger.dataset.reopen) document.getElementById('customerOrderCode').value = '';
    });

    @if($validateQuoteId && $errors->any())
        window.addEventListener('load', function () {
            const trigger = document.querySelector('[data-validate-quote="{{ (int) $validateQuoteId }}"]');
            if (!trigger) return;
            trigger.dataset.reopen = '1';
            bootstrap.Modal.getOrCreateInstance(modal).show(trigger);
            delete trigger.dataset.reopen;
        });
    @endif
});
</script>
@endsection
