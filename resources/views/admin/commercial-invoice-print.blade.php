@extends('admin.print.layout')

@php
    $quote = $invoice->delivery?->order?->quote;
    $issuedAt = $invoice->issued_at ?? $invoice->created_at;
    $statuses = [
        'paid' => ['Payée', 'success'],
        'partially_paid' => ['Partiellement payée', 'warning'],
        'unpaid' => ['En attente de paiement', 'danger'],
        'cancelled' => ['Annulée', 'neutral'],
    ];
    [$statusLabel, $statusTone] = $statuses[$invoice->status] ?? [$invoice->status, 'neutral'];
@endphp

@section('title', 'Facture N° ' . $invoice->id)
@section('doc-title', 'FACTURE')

@section('doc-meta')
    <p class="number">N° {{ $invoice->id }}</p>
    <p>Date d'émission : {{ $issuedAt?->format('d/m/Y') ?: '—' }}</p>
    <span class="pill pill--{{ $statusTone }}">{{ $statusLabel }}</span>
@endsection

@section('content')
    <section class="parties">
        <div class="box box--accent">
            <h2>Facturé à</h2>
            <strong>{{ $client?->name ?: $invoice->client_name }}</strong>
            @if($client?->address)<p>{{ $client->address }}</p>@endif
            @if($client?->city)<p>{{ $client->city }}</p>@endif
            @if($client?->phone || $client?->email)<p>{{ collect([$client?->phone ? 'Tél : ' . $client->phone : null, $client?->email])->filter()->implode(' · ') }}</p>@endif
            @if($client?->tax_id)<p>Compte contribuable : {{ $client->tax_id }}</p>@endif
        </div>
        <div class="box">
            <h2>Références</h2>
            <dl>
                <dt>Bon de commande</dt><dd>{{ $invoice->delivery?->order?->customer_order_code ?: '—' }}</dd>
                @if($quote?->reference)<dt>Devis</dt><dd>{{ $quote->reference }}</dd>@endif
                @if($quote?->subject)<dt>Objet</dt><dd>{{ $quote->subject }}</dd>@endif
                @if($quote?->payment_method)<dt>Mode de paiement</dt><dd>{{ $quote->payment_method }}</dd>@endif
                @if($quote?->delivery_location)<dt>Lieu de livraison</dt><dd>{{ $quote->delivery_location }}</dd>@endif
            </dl>
        </div>
    </section>

    @include('admin.print.sale-body')

    @if($invoice->fne_status === 'certified')
        <div class="callout">
            <strong>FACTURE CERTIFIÉE FNE</strong><br>
            Référence FNE : {{ $invoice->fne_reference ?: '—' }}
            · Certifiée le {{ $invoice->fne_certified_at?->format('d/m/Y à H:i') ?: '—' }}
            @if($invoice->fne_balance_sticker !== null) · Solde du sticker : {{ $invoice->fne_balance_sticker }}@endif
        </div>
    @endif
@endsection
