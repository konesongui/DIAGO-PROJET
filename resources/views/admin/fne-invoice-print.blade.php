@extends('admin.print.layout')

@php
    $isSale = $documentType === 'Vente';
    $settings = $company?->settings ?? [];
    // Clés enregistrées par les paramètres de l'entreprise ; les anciennes clés servent de repli.
    $fiscal = [
        'NCC' => data_get($settings, 'taxpayer_account') ?: data_get($settings, 'ncc'),
        'Régime d’imposition' => data_get($settings, 'tax_regime') ?: data_get($settings, 'regime_imposition'),
        'Centre des impôts' => data_get($settings, 'tax_center') ?: data_get($settings, 'centre_impot'),
        'RCCM' => data_get($settings, 'nccm_rccm') ?: data_get($settings, 'rccm'),
    ];
    $quote = $isSale ? $invoice->delivery?->order?->quote : null;
    // Le jeton renvoyé par la FNE est l'adresse de vérification de la facture.
    $verificationUrl = filter_var($invoice->fne_token, FILTER_VALIDATE_URL) ? $invoice->fne_token : null;
@endphp

@section('title', 'Facture FNE ' . $invoice->fne_reference)
@section('doc-title', 'FACTURE NORMALISÉE')

@section('doc-meta')
    <p class="number">N° {{ $invoice->fne_reference }}</p>
    <p>{{ $isSale ? 'Facture de vente' : 'Facture d’achat' }} · certifiée le {{ $invoice->fne_certified_at?->format('d/m/Y à H:i') ?: '—' }}</p>
    <span class="pill pill--success"><svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M13.485 3.431a1 1 0 0 1 .084 1.412l-6.5 7.5a1 1 0 0 1-1.479.034l-3-3.1a1 1 0 1 1 1.437-1.39l2.24 2.316 5.806-6.7a1 1 0 0 1 1.412-.072z"/></svg>Certifiée FNE</span>
@endsection

@section('content')
    <div class="fiscal-frame">
        <strong>{{ $company?->name ?: 'DIAGO' }}</strong>
        <dl>
            @foreach($fiscal as $label => $value)
                <dt>{{ $label }}</dt><dd>{{ $value ?: '—' }}</dd>
            @endforeach
        </dl>
    </div>

    <section class="parties">
        @if($isSale)
            <div class="box box--accent">
                <h2>Client</h2>
                <strong>{{ $client?->name ?: $invoice->client_name }}</strong>
                @if($client?->address)<p>{{ $client->address }}</p>@endif
                @if($client?->phone || $client?->email)<p>{{ collect([$client?->phone ? 'Tél : ' . $client->phone : null, $client?->email])->filter()->implode(' · ') }}</p>@endif
                <p>NCC : {{ $client?->tax_id ?: '—' }}</p>
            </div>
            <div class="box">
                <h2>Références</h2>
                <dl>
                    <dt>Facture interne</dt><dd>N° {{ $invoice->id }}</dd>
                    <dt>Date d’émission</dt><dd>{{ ($invoice->issued_at ?? $invoice->created_at)?->format('d/m/Y') ?: '—' }}</dd>
                    <dt>Bon de commande</dt><dd>{{ $invoice->delivery?->order?->customer_order_code ?: '—' }}</dd>
                    @if($quote?->payment_method)<dt>Mode de paiement</dt><dd>{{ $quote->payment_method }}</dd>@endif
                </dl>
            </div>
        @else
            <div class="box box--accent">
                <h2>Fournisseur</h2>
                <strong>{{ $invoice->supplier_name ?: 'Fournisseur à vérifier' }}</strong>
                <p>NCC : {{ $invoice->supplier_tax_id ?: '—' }}</p>
            </div>
            <div class="box">
                <h2>Références</h2>
                <dl>
                    <dt>Facture fournisseur</dt><dd>{{ $invoice->invoice_number ?: '—' }}</dd>
                    <dt>Date de facture</dt><dd>{{ $invoice->invoice_date?->format('d/m/Y') ?: '—' }}</dd>
                    <dt>Régime fiscal</dt><dd>{{ \App\Models\TaxRate::regimes()[$invoice->tax_regime] ?? '—' }}</dd>
                </dl>
            </div>
        @endif
    </section>

    @if($isSale)
        @include('admin.print.sale-body', ['withNotes' => false, 'withPayments' => false])
    @else
        @include('admin.print.supplier-body', ['withNotes' => false])
    @endif

    <section class="certification">
        <div class="certification__body">
            <h3>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 0a8 8 0 1 1 0 16A8 8 0 0 1 8 0zm3.03 4.97a.75.75 0 0 0-1.06.02L7.47 7.9 6.03 6.47a.75.75 0 0 0-1.06 1.06l2 2a.75.75 0 0 0 1.08-.02l3-3.5a.75.75 0 0 0-.02-1.04z"/></svg>
                DOCUMENT CERTIFIÉ ÉLECTRONIQUEMENT PAR LA FNE
            </h3>
            <dl>
                <dt>Référence FNE</dt><dd>{{ $invoice->fne_reference }}</dd>
                <dt>Date de certification</dt><dd>{{ $invoice->fne_certified_at?->format('d/m/Y à H:i') ?: '—' }}</dd>
                @if($invoice->fne_token && ! $verificationUrl)<dt>Code de vérification</dt><dd>{{ $invoice->fne_token }}</dd>@endif
                @if($verificationUrl)<dt>Vérification</dt><dd>{{ $verificationUrl }}</dd>@endif
                @if($invoice->fne_balance_sticker !== null)<dt>Solde de stickers</dt><dd>{{ rtrim(rtrim(number_format((float) $invoice->fne_balance_sticker, 2, ',', ' '), '0'), ',') }}</dd>@endif
            </dl>
            <p>Conservez cette facture : sa référence FNE permet toute vérification auprès de l’administration fiscale.</p>
        </div>
        @if($verificationUrl)
            <div class="certification__qr">
                <div id="fneQr" data-url="{{ $verificationUrl }}"></div>
                Scannez pour vérifier
            </div>
        @endif
    </section>
@endsection

@if($verificationUrl)
    @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        <script>
            // QR code de l'adresse de vérification ; sans la bibliothèque, l'adresse reste lisible en clair.
            (function () {
                var target = document.getElementById('fneQr');
                if (!target || typeof QRCode === 'undefined') { if (target) target.parentNode.style.display = 'none'; return; }
                new QRCode(target, { text: target.dataset.url, width: 224, height: 224, colorDark: '#172033', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
            })();
        </script>
    @endpush
@endif
