@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
    <div>
        <div class="text-uppercase text-muted fs-8 fw-bold">Commercial</div>
        <h2 class="fs-2 fw-bold mb-1">{{ $title ?? 'Facture personnalisée' }}</h2>
        <p class="text-muted mb-0">{{ $subtitle ?? 'Suivi des factures personnalisées et leur statut.' }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.commercial') }}" class="btn btn-light">Retour</a>
        <a href="{{ route('admin.commercial.custom-invoice.create') }}" class="btn btn-primary"><i class="ki-duotone ki-plus fs-2 me-2"></i>Nouvelle facture</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header border-0 bg-transparent px-4 pt-4 pb-0">
    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-center gap-3 w-100">
        <div class="d-flex align-items-center gap-3 flex-grow-1 w-100">
            <div class="position-relative flex-grow-1">
                <span class="svg-icon svg-icon-2 position-absolute top-50 start-0 translate-middle-y ms-4 text-muted">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="currentColor"/>
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.68629 5 5 7.68629 5 11C5 14.3137 7.68629 17 11 17C14.3137 17 17 14.3137 17 11C17 7.68629 14.3137 5 11 5Z" fill="currentColor"/>
                    </svg>
                </span>
                <input type="search" class="form-control form-control-lg border-0 bg-light rounded-pill ps-12" placeholder="Search Customers" aria-label="Rechercher une facture" id="customInvoiceSearch" style="min-height: 62px; font-size: 1.1rem;" />
            </div>

            <div class="btn-group">
                <button type="button" class="btn btn-light-info px-6 py-4 fw-bold text-primary rounded-2" style="background: #e6f1ff; min-width: 170px; min-height: 62px;" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="ki-duotone ki-filter fs-2 me-2"></i> Filtrer
                </button>
                <div class="dropdown-menu dropdown-menu-end p-4" style="min-width: 320px;">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted mb-2">Statut</label>
                        <select class="form-select form-select-solid" id="customInvoiceStatusFilter" aria-label="Filtrer par statut">
                            <option value="">Tous les statuts</option>
                            <option value="En attente">En attente</option>
                            <option value="Paiement partiel">Paiement partiel</option>
                            <option value="Payée">Payée</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold text-muted mb-2">Du</label>
                            <input type="date" class="form-control form-control-solid" id="customInvoiceDateStart" aria-label="Date de début">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold text-muted mb-2">Au</label>
                            <input type="date" class="form-control form-control-solid" id="customInvoiceDateEnd" aria-label="Date de fin">
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light btn-sm" id="resetCustomInvoiceFilters">Réinitialiser</button>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="dropdown">Appliquer</button>
                    </div>
                </div>
            </div>

            <a href="{{ route('admin.commercial.custom-invoice.create') }}" class="btn btn-primary px-6 py-4 fw-bold rounded-2" style="min-height: 62px;">Nouvelle facture</a>
        </div>
    </div>
</div>
<div class="card-body px-4 pb-4">
        <div class="table-responsive">
        <table class="table align-middle table-row-dashed table-row-gray-300 gy-6" id="customInvoiceTable">
            <thead>
                <tr class="text-muted text-uppercase fs-7 fw-bold">
                    <th>Client</th>
                    <th>Date</th>
                    <th>Mode de paiement</th>
                    <th class="text-end">Total HT</th>
                    <th class="text-end">Montant payé</th>
                    <th class="text-end">Reste à payer</th>
                    <th class="text-end">Total TTC</th>
                    <th>Statut</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($invoices as $invoice)
                @php
                    $paidAmount = (float) ($invoice->paid_amount ?? 0);
                    $totalTtc = (float) ($invoice->total_ttc ?? 0);
                    $remainingAmount = max(0, $totalTtc - $paidAmount);
                    $statusClass = $paidAmount >= $totalTtc ? 'success' : ($paidAmount > 0 ? 'warning' : 'secondary');
                    $statusLabel = $paidAmount >= $totalTtc ? 'Payée' : ($paidAmount > 0 ? 'Paiement partiel' : 'En attente');
                    $invoiceDate = $invoice->quote_date ?? $invoice->created_at;
                @endphp
                <tr data-status="{{ $statusLabel }}" data-date="{{ $invoiceDate ? \Carbon\Carbon::parse($invoiceDate)->format('Y-m-d') : '' }}">
                    <td>{{ $invoice->client_name ?: 'Client' }}</td>
                    <td>{{ optional($invoice->quote_date)->translatedFormat('d/m/Y') ?: '-' }}</td>
                    <td>{{ $invoice->payment_method ?: $invoice->cash_payment_method ?: '-' }}</td>
                    <td class="text-end fw-bold text-nowrap">{{ number_format($invoice->total_ht, 0, ',', ' ') }} XOF</td>
                    <td class="text-end fw-bold text-nowrap">{{ number_format($paidAmount, 0, ',', ' ') }} XOF</td>
                    <td class="text-end fw-bold text-nowrap">{{ number_format($remainingAmount, 0, ',', ' ') }} XOF</td>
                    <td class="text-end fw-bold text-nowrap">{{ number_format($totalTtc, 0, ',', ' ') }} XOF</td>
                    <td>
                        <span class="badge badge-light-{{ $statusClass }}">{{ $statusLabel }}</span>
                    </td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light btn-active-light-primary" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="ki-duotone ki-dots fs-3"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="{{ route('admin.commercial.custom-invoice.show', $invoice) }}">Voir</a></li>
                                <li><a class="dropdown-item" href="{{ route('admin.commercial.custom-invoice.edit', $invoice) }}">Modifier</a></li>
                                <li>
                                    <form method="POST" action="{{ route('admin.commercial.custom-invoice.destroy', $invoice) }}" onsubmit="return confirm('Supprimer cette facture ?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="dropdown-item text-danger" {{ (float) $invoice->paid_amount > 0 || !empty($invoice->paid_at) ? 'disabled' : '' }}>Supprimer</button>
                                    </form>
                                </li>
                                <li>
                                    <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#paymentModal-{{ $invoice->id }}">Paiement</button>
                                </li>
                                <li>
                                    <form method="POST" action="{{ route('admin.commercial.custom-invoice.email', $invoice) }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item">Envoyer facture par email</button>
                                    </form>
                                </li>
                                <li>
                                    <form method="POST" action="{{ route('admin.commercial.custom-invoice.whatsapp', $invoice) }}" onsubmit="return confirm('Souhaitez-vous envoyer cette facture par WhatsApp ?');">
                                        @csrf
                                        <button type="submit" class="dropdown-item text-success">Envoyer facture par WhatsApp</button>
                                    </form>
                                </li>
                                <li><a class="dropdown-item" href="{{ route('admin.commercial.custom-invoice.print', $invoice) }}" target="_blank">Imprimer facture</a></li>
                                <li>
                                    <form method="POST" action="{{ route('admin.commercial.custom-invoice.duplicate', $invoice) }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item">Dupliquer facture</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </td>
                </tr>

                <div class="modal fade" id="paymentModal-{{ $invoice->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('admin.commercial.custom-invoice.payment', $invoice) }}">
                                @csrf
                                <div class="modal-header">
                                    <h5 class="modal-title">Enregistrer un paiement</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Montant à payer</label>
                                        <input type="number" name="amount" class="form-control" min="0" step="0.01" max="{{ max(0, (float) $invoice->total_ttc - (float) $invoice->paid_amount) }}" value="{{ number_format(max(0, (float) $invoice->total_ttc - (float) $invoice->paid_amount), 2, '.', '') }}" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Mode de paiement</label>
                                        <select name="payment_method" class="form-select payment-method" data-target="payment-account-{{ $invoice->id }}" required>
                                            <option value="">Sélectionner...</option>
                                            <option value="cash">Espèces</option>
                                            <option value="bank">Banque</option>
                                        </select>
                                    </div>
                                    <div id="payment-account-{{ $invoice->id }}" class="mt-3">
                                        <div class="cash-account-field d-none">
                                            <label class="form-label">Caisse</label>
                                            <select name="cash_account_id" class="form-select cash-account-select" disabled>
                                                <option value="">Sélectionner une caisse...</option>
                                                @foreach(App\Models\CashAccount::where('entreprise_id', auth()->user()->entreprise_id)->where('is_active', true)->orderBy('name')->get() as $account)
                                                    <option value="{{ $account->id }}">{{ $account->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-10">Aucune facture personnalisée enregistrée.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
 
@push('scripts')
<script>
    const searchInput = document.getElementById('customInvoiceSearch');
    const statusFilter = document.getElementById('customInvoiceStatusFilter');
    const dateStartInput = document.getElementById('customInvoiceDateStart');
    const dateEndInput = document.getElementById('customInvoiceDateEnd');

    function filterCustomInvoices() {
        const search = (searchInput?.value || '').toLowerCase();
        const status = statusFilter?.value || '';
        const dateStart = dateStartInput?.value || '';
        const dateEnd = dateEndInput?.value || '';

        document.querySelectorAll('#customInvoiceTable tbody tr[data-status]').forEach((row) => {
            const rowDate = row.dataset.date || '';
            const matchesSearch = !search || row.textContent.toLowerCase().includes(search);
            const matchesStatus = !status || row.dataset.status === status;
            const matchesDateStart = !dateStart || !rowDate || rowDate >= dateStart;
            const matchesDateEnd = !dateEnd || !rowDate || rowDate <= dateEnd;

            row.style.display = matchesSearch && matchesStatus && matchesDateStart && matchesDateEnd ? '' : 'none';
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterCustomInvoices);
    }

    if (statusFilter) {
        statusFilter.addEventListener('change', filterCustomInvoices);
    }

    if (dateStartInput) {
        dateStartInput.addEventListener('change', filterCustomInvoices);
    }

    if (dateEndInput) {
        dateEndInput.addEventListener('change', filterCustomInvoices);
    }

    const resetCustomInvoiceFiltersButton = document.getElementById('resetCustomInvoiceFilters');
    if (resetCustomInvoiceFiltersButton) {
        resetCustomInvoiceFiltersButton.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            if (statusFilter) statusFilter.value = '';
            if (dateStartInput) dateStartInput.value = '';
            if (dateEndInput) dateEndInput.value = '';
            filterCustomInvoices();
        });
    }

    if (window.jQuery && $.fn.DataTable) {
        const customInvoiceTable = $('#customInvoiceTable').DataTable({
            paging: true,
            ordering: true,
            searching: false,
            pageLength: 10,
            lengthMenu: [5, 10, 25, 50],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
            },
            dom: "<'row'<'col-sm-12 col-md-6 d-flex align-items-center justify-content-start'l><'col-sm-12 col-md-6 d-flex justify-content-end'>>" +
                "<'table-responsive'tr>" +
                "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            columnDefs: [
                { targets: 8, orderable: false, searchable: false }
            ]
        });

        customInvoiceTable.on('draw', function() {
            filterCustomInvoices();
        });
    }

    document.querySelectorAll('.payment-method').forEach((select) => {
        select.addEventListener('change', () => {
            const target = document.getElementById(select.dataset.target);
            if (!target) return;
            const cashField = target.querySelector('.cash-account-field');
            const cashSelect = target.querySelector('.cash-account-select');
            const isCash = select.value === 'cash';

            cashField.classList.toggle('d-none', !isCash);
            cashSelect.disabled = !isCash;
            cashSelect.required = isCash;
        });
    });
</script>
@endpush
@endsection
