@extends('admin.print.layout')

@php
    $expired = $quote->status === 'pending_validation' && $quote->due_date && $quote->due_date->lt(now()->startOfDay());
    [$statusLabel, $statusTone] = $quote->status === 'validated'
        ? ['Validé', 'success']
        : ($expired ? ['Expiré', 'danger'] : ['En attente de validation', 'warning']);

    // Totaux : lignes HT, remise, HT net (base de la TVA), TVA et TTC portés par le devis.
    $grossHt = (float) $quote->total_ht;
    $discount = (float) $quote->total_discount;
    $netHt = $quote->net_ht !== null ? (float) $quote->net_ht : $grossHt - $discount;
    // Anciens devis sans régime fiscal : seule la valeur TTC est fiable.
    $hasTaxBreakdown = ! in_array($quote->tax_regime, [null, '', 'unknown'], true);
    $rate = rtrim(rtrim(number_format((float) $quote->tax_rate, 2, ',', ''), '0'), ',');
    $taxLabel = in_array($quote->tax_regime, ['exempt', 'export', 'reverse_charge'], true)
        ? 'TVA (' . mb_strtolower(\App\Models\TaxRate::regimes()[$quote->tax_regime]) . ')'
        : 'TVA (' . $rate . ' %)';

    $settings = $company?->settings ?? [];
    $bankDetails = collect([data_get($settings, 'bank_name'), data_get($settings, 'bank_account') ? 'compte n° ' . data_get($settings, 'bank_account') : null])->filter()->implode(', ');
@endphp

@section('title', 'Devis ' . ($quote->reference ?: $quote->id))
@section('doc-title', 'DEVIS')

@section('doc-meta')
    <p class="number">N° {{ $quote->reference ?: $quote->id }}</p>
    <p>Date : {{ $quote->quote_date?->format('d/m/Y') ?: '—' }}@if($quote->due_date) · valable jusqu’au {{ $quote->due_date->format('d/m/Y') }}@endif</p>
    <span class="pill pill--{{ $statusTone }}">{{ $statusLabel }}</span>
@endsection

@section('content')
    <section class="parties">
        <div class="box box--accent">
            <h2>Client</h2>
            <strong>{{ $client?->name ?: $quote->client_name }}</strong>
            @if($client?->address)<p>{{ $client->address }}</p>@endif
            @if($client?->city)<p>{{ $client->city }}</p>@endif
            @if($client?->phone || $client?->email)<p>{{ collect([$client?->phone ? 'Tél : ' . $client->phone : null, $client?->email])->filter()->implode(' · ') }}</p>@endif
            @if($client?->tax_id)<p>Compte contribuable : {{ $client->tax_id }}</p>@endif
        </div>
        <div class="box">
            <h2>Références</h2>
            <dl>
                @if($quote->subject)<dt>Objet</dt><dd>{{ $quote->subject }}</dd>@endif
                @if($quote->customer_order_code)<dt>Bon de commande</dt><dd>{{ $quote->customer_order_code }}</dd>@endif
                <dt>Mode de paiement</dt><dd>{{ $quote->payment_method ?: '—' }}</dd>
                @if($quote->delivery_location)<dt>Lieu de livraison</dt><dd>{{ $quote->delivery_location }}</dd>@endif
            </dl>
        </div>
    </section>

    @include('admin.print.item-lines', ['lines' => $quote->lines ?? []])

    <section class="summary">
        <div class="notes">
            @if($quote->payment_terms)
                <h3>Conditions de règlement</h3>
                <p>{{ $quote->payment_terms }}</p>
            @endif
            @if($quote->delivery_terms)
                <h3>Conditions de livraison</h3>
                <p>{{ $quote->delivery_terms }}</p>
            @endif
            @if($bankDetails)
                <h3>Règlement par virement</h3>
                <p>{{ $bankDetails }}</p>
            @endif
        </div>
        <table class="totals">
            @if($hasTaxBreakdown)
                @if($discount > 0)
                    <tr><td>Total HT brut</td><td>{{ money($grossHt) }}</td></tr>
                    <tr><td>Remise</td><td>− {{ money($discount) }}</td></tr>
                @endif
                <tr><td>Total HT</td><td>{{ money($netHt) }}</td></tr>
                <tr><td>{{ $taxLabel }}</td><td>{{ money((float) $quote->tax_amount) }}</td></tr>
            @endif
            <tr class="due"><td>Total TTC</td><td>{{ money((float) $quote->total_ttc) }}</td></tr>
        </table>
    </section>

    @if($quote->status !== 'validated')
        {{-- Le client retourne le devis signé : c'est son acceptation. --}}
        <section class="agreement">
            <div class="agreement__box">
                <strong>Bon pour accord</strong>
                <span>Date, cachet et signature du client, précédés de la mention « Bon pour accord »</span>
            </div>
        </section>
    @endif
@endsection

@push('scripts')
    <style>
        .agreement { display: flex; justify-content: flex-end; margin-top: 18px; break-inside: avoid; }
        .agreement__box { width: 46%; min-height: 92px; padding: 10px 14px; border: 1px dashed #9aa3b5; border-radius: 10px; font-size: 11px; color: var(--muted); }
        .agreement__box strong { display: block; margin-bottom: 2px; color: var(--text); font-size: 12px; }
    </style>
@endpush
