@extends('admin.layout')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body p-6">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-6">
            <div><div class="text-uppercase text-muted fs-8 fw-bold ls-1">Achats</div><h3 class="fs-2 fw-bold text-dark mb-1">{{ $title }}</h3><p class="text-muted mb-0">{{ $subtitle }}</p></div>
            <div class="d-flex gap-2"><a href="{{ route('admin.comptabilite') }}" class="btn btn-light">Retour</a><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importSupplierInvoiceModal"><i class="bi bi-upload me-2"></i>Importer une facture</button></div>
        </div>
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

        <form method="GET" class="row g-3 align-items-end mb-5">
            <div class="col-md-5"><label class="form-label">Recherche</label><input name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Fournisseur, numéro, nom du fichier"></div>
            <div class="col-md-2"><button class="btn btn-light-primary">Filtrer</button></div>
        </form>

        <form method="POST" action="{{ route('admin.comptabilite.supplierInvoices.bulkDestroy') }}" id="supplierInvoicesForm">
            @csrf
            @method('DELETE')
        </form>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-muted small" id="selectedInvoicesLabel">Aucune facture sélectionnée</span>
            <button type="submit" form="supplierInvoicesForm" class="btn btn-sm btn-light-danger" id="deleteSelectedInvoices" disabled onclick="return confirm('Supprimer les factures sélectionnées ?')">
                <i class="bi bi-trash me-1"></i>Supprimer la sélection
            </button>
        </div>
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed supplier-invoices-table">
                <colgroup>
                    <col class="selection-column">
                    <col class="supplier-column">
                    <col class="number-column">
                    <col class="date-column">
                    <col class="amount-column">
                    <col class="amount-column">
                    <col class="actions-column">
                </colgroup>
                <thead><tr class="text-muted text-uppercase fs-7"><th><input class="form-check-input" type="checkbox" id="selectAllInvoices" title="Tout sélectionner"></th><th>Fournisseur</th><th>N° facture</th><th>Date</th><th class="amount-column">Total HT</th><th class="amount-column">Total TTC</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse($invoices as $invoice)
                    <tr>
                        <td><input class="form-check-input invoice-checkbox" form="supplierInvoicesForm" type="checkbox" name="invoice_ids[]" value="{{ $invoice->id }}"></td>
                        <td>{{ $invoice->supplier_name ?: 'À vérifier' }}</td>
                        <td>{{ $invoice->invoice_number ?: 'À vérifier' }}</td>
                        <td>{{ $invoice->invoice_date?->format('d/m/Y') ?: '-' }}</td>
                        <td class="amount-column"><span class="amount-value">{{ ($invoice->total_ht ?? $invoice->subtotal) !== null ? number_format((float) ($invoice->total_ht ?? $invoice->subtotal), 0, ',', ' ') . ' ' . $invoice->currency : 'À vérifier' }}</span></td>
                        <td class="amount-column fw-bold"><span class="amount-value">{{ $invoice->total_amount !== null ? number_format((float) $invoice->total_amount, 0, ',', ' ') . ' ' . $invoice->currency : 'À vérifier' }}</span></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light action-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions"></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="{{ route('admin.comptabilite.supplierInvoices.show', $invoice) }}"><i class="bi bi-eye me-2"></i>Détails</a></li>
                                    <li><a class="dropdown-item" target="_blank" href="{{ route('admin.comptabilite.supplierInvoices.pdf', $invoice) }}"><i class="bi bi-file-earmark-pdf me-2"></i>Voir le PDF</a></li>
                                    @if($invoice->fne_status === 'certified')<li><a class="dropdown-item text-success" target="_blank" href="{{ route('admin.comptabilite.supplierInvoices.printFne', $invoice) }}"><i class="bi bi-printer me-2"></i>Impression FNE</a></li>@endif
                                    @if($invoice->fne_status !== 'certified')<li><form method="POST" action="{{ route('admin.comptabilite.supplierInvoices.fne', $invoice) }}" onsubmit="return confirm('Envoyer cette facture à la FNE ?')">@csrf<button class="dropdown-item"><i class="bi bi-patch-check me-2"></i>Certifier FNE</button></form></li>@endif
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('admin.comptabilite.supplierInvoices.destroy', $invoice) }}" onsubmit="return confirm('Supprimer cette facture ?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash me-2"></i>Supprimer</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-8">Aucune facture fournisseur importée.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="importSupplierInvoiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.comptabilite.supplierInvoices.import') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Importer une facture fournisseur</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body"><label class="form-label fw-bold">Fichier PDF</label><input type="file" name="invoice" class="form-control" accept="application/pdf,.pdf" required><div class="form-text">PDF uniquement, taille maximale 10 Mo.</div></div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Charger et extraire</button></div>
            </form>
        </div>
    </div>
</div>
<style>
.supplier-invoices-table {
    min-width: 820px;
    margin-bottom: 0;
    table-layout: fixed;
}

.supplier-invoices-table th,
.supplier-invoices-table td {
    vertical-align: middle;
    padding: .85rem .75rem;
}

.supplier-invoices-table th {
    white-space: nowrap;
    font-size: .72rem;
    letter-spacing: .04em;
}

.supplier-invoices-table td {
    font-size: .9rem;
}

.supplier-invoices-table .selection-column {
    width: 44px;
}

.supplier-invoices-table .supplier-column {
    width: 240px;
}

.supplier-invoices-table .number-column {
    width: 125px;
}

.supplier-invoices-table .date-column {
    width: 105px;
}

.supplier-invoices-table .actions-column {
    width: 90px;
}

.supplier-invoices-table td:nth-child(2),
.supplier-invoices-table td:nth-child(3) {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.supplier-invoices-table .amount-column {
    width: 135px;
    min-width: 135px;
    text-align: right;
    white-space: nowrap;
}

.supplier-invoices-table .amount-value {
    display: inline-block;
    min-width: 0;
    text-align: right;
    font-variant-numeric: tabular-nums;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('selectAllInvoices');
    const boxes = Array.from(document.querySelectorAll('.invoice-checkbox'));
    const deleteButton = document.getElementById('deleteSelectedInvoices');
    const label = document.getElementById('selectedInvoicesLabel');
    const update = function () {
        const selected = boxes.filter((box) => box.checked).length;
        deleteButton.disabled = selected === 0;
        label.textContent = selected ? selected + ' facture(s) sélectionnée(s)' : 'Aucune facture sélectionnée';
        selectAll.checked = boxes.length > 0 && selected === boxes.length;
        selectAll.indeterminate = selected > 0 && selected < boxes.length;
    };
    selectAll.addEventListener('change', function () {
        boxes.forEach((box) => { box.checked = selectAll.checked; });
        update();
    });
    boxes.forEach((box) => box.addEventListener('change', update));
});
</script>
@endsection
