@extends('admin.layout')

@section('content')
@php
    $companyCurrency = company_currency()['code'];
    $currencyLabel = fn ($currency) => ! $currency || $currency === $companyCurrency ? currency_symbol() : $currency;
    $amount = fn ($value, $currency) => $value !== null ? number_format((float) $value, 0, ',', ' ') . ' ' . $currencyLabel($currency) : null;
    $needsReview = fn ($invoice) => $invoice->status !== 'imported' || ! $invoice->supplier_name || ! $invoice->invoice_number || $invoice->total_amount === null;
    $stats = [
        ['Factures importées', $invoices->count(), 'bi-files', 'indigo', 'dans la liste'],
        ['À vérifier', $invoices->filter($needsReview)->count(), 'bi-exclamation-triangle', 'red', 'données incomplètes'],
        ['Certifiées FNE', $invoices->where('fne_status', 'certified')->count(), 'bi-patch-check', 'green', 'factures certifiées'],
        ['Total TTC', money((float) $invoices->where('currency', $companyCurrency)->sum('total_amount')), 'bi-cash-stack', 'orange', 'factures en ' . currency_symbol()],
    ];
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.comptabilite')" back-label="Comptabilité">
        <x-slot:actions>
            <button type="button" class="dg-btn dg-btn--primary" data-bs-toggle="modal" data-bs-target="#importSupplierInvoiceModal"><i class="bi bi-upload"></i>Importer une facture</button>
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="dg-kpi-grid">
        @foreach($stats as [$label, $value, $icon, $color, $hint])
            <x-dg.kpi :label="$label" :value="$value" :icon="$icon" :color="$color" :hint="$hint" />
        @endforeach
    </div>

    <form method="POST" action="{{ route('admin.comptabilite.supplierInvoices.bulkDestroy') }}" id="supplierInvoicesForm">
        @csrf
        @method('DELETE')
    </form>

    <div class="dg-card dg-card--table">
        <div class="dg-card__header flex-wrap">
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-orange"><i class="bi bi-file-earmark-text"></i></span>Suivi des achats fournisseurs</h2>
            <form method="GET" class="d-flex flex-wrap align-items-center gap-2" role="search" aria-label="Filtrer les factures">
                <label class="dg-search" style="max-width:320px">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Fournisseur, numéro, fichier…" aria-label="Rechercher une facture">
                </label>
                <button class="dg-btn dg-btn--outline"><i class="bi bi-funnel"></i>Filtrer</button>
                @if(!empty($filters['search']))
                    <a href="{{ route('admin.comptabilite.supplierInvoices') }}" class="dg-btn dg-btn--outline" title="Effacer la recherche" aria-label="Effacer la recherche"><i class="bi bi-x-lg"></i></a>
                @endif
            </form>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <span class="dg-muted" style="font-size:13px" id="selectedInvoicesLabel">Aucune facture sélectionnée</span>
            <button type="submit" form="supplierInvoicesForm" class="dg-btn dg-btn--outline dg-btn--sm dg-btn--danger" id="deleteSelectedInvoices" disabled onclick="return confirm('Supprimer les factures sélectionnées ?')">
                <i class="bi bi-trash"></i>Supprimer la sélection
            </button>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 supplier-invoices-table">
                <thead>
                    <tr>
                        <th class="supplier-invoices-table__check"><input class="form-check-input" type="checkbox" id="selectAllInvoices" title="Tout sélectionner" aria-label="Tout sélectionner"></th>
                        <th>Date</th>
                        <th>Fournisseur</th>
                        <th>Justificatif</th>
                        <th class="text-end">Total HT</th>
                        <th class="text-end">Total TTC</th>
                        <th>Statut</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($invoices as $invoice)
                    <tr>
                        <td><input class="form-check-input invoice-checkbox" form="supplierInvoicesForm" type="checkbox" name="invoice_ids[]" value="{{ $invoice->id }}" aria-label="Sélectionner la facture {{ $invoice->invoice_number ?: $invoice->id }}"></td>
                        <td class="text-nowrap">{{ $invoice->invoice_date?->format('d/m/Y') ?: '—' }}</td>
                        <td>
                            <a href="{{ route('admin.comptabilite.supplierInvoices.show', $invoice) }}" class="fw-semibold text-reset text-decoration-none">{{ $invoice->supplier_name ?: 'Fournisseur à vérifier' }}</a>
                            <span class="d-block dg-muted" style="font-size:12.5px">N° {{ $invoice->invoice_number ?: 'à vérifier' }}</span>
                        </td>
                        <td>
                            @if($invoice->file_path)
                                <a class="dg-chip" target="_blank" href="{{ route('admin.comptabilite.supplierInvoices.pdf', $invoice) }}" title="{{ $invoice->original_filename }}"><i class="bi bi-file-earmark-pdf"></i>{{ \Illuminate\Support\Str::limit($invoice->original_filename ?: 'Facture.pdf', 18) }}</a>
                            @else
                                <span class="dg-badge dg-badge--warning"><i class="bi bi-exclamation-triangle"></i>Manquant</span>
                            @endif
                        </td>
                        <td class="text-end dg-cell-num">{{ $amount($invoice->total_ht ?? $invoice->subtotal, $invoice->currency) ?? '—' }}</td>
                        <td class="text-end dg-cell-num">{{ $amount($invoice->total_amount, $invoice->currency) ?? '—' }}</td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                @if($needsReview($invoice))
                                    <span class="dg-badge dg-badge--warning">À vérifier</span>
                                @else
                                    <span class="dg-badge dg-badge--success">Importée</span>
                                @endif
                                @if($invoice->fne_status === 'certified')
                                    <span class="dg-badge dg-badge--neutral"><i class="bi bi-patch-check"></i>FNE</span>
                                @elseif($invoice->fne_status === 'failed')
                                    <span class="dg-badge dg-badge--danger">FNE : échec</span>
                                @endif
                            </div>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light action-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions"></button>
                                <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                                    <li><a class="dropdown-item" href="{{ route('admin.comptabilite.supplierInvoices.show', $invoice) }}"><i class="bi bi-eye"></i>Détails</a></li>
                                    <li><a class="dropdown-item" target="_blank" href="{{ route('admin.comptabilite.supplierInvoices.pdf', $invoice) }}"><i class="bi bi-file-earmark-pdf"></i>Voir le PDF</a></li>
                                    @if($invoice->fne_status === 'certified')<li><a class="dropdown-item" target="_blank" href="{{ route('admin.comptabilite.supplierInvoices.printFne', $invoice) }}"><i class="bi bi-printer"></i>Impression FNE</a></li>@endif
                                    @if($invoice->fne_status !== 'certified')<li><form method="POST" action="{{ route('admin.comptabilite.supplierInvoices.fne', $invoice) }}" onsubmit="return confirm('Envoyer cette facture à la FNE ?')">@csrf<button class="dropdown-item"><i class="bi bi-patch-check"></i>Certifier FNE</button></form></li>@endif
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('admin.comptabilite.supplierInvoices.destroy', $invoice) }}" onsubmit="return confirm('Supprimer cette facture ?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash"></i>Supprimer</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="p-5">
                            <div class="dg-chart-empty" style="min-height:220px">
                                <span class="dg-tile dg-tone-orange"><i class="bi bi-file-earmark-arrow-up"></i></span>
                                <div>
                                    <strong>{{ !empty($filters['search']) ? 'Aucune facture ne correspond à votre recherche' : 'Aucune facture fournisseur importée' }}</strong>
                                    Importez une facture PDF : ses informations sont extraites automatiquement.
                                </div>
                                @if(empty($filters['search']))
                                    <button type="button" class="dg-btn dg-btn--primary dg-btn--sm" data-bs-toggle="modal" data-bs-target="#importSupplierInvoiceModal"><i class="bi bi-upload"></i>Importer une facture</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="importSupplierInvoiceModal" tabindex="-1" aria-labelledby="importSupplierInvoiceTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.comptabilite.supplierInvoices.import') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title" id="importSupplierInvoiceTitle"><i class="bi bi-upload me-2"></i>Importer une facture fournisseur</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                    <div class="modal-body">
                        <label class="form-label" for="supplierInvoiceFile">Fichier PDF</label>
                        <input type="file" id="supplierInvoiceFile" name="invoice" class="form-control" accept="application/pdf,.pdf" required>
                        <div class="form-text">PDF uniquement, 10 Mo maximum. Le fournisseur, le numéro, la date et les montants sont extraits automatiquement ; vous pourrez les vérifier sur la fiche.</div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary"><i class="bi bi-magic me-1"></i>Charger et extraire</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .supplier-invoices-table { min-width: 900px; }
    .supplier-invoices-table__check { width: 44px; }
    .supplier-invoices-table td:nth-child(3) { min-width: 210px; }
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
