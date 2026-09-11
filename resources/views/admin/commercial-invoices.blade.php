@extends('admin.layout')

@section('content')
@php
    // Statut : libellé, ton du badge, icône, libellé de l'onglet.
    $statuses = [
        'paid' => ['Payée', 'success', 'bi-check-lg', 'Payées'],
        'partially_paid' => ['Partiellement payée', 'warning', 'bi-hourglass-split', 'Partielles'],
        'unpaid' => ['Impayée', 'danger', 'bi-exclamation-circle', 'Impayées'],
        'cancelled' => ['Annulée', 'neutral', 'bi-x-circle', 'Annulées'],
    ];
    $remainingOf = fn ($invoice) => max(0, (float) $invoice->amount - (float) $invoice->paid_amount);
    $active = $invoices->where('status', '!=', 'cancelled');
    $toCollect = $invoices->whereIn('status', ['unpaid', 'partially_paid']);
    $stats = [
        ['Montant facturé', money((float) $active->sum('amount')), 'bi-receipt', 'blue', 'TTC, hors factures annulées'],
        ['Encaissé', money((float) $active->sum('paid_amount')), 'bi-arrow-down-left-circle', 'green', 'paiements reçus'],
        ['Reste à encaisser', money((float) $toCollect->sum($remainingOf)), 'bi-hourglass-split', 'red', $toCollect->count() . ' facture(s) à encaisser'],
        ['Certifiées FNE', $invoices->where('fne_status', 'certified')->count(), 'bi-patch-check', 'teal', 'sur ' . $invoices->count() . ' facture(s)'],
    ];
    $paymentInvoiceId = old('payment_invoice_id');
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.commercial')" back-label="Commercial">
        <x-slot:actions>
            <a href="{{ route('admin.commercial.module', 'livraisons') }}" class="dg-btn dg-btn--outline"><i class="bi bi-truck"></i>Voir les livraisons</a>
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
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-cyan"><i class="bi bi-receipt"></i></span>Factures à encaisser</h2>
            <div class="d-flex flex-wrap align-items-center gap-2" role="search" aria-label="Filtrer les factures">
                <label class="dg-search" style="max-width:260px">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input id="invoiceSearch" type="search" placeholder="Client, n°, bon de commande…" aria-label="Rechercher une facture">
                </label>
                <div class="dg-period">
                    <input id="invoiceDateStart" type="date" class="dg-input" aria-label="Du">
                    <span class="dg-period__sep">au</span>
                    <input id="invoiceDateEnd" type="date" class="dg-input" aria-label="Au">
                </div>
            </div>
        </div>

        @if($invoices->isNotEmpty())
            <div class="dg-tabs mb-4" role="group" aria-label="Filtrer par statut">
                <button type="button" class="dg-tab is-active" data-status-filter="" aria-pressed="true">Toutes ({{ $invoices->count() }})</button>
                @foreach($statuses as $value => [, , , $tabLabel])
                    <button type="button" class="dg-tab" data-status-filter="{{ $value }}" aria-pressed="false">{{ $tabLabel }} ({{ $invoices->where('status', $value)->count() }})</button>
                @endforeach
            </div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle mb-0 sales-invoices-table" id="invoicesTable">
                <thead>
                    <tr>
                        <th>Facture</th>
                        <th>Client</th>
                        <th class="text-end">Montant TTC</th>
                        <th class="text-end">Reste à payer</th>
                        <th>Statut</th>
                        <th>FNE</th>
                        <th class="text-end"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($invoices as $invoice)
                    @php
                        $invoiceDate = $invoice->issued_at ?? $invoice->created_at;
                        $remaining = $remainingOf($invoice);
                        [$statusLabel, $statusTone, $statusIcon] = $statuses[$invoice->status] ?? [$invoice->status, 'neutral', 'bi-circle'];
                        $orderCode = $invoice->delivery?->order?->customer_order_code;
                    @endphp
                    <tr data-status="{{ $invoice->status }}" data-date="{{ $invoiceDate?->format('Y-m-d') }}">
                        <td class="text-nowrap">
                            <span class="d-block fw-semibold">N° {{ $invoice->id }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ $invoiceDate?->format('d/m/Y') ?: '—' }}</span>
                        </td>
                        <td>
                            <span class="d-block fw-semibold">{{ $invoice->client_name }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ $orderCode ? 'BC ' . $orderCode : 'Sans bon de commande client' }}</span>
                        </td>
                        <td class="text-end">
                            <span class="d-block dg-cell-num">{{ money((float) $invoice->amount) }}</span>
                            <span class="d-block dg-muted text-nowrap" style="font-size:12.5px">payé : {{ money((float) $invoice->paid_amount) }}</span>
                        </td>
                        @if($invoice->status === 'cancelled')
                            <td class="text-end dg-muted">—</td>
                        @else
                            <td class="text-end dg-cell-num {{ $remaining > 0 ? 'dg-amount-negative' : 'dg-amount-positive' }}">{{ money($remaining) }}</td>
                        @endif
                        <td><span class="dg-badge dg-badge--{{ $statusTone }}"><i class="bi {{ $statusIcon }}"></i>{{ $statusLabel }}</span></td>
                        <td>
                            @if($invoice->fne_status === 'certified')
                                <span class="dg-badge dg-badge--success"><i class="bi bi-patch-check"></i>Certifiée</span>
                            @elseif($invoice->fne_status === 'failed')
                                <span class="dg-badge dg-badge--danger" title="{{ $invoice->fne_error }}"><i class="bi bi-exclamation-triangle"></i>Échec</span>
                            @else
                                <span class="dg-badge dg-badge--neutral">Non certifiée</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light action-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions pour la facture N° {{ $invoice->id }}"></button>
                                <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                                    @if($invoice->status !== 'paid' && $invoice->status !== 'cancelled')
                                        <li>
                                            <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#paymentModal"
                                                data-payment-invoice="{{ $invoice->id }}"
                                                data-action="{{ route('admin.commercial.invoices.payment', $invoice) }}"
                                                data-label="Facture N° {{ $invoice->id }} · {{ $invoice->client_name }}"
                                                data-remaining="{{ number_format($remaining, 2, '.', '') }}"
                                                data-remaining-label="{{ money($remaining) }}"><i class="bi bi-cash-coin"></i>Enregistrer un paiement</button>
                                        </li>
                                    @endif
                                    <li><a class="dropdown-item" target="_blank" href="{{ route('admin.commercial.invoices.print', $invoice) }}"><i class="bi bi-printer"></i>Imprimer</a></li>
                                    @if($invoice->fne_status === 'certified')
                                        <li><a class="dropdown-item" target="_blank" href="{{ route('admin.commercial.invoices.printFne', $invoice) }}"><i class="bi bi-patch-check"></i>Impression FNE</a></li>
                                    @else
                                        <li><form method="POST" action="{{ route('admin.commercial.invoices.fne', $invoice) }}" onsubmit="return confirm('Envoyer cette facture à la FNE ?')">@csrf<button class="dropdown-item"><i class="bi bi-patch-check"></i>Certifier FNE</button></form></li>
                                    @endif
                                    <li><form method="POST" action="{{ route('admin.commercial.invoices.email', $invoice) }}">@csrf<button class="dropdown-item"><i class="bi bi-envelope"></i>Envoyer par e-mail au client</button></form></li>
                                    <li><form method="POST" action="{{ route('admin.commercial.invoices.whatsapp', $invoice) }}">@csrf<button class="dropdown-item"><i class="bi bi-whatsapp"></i>Envoyer par WhatsApp</button></form></li>
                                    @if($invoice->status !== 'paid' && $invoice->status !== 'cancelled')
                                        <li><hr class="dropdown-divider"></li>
                                        <li><form method="POST" action="{{ route('admin.commercial.invoices.cancel', $invoice) }}" onsubmit="return confirm('Annuler cette facture ?')">@csrf<button class="dropdown-item text-danger"><i class="bi bi-x-circle"></i>Annuler la facture</button></form></li>
                                    @endif
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-5">
                            <div class="dg-chart-empty" style="min-height:220px">
                                <span class="dg-tile dg-tone-cyan"><i class="bi bi-receipt"></i></span>
                                <div>
                                    <strong>Aucune facture de vente</strong>
                                    Une facture est créée automatiquement à la validation d’une livraison complète.
                                </div>
                                <a href="{{ route('admin.commercial.module', 'livraisons') }}" class="dg-btn dg-btn--outline dg-btn--sm"><i class="bi bi-truck"></i>Voir les livraisons</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Hors du tableau : une ligne fusionnée dans le tbody empêcherait DataTables de s'initialiser. --}}
        <div class="dg-chart-empty mt-4 d-none" id="invoicesNoResult" style="min-height:160px">
            <span class="dg-tile dg-tone-cyan"><i class="bi bi-search"></i></span>
            <div><strong>Aucune facture ne correspond à vos filtres</strong>Modifiez la recherche, la période ou le statut.</div>
        </div>
    </div>

    <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="paymentForm" action="{{ $paymentInvoiceId ? route('admin.commercial.invoices.payment', $paymentInvoiceId) : '#' }}">
                    @csrf
                    <input type="hidden" name="payment_invoice_id" id="paymentInvoiceId" value="{{ $paymentInvoiceId }}">
                    <div class="modal-header">
                        <h5 class="modal-title" id="paymentModalTitle"><i class="bi bi-cash-coin me-2"></i>Enregistrer un paiement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <div class="sales-payment-summary mb-4">
                            <span class="dg-tile dg-tile--sm dg-tone-green"><i class="bi bi-receipt"></i></span>
                            <span class="flex-grow-1">
                                <span class="d-block fw-semibold" id="paymentInvoiceLabel"></span>
                                <span class="d-block dg-muted" style="font-size:13px">Reste à payer</span>
                            </span>
                            <strong class="sales-payment-summary__amount" id="paymentRemainingLabel"></strong>
                        </div>
                        <label class="form-label" for="paymentAmount">Montant reçu</label>
                        <input id="paymentAmount" name="paid_amount" type="number" min="0.01" step="0.01" class="form-control mb-4" required value="{{ old('paid_amount') }}">
                        <label class="form-label" for="paymentMethod">Mode de paiement</label>
                        <select id="paymentMethod" name="payment_method" class="form-select" required>
                            <option value="">Sélectionner…</option>
                            <option value="cash" @selected(old('payment_method') === 'cash')>Espèces (caisse)</option>
                            <option value="bank" @selected(old('payment_method') === 'bank')>Banque</option>
                        </select>
                        <div class="mt-4 d-none" id="paymentCashField">
                            <label class="form-label" for="paymentCash">Caisse</label>
                            <select id="paymentCash" name="cash_account_id" class="form-select" disabled>
                                <option value="">Sélectionner une caisse…</option>
                                @foreach($cashAccounts as $account)
                                    <option value="{{ $account->id }}" @selected((string) old('cash_account_id') === (string) $account->id)>{{ $account->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mt-4 d-none" id="paymentBankField">
                            <label class="form-label" for="paymentBank">Banque</label>
                            <select id="paymentBank" name="bank_account_id" class="form-select" disabled>
                                <option value="">Sélectionner une banque…</option>
                                @foreach($bankAccounts as $account)
                                    <option value="{{ $account->id }}" @selected((string) old('bank_account_id') === (string) $account->id)>{{ $account->name }}{{ $account->bank_name ? ' - ' . $account->bank_name : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer le paiement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .sales-invoices-table { min-width: 900px; }
    .sales-invoices-table td:nth-child(2) { min-width: 150px; }
    .dg-scope .sales-invoices-table > thead > tr > th,
    .dg-scope .sales-invoices-table > tbody > tr > td { padding-left: 12px !important; padding-right: 12px !important; }
    .sales-payment-summary { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: var(--dg-radius); background: rgba(5, 150, 105, .08); border: 1px solid rgba(5, 150, 105, .25); }
    .sales-payment-summary__amount { font-size: 17px; color: #059669; white-space: nowrap; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Filtres : recherche, période et statut, appliqués sur les lignes déjà chargées.
    const rows = Array.from(document.querySelectorAll('#invoicesTable tbody tr[data-status]'));
    const search = document.getElementById('invoiceSearch');
    const dateStart = document.getElementById('invoiceDateStart');
    const dateEnd = document.getElementById('invoiceDateEnd');
    const tabs = Array.from(document.querySelectorAll('[data-status-filter]'));
    const noResult = document.getElementById('invoicesNoResult');
    let status = '';

    const filterInvoices = function () {
        const term = search.value.trim().toLowerCase();
        let visible = 0;
        rows.forEach(function (row) {
            const rowDate = row.dataset.date || '';
            const show = (!term || row.textContent.toLowerCase().includes(term))
                && (!status || row.dataset.status === status)
                && (!dateStart.value || !rowDate || rowDate >= dateStart.value)
                && (!dateEnd.value || !rowDate || rowDate <= dateEnd.value);
            row.style.display = show ? '' : 'none';
            visible += show ? 1 : 0;
        });
        noResult.classList.toggle('d-none', !rows.length || visible > 0);
    };

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            status = tab.dataset.statusFilter;
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

    // Fenêtre de paiement unique, remplie à partir de la ligne choisie.
    const modal = document.getElementById('paymentModal');
    const form = document.getElementById('paymentForm');
    const amount = document.getElementById('paymentAmount');
    const method = document.getElementById('paymentMethod');
    const cashField = document.getElementById('paymentCashField');
    const bankField = document.getElementById('paymentBankField');
    const cashSelect = document.getElementById('paymentCash');
    const bankSelect = document.getElementById('paymentBank');

    const toggleAccounts = function () {
        const isCash = method.value === 'cash';
        const isBank = method.value === 'bank';
        cashField.classList.toggle('d-none', !isCash);
        bankField.classList.toggle('d-none', !isBank);
        cashSelect.disabled = !isCash;
        cashSelect.required = isCash;
        bankSelect.disabled = !isBank;
        bankSelect.required = isBank;
    };
    method.addEventListener('change', toggleAccounts);

    const describe = function (trigger) {
        form.action = trigger.dataset.action;
        document.getElementById('paymentInvoiceId').value = trigger.dataset.paymentInvoice;
        document.getElementById('paymentInvoiceLabel').textContent = trigger.dataset.label;
        document.getElementById('paymentRemainingLabel').textContent = trigger.dataset.remainingLabel;
        amount.max = trigger.dataset.remaining;
    };

    modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger || !trigger.dataset.paymentInvoice) return;
        describe(trigger);
        // Réouverture après une erreur : la saisie est conservée ; sinon, montant restant proposé.
        if (!trigger.dataset.reopen) {
            amount.value = trigger.dataset.remaining;
            method.value = '';
            cashSelect.value = '';
            bankSelect.value = '';
        }
        toggleAccounts();
    });

    @if($paymentInvoiceId && $errors->any())
        window.addEventListener('load', function () {
            const trigger = document.querySelector('[data-payment-invoice="{{ (int) $paymentInvoiceId }}"]');
            if (!trigger) return;
            trigger.dataset.reopen = '1';
            bootstrap.Modal.getOrCreateInstance(modal).show(trigger);
            delete trigger.dataset.reopen;
        });
    @endif
});
</script>
@endsection
