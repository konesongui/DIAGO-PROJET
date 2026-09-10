@extends('admin.layout')

@section('content')
<div class="container-fluid py-5">
    <div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
        <div>
            <div class="text-uppercase text-muted fs-8 fw-bold">Commercial</div>
            <h2 class="fs-2 fw-bold mb-1">{{ $invoice->reference }}</h2>
            <p class="text-muted mb-0">Facture personnalisée</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.commercial.custom-invoice.index') }}" class="btn btn-light">Retour</a>
            @if($locked ?? false)
                <span class="btn btn-light disabled" title="{{ $lockReason }}">Modifier</span>
            @else
                <a href="{{ route('admin.commercial.custom-invoice.edit', $invoice) }}" class="btn btn-primary">Modifier</a>
                <form method="POST" action="{{ route('admin.commercial.custom-invoice.issue', $invoice) }}" class="d-inline"
                      onsubmit="return confirm('Émettre cette facture ? Elle ne pourra plus être modifiée : seule l\'émission d\'un avoir permettra de la corriger.')">
                    @csrf
                    <button class="btn btn-primary">Émettre</button>
                </form>
            @endif
            <a href="{{ route('admin.commercial.custom-invoice.print', $invoice) }}" target="_blank" class="btn btn-success">Imprimer</a>
            <form method="POST" action="{{ route('admin.commercial.custom-invoice.whatsapp', $invoice) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-success">WhatsApp</button>
            </form>
        </div>
    </div>

    @if($locked ?? false)
    <div class="alert alert-info d-flex flex-wrap align-items-center gap-4">
        <div class="flex-grow-1">
            <div class="fw-bold">Facture émise</div>
            <div class="fs-7">{{ $lockReason }} Elle ne peut plus être modifiée ni supprimée.</div>
            @if(($creditedAmount ?? 0) > 0)
                <div class="fs-7 mt-1">Déjà porté en avoir : <strong>{{ number_format((float) $creditedAmount, 2, ',', ' ') }}</strong>.
                Reste à créditer : <strong>{{ number_format((float) $creditableAmount, 2, ',', ' ') }}</strong>.</div>
            @endif
        </div>
        @if(($creditableAmount ?? 0) > 0)
        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#creditNote">Émettre un avoir</button>
        @endif
    </div>
    @endif

    @if(!empty($creditNotes) && count($creditNotes))
    <div class="card border-0 mb-5">
        <div class="card-header"><h3 class="card-title">Avoirs émis</h3></div>
        <div class="card-body table-responsive">
            <table class="table align-middle">
                <thead><tr class="text-muted fs-7 text-uppercase"><th>Référence</th><th>Date</th><th>Motif</th><th class="text-end">Montant</th></tr></thead>
                <tbody>
                @foreach($creditNotes as $note)
                    <tr>
                        <td class="fw-semibold">{{ $note->reference }}</td>
                        <td class="text-muted">{{ $note->created_at->format('d/m/Y') }}</td>
                        <td>{{ $note->reason }}</td>
                        <td class="text-end">{{ number_format((float) $note->total_ttc, 2, ',', ' ') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

    <div class="card shadow-sm border-0">
        <div class="card-body p-5">
            <div class="row g-4 mb-5">
                <div class="col-md-6">
                    <h5 class="text-muted text-uppercase fs-8 fw-bold mb-3">Client</h5>
                    <div class="fw-bold fs-5">{{ $invoice->client_name ?: 'Client' }}</div>
                    <div>{{ $invoice->client_phone ?: '-' }}</div>
                    <div>{{ $invoice->client_email ?: '-' }}</div>
                </div>
                <div class="col-md-6 text-md-end">
                    <h5 class="text-muted text-uppercase fs-8 fw-bold mb-3">Détails</h5>
                    <div><strong>Date :</strong> {{ optional($invoice->quote_date)->translatedFormat('d/m/Y') ?: '-' }}</div>
                    <div><strong>Échéance :</strong> {{ optional($invoice->valid_until)->translatedFormat('d/m/Y') ?: '-' }}</div>
                    <div><strong>Paiement :</strong> {{ $invoice->cash_payment_method ?: $invoice->payment_method ?: '-' }}</div>
                </div>
            </div>

            <div class="table-responsive mb-5">
                <table class="table table-bordered align-middle">
                    <thead>
                        <tr class="text-uppercase text-muted fs-7">
                            <th>Article</th>
                            <th>Quantité</th>
                            <th>Unité</th>
                            <th>Prix</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($invoice->items ?? [] as $item)
                        <tr>
                            <td>{{ $item['item_name'] ?? 'Article' }}</td>
                            <td>{{ $item['quantity'] ?? 0 }}</td>
                            <td>{{ $item['unit'] ?? '-' }}</td>
                            <td>{{ number_format((float) ($item['price'] ?? 0), 0, ',', ' ') }} CFA</td>
                            <td>{{ number_format(((float) ($item['quantity'] ?? 0)) * ((float) ($item['price'] ?? 0)), 0, ',', ' ') }} CFA</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="row justify-content-end">
                <div class="col-md-5">
                    <div class="border rounded p-4 bg-light">
                        <div class="d-flex justify-content-between mb-2"><span>Total HT</span><strong>{{ number_format($invoice->total_ht, 0, ',', ' ') }} CFA</strong></div>
                        <div class="d-flex justify-content-between mb-2"><span>Remise</span><strong>{{ number_format($invoice->total_discount, 0, ',', ' ') }} CFA</strong></div>
                        <div class="d-flex justify-content-between mb-2"><span>TVA</span><strong>{{ number_format($invoice->tax_amount, 0, ',', ' ') }} CFA</strong></div>
                        <div class="d-flex justify-content-between border-top pt-3 mt-3"><span>Total TTC</span><strong>{{ number_format($invoice->total_ttc, 0, ',', ' ') }} CFA</strong></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="creditNote" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.commercial.custom-invoice.credit', $invoice) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Émettre un avoir</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted fs-7">
                        La facture d'origine reste inchangée. L'avoir enregistre la correction et reprend
                        son régime fiscal.
                    </p>
                    <div class="mb-4">
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="full" value="1" id="fullCredit">
                            <span class="form-check-label ms-2">Avoir total ({{ number_format((float) ($creditableAmount ?? 0), 2, ',', ' ') }})</span>
                        </label>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Montant à créditer</label>
                        <input type="number" step="0.01" min="0.01" max="{{ $creditableAmount ?? 0 }}" name="amount" id="creditAmount" class="form-control">
                        <div class="form-text">Maximum : {{ number_format((float) ($creditableAmount ?? 0), 2, ',', ' ') }}</div>
                    </div>
                    <div>
                        <label class="form-label required">Motif</label>
                        <textarea name="reason" class="form-control" rows="3" minlength="5" required
                                  placeholder="Ex. : erreur de quantité, retour marchandise, remise accordée après coup"></textarea>
                        <div class="form-text">Obligatoire : un avoir sans motif n'est pas justifiable lors d'un contrôle.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                    <button class="btn btn-warning">Émettre l'avoir</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    document.getElementById('fullCredit')?.addEventListener('change', function () {
        const amount = document.getElementById('creditAmount');
        amount.disabled = this.checked;
        if (this.checked) { amount.value = ''; }
    });
</script>
@endsection