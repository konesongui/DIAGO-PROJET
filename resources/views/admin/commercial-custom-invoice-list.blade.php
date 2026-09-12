@extends('admin.layout')

@section('content')
@php
    // État lisible, déduit des montants et des dates (voir CustomInvoice::state()).
    $dueOf = fn ($invoice) => $invoice->amountDue();
    $remainingOf = fn ($invoice) => $invoice->remainingAmount();
    $stateOf = fn ($invoice) => $invoice->state();
    $states = \App\Models\CustomInvoice::states();
    $byState = $invoices->groupBy($stateOf);
    $issued = $invoices->filter(fn ($invoice) => $invoice->issued_at && $stateOf($invoice) !== 'cancelled');
    $toCollect = $invoices->filter(fn ($invoice) => in_array($stateOf($invoice), ['issued', 'partial'], true));
    $stats = [
        ['Montant facturé', money((float) $issued->sum($dueOf)), 'bi-receipt', 'blue', 'factures émises, net des avoirs'],
        ['Encaissé', money((float) $invoices->sum(fn ($i) => (float) $i->paid_amount)), 'bi-arrow-down-left-circle', 'green', 'paiements reçus'],
        ['Reste à encaisser', money((float) $toCollect->sum($remainingOf)), 'bi-hourglass-split', 'red', $toCollect->count() . ' facture(s) émise(s)'],
        ['Brouillons', $byState->get('draft', collect())->count(), 'bi-pencil-square', 'orange', 'à émettre au client'],
    ];
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.commercial')" back-label="Commercial">
        <x-slot:actions>
            <a href="{{ route('admin.commercial.custom-invoice.create') }}" class="dg-btn dg-btn--primary"><i class="bi bi-plus-lg"></i>Nouvelle facture</a>
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
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-purple"><i class="bi bi-receipt-cutoff"></i></span>Factures personnalisées</h2>
            <div class="d-flex flex-wrap align-items-center gap-2" role="search" aria-label="Filtrer les factures">
                <label class="dg-search" style="max-width:240px">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input id="customInvoiceSearch" type="search" placeholder="Référence…" aria-label="Rechercher une facture">
                </label>
                <div class="dg-period">
                    <input id="customInvoiceDateStart" type="date" class="dg-input" aria-label="Du">
                    <span class="dg-period__sep">au</span>
                    <input id="customInvoiceDateEnd" type="date" class="dg-input" aria-label="Au">
                </div>
            </div>
        </div>

        @if($invoices->isNotEmpty())
            <div class="dg-tabs mb-4" role="group" aria-label="Filtrer par état">
                <button type="button" class="dg-tab is-active" data-state-filter="" aria-pressed="true">Toutes ({{ $invoices->count() }})</button>
                @foreach($states as $value => [, , , $tabLabel])
                    <button type="button" class="dg-tab" data-state-filter="{{ $value }}" aria-pressed="false">{{ $tabLabel }} ({{ $byState->get($value, collect())->count() }})</button>
                @endforeach
            </div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle mb-0 custom-invoices-table" id="customInvoiceTable">
                <thead>
                    <tr>
                        <th>Facture</th>
                        <th>Client</th>
                        <th class="text-end">Montant TTC</th>
                        <th class="text-end">Reste à payer</th>
                        <th>Statut</th>
                        <th class="text-end"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($invoices as $invoice)
                    @php
                        $state = $stateOf($invoice);
                        [$stateLabel, $stateTone, $stateIcon] = $states[$state];
                        $remaining = $remainingOf($invoice);
                        $locked = app(\App\Services\InvoiceIntegrityService::class)->isLocked($invoice);
                        $invoiceDate = $invoice->quote_date ?? $invoice->created_at;
                    @endphp
                    <tr data-state="{{ $state }}" data-date="{{ $invoiceDate?->format('Y-m-d') }}">
                        <td class="text-nowrap">
                            <a href="{{ route('admin.commercial.custom-invoice.show', $invoice) }}" class="d-block fw-semibold text-reset text-decoration-none">{{ $invoice->reference ?: 'N° ' . $invoice->id }}</a>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ $invoiceDate?->format('d/m/Y') ?: '—' }}{{ $invoice->issued_at ? ' · émise le ' . $invoice->issued_at->format('d/m/Y') : '' }}</span>
                        </td>
                        <td>
                            <span class="d-block fw-semibold">{{ $invoice->client_name ?: 'Client' }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ collect([$invoice->client_phone, $invoice->client_email])->filter()->implode(' · ') ?: ($invoice->subject ?: '—') }}</span>
                        </td>
                        <td class="text-end">
                            <span class="d-block dg-cell-num">{{ money((float) $invoice->total_ttc) }}</span>
                            <span class="d-block dg-muted text-nowrap" style="font-size:12.5px">payé : {{ money((float) $invoice->paid_amount) }}</span>
                        </td>
                        @if($state === 'cancelled')
                            <td class="text-end dg-muted">—</td>
                        @else
                            <td class="text-end dg-cell-num {{ $remaining > 0 ? 'dg-amount-negative' : 'dg-amount-positive' }}">{{ money($remaining) }}</td>
                        @endif
                        <td>
                            <span class="dg-badge dg-badge--{{ $stateTone }}"><i class="bi {{ $stateIcon }}"></i>{{ $stateLabel }}</span>
                            @if((float) $invoice->credited_amount > 0 && $state !== 'cancelled')
                                <span class="d-block dg-muted mt-1" style="font-size:12px">avoir : {{ money((float) $invoice->credited_amount) }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light action-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions pour la facture {{ $invoice->reference }}"></button>
                                <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                                    <li><a class="dropdown-item" href="{{ route('admin.commercial.custom-invoice.show', $invoice) }}"><i class="bi bi-eye"></i>Voir la facture</a></li>
                                    @if(! $locked)
                                        <li><a class="dropdown-item" href="{{ route('admin.commercial.custom-invoice.edit', $invoice) }}"><i class="bi bi-pencil"></i>Modifier</a></li>
                                    @endif
                                    @if(! $invoice->issued_at && $state !== 'cancelled')
                                        <li><form method="POST" action="{{ route('admin.commercial.custom-invoice.issue', $invoice) }}" onsubmit="return confirm('Émettre cette facture ? Elle ne pourra plus être modifiée : une correction passera par un avoir.')">@csrf<button class="dropdown-item"><i class="bi bi-send-check"></i>Émettre la facture</button></form></li>
                                    @endif
                                    @if($remaining > 0 && $state !== 'cancelled')
                                        <li>
                                            <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#paymentModal"
                                                data-payment-invoice="{{ $invoice->id }}"
                                                data-action="{{ route('admin.commercial.custom-invoice.payment', $invoice) }}"
                                                data-label="{{ $invoice->reference }} · {{ $invoice->client_name }}"
                                                data-remaining="{{ number_format($remaining, 2, '.', '') }}"
                                                data-remaining-label="{{ money($remaining) }}"><i class="bi bi-cash-coin"></i>Enregistrer un paiement</button>
                                        </li>
                                    @endif
                                    <li><a class="dropdown-item" target="_blank" href="{{ route('admin.commercial.custom-invoice.print', $invoice) }}"><i class="bi bi-printer"></i>Imprimer</a></li>
                                    <li><form method="POST" action="{{ route('admin.commercial.custom-invoice.email', $invoice) }}">@csrf<button class="dropdown-item"><i class="bi bi-envelope"></i>Envoyer par e-mail</button></form></li>
                                    <li><form method="POST" action="{{ route('admin.commercial.custom-invoice.whatsapp', $invoice) }}">@csrf<button class="dropdown-item"><i class="bi bi-whatsapp"></i>Envoyer par WhatsApp</button></form></li>
                                    <li><form method="POST" action="{{ route('admin.commercial.custom-invoice.duplicate', $invoice) }}">@csrf<button class="dropdown-item"><i class="bi bi-copy"></i>Dupliquer</button></form></li>
                                    @if(! $locked)
                                        <li><hr class="dropdown-divider"></li>
                                        <li><form method="POST" action="{{ route('admin.commercial.custom-invoice.destroy', $invoice) }}" onsubmit="return confirm('Supprimer cette facture ?')">@csrf @method('DELETE')<button class="dropdown-item text-danger"><i class="bi bi-trash"></i>Supprimer</button></form></li>
                                    @endif
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-5">
                            <div class="dg-chart-empty" style="min-height:220px">
                                <span class="dg-tile dg-tone-purple"><i class="bi bi-receipt-cutoff"></i></span>
                                <div><strong>Aucune facture personnalisée</strong>Une facture sur mesure, sans devis ni livraison : lignes libres, forfaits du catalogue et paiement.</div>
                                <a href="{{ route('admin.commercial.custom-invoice.create') }}" class="dg-btn dg-btn--primary dg-btn--sm"><i class="bi bi-plus-lg"></i>Nouvelle facture</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Hors du tableau : une ligne fusionnée dans le tbody empêcherait DataTables de s'initialiser. --}}
        <div class="dg-chart-empty mt-4 d-none" id="customInvoicesNoResult" style="min-height:160px">
            <span class="dg-tile dg-tone-purple"><i class="bi bi-search"></i></span>
            <div><strong>Aucune facture ne correspond à vos filtres</strong>Modifiez la recherche, la période ou l’état.</div>
        </div>
    </div>

    @include('admin.partials.custom-invoice-payment-modal')
</div>

<style>
    .custom-invoices-table { min-width: 860px; }
    .custom-invoices-table td:nth-child(2) { min-width: 160px; }
    .dg-scope .custom-invoices-table > thead > tr > th,
    .dg-scope .custom-invoices-table > tbody > tr > td { padding-left: 12px !important; padding-right: 12px !important; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Filtres : recherche, période et état.
    const rows = Array.from(document.querySelectorAll('#customInvoiceTable tbody tr[data-state]'));
    const search = document.getElementById('customInvoiceSearch');
    const dateStart = document.getElementById('customInvoiceDateStart');
    const dateEnd = document.getElementById('customInvoiceDateEnd');
    const tabs = Array.from(document.querySelectorAll('[data-state-filter]'));
    const noResult = document.getElementById('customInvoicesNoResult');
    let state = '';
    const filterInvoices = function () {
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
            filterInvoices();
        });
    });
    search.addEventListener('input', filterInvoices);
    dateStart.addEventListener('change', filterInvoices);
    dateEnd.addEventListener('change', filterInvoices);
});
</script>
@endsection
