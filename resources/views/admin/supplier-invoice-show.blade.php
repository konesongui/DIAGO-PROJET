@extends('admin.layout')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body p-6">
        <div class="d-flex justify-content-between align-items-center mb-6"><div><div class="text-uppercase text-muted fs-8 fw-bold ls-1">Facture fournisseur</div><h3 class="fs-2 fw-bold text-dark mb-1">Détail de la facture</h3><p class="text-muted mb-0">{{ $invoice->original_filename }}</p></div><div class="d-flex gap-2"><a href="{{ route('admin.comptabilite.supplierInvoices') }}" class="btn btn-light">Retour</a><a target="_blank" href="{{ route('admin.comptabilite.supplierInvoices.print', $invoice) }}" class="btn btn-light-primary"><i class="bi bi-printer me-2"></i>Imprimer</a><a target="_blank" href="{{ route('admin.comptabilite.supplierInvoices.pdf', $invoice) }}" class="btn btn-danger"><i class="bi bi-file-earmark-pdf me-2"></i>Voir le PDF</a></div></div>
        <div class="row g-4 mb-6">
            @foreach([['Fournisseur', $invoice->supplier_name ?: 'À vérifier'], ['N° facture', $invoice->invoice_number ?: 'À vérifier'], ['Total HT', ($invoice->total_ht ?? $invoice->subtotal) !== null ? number_format((float)($invoice->total_ht ?? $invoice->subtotal), 0, ',', ' ') . ' ' . $invoice->currency : 'À vérifier'], ['Total TTC', $invoice->total_amount !== null ? number_format((float)$invoice->total_amount, 0, ',', ' ') . ' ' . $invoice->currency : 'À vérifier']] as $item)
                <div class="col-md-3"><div class="bg-light rounded-3 p-4"><small class="text-muted">{{ $item[0] }}</small><div class="fw-bold mt-2">{{ $item[1] }}</div></div></div>
            @endforeach
        </div>
        <div class="row g-4 mb-6">
            <div class="col-md-4"><div class="bg-light rounded-3 p-4"><small class="text-muted">Fichier source</small><div class="fw-bold mt-2 text-break">{{ $invoice->original_filename ?: '-' }}</div></div></div>
            <div class="col-md-4"><div class="bg-light rounded-3 p-4"><small class="text-muted">État de l'extraction</small><div class="fw-bold mt-2">{{ $invoice->status === 'imported' ? 'Importée' : 'À vérifier' }}</div></div></div>
            <div class="col-md-4"><div class="bg-light rounded-3 p-4"><small class="text-muted">Certification FNE</small><div class="fw-bold mt-2">{{ $invoice->fne_status === 'certified' ? 'Certifiée' : ($invoice->fne_status === 'failed' ? 'Échec' : 'Non certifiée') }}@if($invoice->fne_reference)<br><span class="text-muted fs-8">{{ $invoice->fne_reference }}</span>@endif</div></div></div>
        </div>

        <div class="alert {{ in_array($invoice->tax_regime, [null, '', 'unknown'], true) ? 'alert-warning' : 'alert-light' }} d-flex flex-wrap align-items-center gap-4 mt-5">
            <div class="flex-grow-1">
                <div class="fw-bold">Régime fiscal déclaré</div>
                <div class="fs-7 text-muted">
                    @if(in_array($invoice->tax_regime, [null, '', 'unknown'], true))
                        Ce régime est issu d'une lecture automatique du PDF et n'a pas été confirmé.
                        La certification est bloquée tant qu'il n'est pas renseigné.
                    @else
                        Régime confirmé : <strong>{{ \App\Models\TaxRate::regimes()[$invoice->tax_regime] ?? $invoice->tax_regime }}</strong>.
                    @endif
                </div>
            </div>
            @if($invoice->fne_status !== 'certified')
            <form method="POST" action="{{ route('admin.comptabilite.supplierInvoices.regime', $invoice) }}" class="d-flex gap-3">
                @csrf @method('PATCH')
                <select name="tax_regime" class="form-select form-select-sm" style="min-width:190px">
                    @foreach($regimes ?? [] as $key => $label)
                        <option value="{{ $key }}" @selected($invoice->tax_regime === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-primary">Confirmer</button>
            </form>
            @endif
        </div>

        <div class="row g-5">
            <div class="col-lg-7"><h5 class="fw-bold mb-4">Données extraites</h5><table class="table table-bordered align-middle"><tbody>@foreach(($invoice->extracted_data ?? []) as $key => $value)<tr><th class="text-muted" style="width:35%">{{ ucwords(str_replace('_', ' ', $key)) }}</th><td>{{ is_scalar($value) && $value !== null && $value !== '' ? $value : 'Non détecté' }}</td></tr>@endforeach</tbody></table></div>
            <div class="col-lg-5"><h5 class="fw-bold mb-4">Aperçu PDF</h5><iframe src="{{ route('admin.comptabilite.supplierInvoices.pdf', $invoice) }}" style="width:100%;height:520px;border:1px solid #e5e7eb;border-radius:12px;"></iframe></div>
        </div>
        @if($invoice->raw_text)<details class="mt-6"><summary class="fw-bold">Texte extrait brut</summary><pre class="bg-light p-4 mt-3 rounded-3" style="white-space:pre-wrap;">{{ $invoice->raw_text }}</pre></details>@endif
    </div>
</div>
@endsection
