@extends('admin.layout')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-5">
    <div>
        <div class="text-uppercase text-muted fs-8 fw-bold">Commercial</div>
        <h2 class="fs-2 fw-bold mb-1">Factures</h2>
        <p class="text-muted mb-0">Les factures apparaissent après validation d’une livraison complète.</p>
    </div>
    <a href="{{ route('admin.commercial.module', 'livraisons') }}" class="btn btn-light">Voir les livraisons</a>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="card border-0">
    <div class="card-header border-0">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 w-100">
            <h3 class="card-title mb-0">Factures à encaisser</h3>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <input id="invoiceSearch" type="search" class="form-control form-control-sm" placeholder="Rechercher..." aria-label="Rechercher une facture">
                <select id="invoiceStatus" class="form-select form-select-sm" aria-label="Filtrer par statut">
                    <option value="">Tous les statuts</option>
                    <option value="paid">Payée</option>
                    <option value="partially_paid">Partiellement payée</option>
                    <option value="pending">Impayée</option>
                    <option value="cancelled">Annulée</option>
                </select>
                <input id="invoiceDateStart" type="date" class="form-control form-control-sm" aria-label="Date de début">
                <input id="invoiceDateEnd" type="date" class="form-control form-control-sm" aria-label="Date de fin">
            </div>
        </div>
    </div>
    <div class="card-body table-responsive">
        <table class="table align-middle table-row-bordered" id="invoicesTable">
            <thead>
                <tr>
                    <th>Client</th><th>Code BC client</th><th>Montant</th><th>Payé</th><th>Reste</th><th>Statut</th><th>FNE</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $invoice)
                    @php
                        $invoiceDate = $invoice->invoice_date ?? $invoice->created_at ?? ($invoice->delivery->delivery_date ?? null);
                    @endphp
                    <tr data-status="{{ $invoice->status }}" data-date="{{ $invoiceDate ? \Carbon\Carbon::parse($invoiceDate)->format('Y-m-d') : '' }}">
                        <td>{{ $invoice->client_name }}</td>
                        <td>{{ $invoice->delivery->order->customer_order_code ?: '-' }}</td>
                        <td>{{ number_format($invoice->amount,0,',',' ') }} XOF</td>
                        <td>{{ number_format($invoice->paid_amount,0,',',' ') }} XOF</td>
                        <td>{{ number_format(max(0, (float) $invoice->amount - (float) $invoice->paid_amount),0,',',' ') }} XOF</td>
                        <td><span class="badge badge-light-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'cancelled' ? 'danger' : 'warning') }}">{{ $invoice->status === 'paid' ? 'Payée' : ($invoice->status === 'cancelled' ? 'Annulée' : ($invoice->status === 'partially_paid' ? 'Partiellement payée' : 'Impayée')) }}</span></td>
                        <td><span class="badge badge-light-{{ $invoice->fne_status === 'certified' ? 'success' : ($invoice->fne_status === 'failed' ? 'danger' : 'warning') }}">{{ $invoice->fne_status === 'certified' ? 'Certifiée' : ($invoice->fne_status === 'failed' ? 'Échec' : 'Non certifiée') }}</span></td>
                        <td><div class="dropdown"><button class="btn btn-sm btn-light action-menu-button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions"></button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" target="_blank" href="{{ route('admin.commercial.invoices.print',$invoice) }}">Imprimer</a></li>
                            @if($invoice->fne_status === 'certified')
                            <li><a class="dropdown-item text-success" target="_blank" href="{{ route('admin.commercial.invoices.printFne',$invoice) }}">Impression FNE</a></li>
                            @endif
                            @if($invoice->fne_status !== 'certified')
                            <li><form method="POST" action="{{ route('admin.commercial.invoices.fne',$invoice) }}" onsubmit="return confirm('Envoyer cette facture à la FNE ?')">@csrf<button class="dropdown-item">Certifier FNE</button></form></li>
                            @endif
                            <li><form method="POST" action="{{ route('admin.commercial.invoices.email',$invoice) }}">@csrf<button class="dropdown-item">Envoyer par mail au client</button></form></li>
                            <li><form method="POST" action="{{ route('admin.commercial.invoices.whatsapp',$invoice) }}">@csrf<button class="dropdown-item">Envoyer par WhatsApp</button></form></li>
                            @if($invoice->status !== 'paid' && $invoice->status !== 'cancelled')
                            <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#paymentModal-{{ $invoice->id }}">Paiement</button></li><li><form method="POST" action="{{ route('admin.commercial.invoices.cancel',$invoice) }}" onsubmit="return confirm('Annuler cette facture ?')">@csrf<button class="dropdown-item text-danger">Annuler</button></form></li>
                            @endif
                            </ul></div></td>
                    </tr>
                    <div class="modal fade" id="paymentModal-{{ $invoice->id }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('admin.commercial.invoices.payment',$invoice) }}">@csrf<div class="modal-header"><h5 class="modal-title">Enregistrer un paiement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><label class="form-label">Montant à payer</label><input name="paid_amount" type="number" min="0.01" max="{{ max(0, (float)$invoice->amount - (float)$invoice->paid_amount) }}" value="{{ number_format(max(0, (float)$invoice->amount - (float)$invoice->paid_amount), 2, '.', '') }}" step="0.01" class="form-control mb-4" required><label class="form-label">Mode de paiement</label><select name="payment_method" class="form-select payment-method" data-target="payment-account-{{ $invoice->id }}" required><option value="">Sélectionner...</option><option value="cash">Espèces</option><option value="bank">Banque</option></select><div id="payment-account-{{ $invoice->id }}" class="mt-4"><div class="cash-account-field d-none"><label class="form-label">Caisse</label><select name="cash_account_id" class="form-select cash-account-select" disabled><option value="">Sélectionner une caisse...</option>@foreach($cashAccounts as $account)<option value="{{ $account->id }}">{{ $account->name }}</option>@endforeach</select></div><div class="bank-account-field d-none"><label class="form-label">Banque</label><select name="bank_account_id" class="form-select bank-account-select" disabled><option value="">Sélectionner une banque...</option>@foreach($bankAccounts as $account)<option value="{{ $account->id }}">{{ $account->name }}{{ $account->bank_name ? ' - '.$account->bank_name : '' }}</option>@endforeach</select></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Enregistrer le paiement</button></div></form></div></div></div>
                @empty
                    <tr><td colspan="8" class="text-center text-muted">Aucune facture issue d’une livraison complète.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<script>
function filterInvoices() {
    const search = document.getElementById('invoiceSearch').value.toLowerCase();
    const status = document.getElementById('invoiceStatus').value;
    const dateStart = document.getElementById('invoiceDateStart').value;
    const dateEnd = document.getElementById('invoiceDateEnd').value;

    document.querySelectorAll('#invoicesTable tbody tr[data-status]').forEach(function (row) {
        const rowDate = row.dataset.date || '';
        const matchesSearch = !search || row.textContent.toLowerCase().includes(search);
        const matchesStatus = !status || row.dataset.status === status;
        const matchesDateStart = !dateStart || !rowDate || rowDate >= dateStart;
        const matchesDateEnd = !dateEnd || !rowDate || rowDate <= dateEnd;

        row.style.display = matchesSearch && matchesStatus && matchesDateStart && matchesDateEnd ? '' : 'none';
    });
}

document.getElementById('invoiceSearch').addEventListener('input', filterInvoices);
document.getElementById('invoiceStatus').addEventListener('change', filterInvoices);
document.getElementById('invoiceDateStart').addEventListener('change', filterInvoices);
document.getElementById('invoiceDateEnd').addEventListener('change', filterInvoices);

document.querySelectorAll('.payment-method').forEach(select => select.addEventListener('change', () => {
    const target = document.getElementById(select.dataset.target);
    if (!target) return;
    const cash = target.querySelector('.cash-account-field');
    const bank = target.querySelector('.bank-account-field');
    const cashSelect = target.querySelector('.cash-account-select');
    const bankSelect = target.querySelector('.bank-account-select');
    const isCash = select.value === 'cash';
    cash.classList.toggle('d-none', !isCash);
    bank.classList.toggle('d-none', isCash || !select.value);
    cashSelect.disabled = !isCash;
    cashSelect.required = isCash;
    bankSelect.disabled = isCash || !select.value;
    bankSelect.required = !isCash && !!select.value;
}));
</script>
@endsection
