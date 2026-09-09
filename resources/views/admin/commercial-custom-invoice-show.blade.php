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
            <a href="{{ route('admin.commercial.custom-invoice.edit', $invoice) }}" class="btn btn-primary">Modifier</a>
            <a href="{{ route('admin.commercial.custom-invoice.print', $invoice) }}" target="_blank" class="btn btn-success">Imprimer</a>
            <form method="POST" action="{{ route('admin.commercial.custom-invoice.whatsapp', $invoice) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-success">WhatsApp</button>
            </form>
        </div>
    </div>

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
                        <div class="d-flex justify-content-between mb-2"><span>TVA</span><strong>{{ number_format($invoice->vat_amount, 0, ',', ' ') }} CFA</strong></div>
                        <div class="d-flex justify-content-between border-top pt-3 mt-3"><span>Total TTC</span><strong>{{ number_format($invoice->total_ttc, 0, ',', ' ') }} CFA</strong></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
