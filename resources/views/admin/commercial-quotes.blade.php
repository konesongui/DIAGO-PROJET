@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-5">
    <div>
        <div class="text-uppercase text-muted fs-8 fw-bold">Commercial</div>
        <h2 class="fs-2 fw-bold mb-1">Devis</h2>
        <p class="text-muted mb-0">Suivez les devis et leur validation.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.commercial') }}" class="btn btn-light">Retour</a>
        <a href="{{ route('admin.commercial.quotes.create') }}" class="btn btn-primary"><i class="ki-duotone ki-plus fs-2 me-2"></i>Créer un devis</a>
    </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

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
                <input id="quoteSearch" type="search" class="form-control form-control-lg border-0 bg-light rounded-pill ps-12" placeholder="Search Customers" aria-label="Rechercher un devis" style="min-height: 62px; font-size: 1.1rem;">
            </div>

            <div class="btn-group">
                <button type="button" class="btn btn-light-info px-6 py-4 fw-bold text-primary rounded-2" style="background: #e6f1ff; min-width: 170px; min-height: 62px;" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="ki-duotone ki-filter fs-2 me-2"></i> Filtrer
                </button>
                <div class="dropdown-menu dropdown-menu-end p-4" style="min-width: 320px;">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted mb-2">Statut</label>
                        <select id="quoteStatus" class="form-select form-select-solid" aria-label="Filtrer par statut">
                            <option value="">Tous les statuts</option>
                            <option value="pending_validation">En attente</option>
                            <option value="validated">Validé</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold text-muted mb-2">Du</label>
                            <input id="quoteDateStart" type="date" class="form-control form-control-solid" aria-label="Date de début">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold text-muted mb-2">Au</label>
                            <input id="quoteDateEnd" type="date" class="form-control form-control-solid" aria-label="Date de fin">
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light btn-sm" id="resetQuoteFilters">Réinitialiser</button>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="dropdown">Appliquer</button>
                    </div>
                </div>
            </div>

            <a href="{{ route('admin.commercial.quotes.create') }}" class="btn btn-primary px-6 py-4 fw-bold rounded-2" style="min-height: 62px;">Créer un devis</a>
        </div>
    </div>
</div>
<div class="card-body px-4 pb-4 table-responsive">
        <table class="table table-row-bordered table-hover align-middle" id="quotesTable">
            <thead>
                <tr class="text-muted text-uppercase fs-7">
                    <th>Référence</th><th>Client</th><th>Date</th><th>Enregistré par</th>
                    <th>Bon de commande</th><th class="text-end">Total TTC</th><th>Statut</th><th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($quotes as $quote)
                @php
                    $quoteDate = $quote->quote_date ?? $quote->created_at;
                @endphp
                <tr data-status="{{ $quote->status }}" data-date="{{ $quoteDate ? \Carbon\Carbon::parse($quoteDate)->format('Y-m-d') : '' }}">
                    <td><span class="fw-bold">{{ $quote->reference ?: '-' }}</span></td>
                    <td>{{ $quote->client_name ?: '-' }}</td>
                    <td>{{ optional($quote->quote_date)->format('d/m/Y') ?: '-' }}</td>
                    <td>{{ $quote->creator->name ?? '-' }}</td>
                    <td>{{ $quote->customer_order_code ?: '-' }}</td>
                    <td class="text-end fw-bold text-nowrap">{{ number_format($quote->total_ttc, 0, ',', ' ') }} XOF</td>
                    <td><span class="badge badge-light-{{ $quote->status === 'validated' ? 'success' : 'warning' }}">{{ $quote->status === 'validated' ? 'Validé' : 'En attente' }}</span></td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light btn-active-light-primary" data-bs-toggle="dropdown" aria-expanded="false">Actions <i class="ki-duotone ki-down fs-6 ms-1"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                @if($quote->status === 'pending_validation')
                                    <li><form method="POST" action="{{ route('admin.commercial.quotes.validate', $quote) }}" class="px-3 py-2">@csrf<input name="customer_order_code" class="form-control form-control-sm mb-2" placeholder="Code BC client" required maxlength="100"><button class="btn btn-sm btn-success w-100">Valider le devis</button></form></li>
                                @endif
                                <li><a class="dropdown-item" href="{{ route('admin.commercial.quotes.edit', $quote) }}">Modifier</a></li>
                                <li><a class="dropdown-item" target="_blank" href="{{ route('admin.commercial.quotes.print', $quote) }}">Imprimer</a></li>
                                <li><form method="POST" action="{{ route('admin.commercial.quotes.email', $quote) }}">@csrf<button class="dropdown-item">Envoyer par mail</button></form></li>
                                <li><form method="POST" action="{{ route('admin.commercial.quotes.duplicate', $quote) }}">@csrf<button class="dropdown-item">Dupliquer</button></form></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><form method="POST" action="{{ route('admin.commercial.quotes.destroy', $quote) }}" onsubmit="return confirm('Supprimer ce devis ?')">@csrf @method('DELETE')<button class="dropdown-item text-danger">Supprimer</button></form></li>
                            </ul>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-10">Aucun devis enregistré.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@push('scripts')
<script>
function filterQuotes() {
    const search = document.getElementById('quoteSearch').value.toLowerCase();
    const status = document.getElementById('quoteStatus').value;
    const dateStart = document.getElementById('quoteDateStart').value;
    const dateEnd = document.getElementById('quoteDateEnd').value;

    document.querySelectorAll('#quotesTable tbody tr[data-status]').forEach(function (row) {
        const rowDate = row.dataset.date || '';
        const matchesSearch = !search || row.textContent.toLowerCase().includes(search);
        const matchesStatus = !status || row.dataset.status === status;
        const matchesDateStart = !dateStart || !rowDate || rowDate >= dateStart;
        const matchesDateEnd = !dateEnd || !rowDate || rowDate <= dateEnd;

        row.style.display = matchesSearch && matchesStatus && matchesDateStart && matchesDateEnd ? '' : 'none';
    });
}

document.getElementById('quoteSearch').addEventListener('input', filterQuotes);
document.getElementById('quoteStatus').addEventListener('change', filterQuotes);
document.getElementById('quoteDateStart').addEventListener('change', filterQuotes);
document.getElementById('quoteDateEnd').addEventListener('change', filterQuotes);

const resetQuoteFiltersButton = document.getElementById('resetQuoteFilters');
if (resetQuoteFiltersButton) {
    resetQuoteFiltersButton.addEventListener('click', function () {
        document.getElementById('quoteSearch').value = '';
        document.getElementById('quoteStatus').value = '';
        document.getElementById('quoteDateStart').value = '';
        document.getElementById('quoteDateEnd').value = '';
        filterQuotes();
    });
}
</script>
@endpush
@endsection
