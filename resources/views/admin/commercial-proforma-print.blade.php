@extends('admin.print.layout')

@php
    $state = $proforma->state();
    [$stateLabel, $stateTone] = \App\Models\CommercialProforma::states()[$state];
    $client = $proforma->client;

    // Totaux : lignes HT brutes, remises des lignes, HT net (base de la TVA), TVA et TTC portés par la proforma.
    $grossHt = (float) $proforma->total_ht;
    $discount = (float) $proforma->total_discount;
    // Anciennes proformas sans régime fiscal : seule la valeur TTC est fiable.
    $hasTaxBreakdown = ! in_array($proforma->tax_regime, [null, '', 'unknown'], true);

    $settings = $company?->settings ?? [];
    $bankDetails = collect([data_get($settings, 'bank_name'), data_get($settings, 'bank_account') ? 'compte n° ' . data_get($settings, 'bank_account') : null])->filter()->implode(', ');
@endphp

@section('title', 'Proforma ' . ($proforma->reference ?: $proforma->id))
@section('doc-title', 'PROFORMA')

@section('doc-meta')
    <p class="number">N° {{ $proforma->reference ?: $proforma->id }}</p>
    <p>Date : {{ $proforma->creation_date?->format('d/m/Y') ?: '—' }}@if($proforma->due_date) · valable jusqu’au {{ $proforma->due_date->format('d/m/Y') }}@endif</p>
    <span class="pill pill--{{ $stateTone }}">{{ $stateLabel }}</span>
@endsection

@section('content')
    <section class="parties">
        <div class="box box--accent">
            <h2>Client</h2>
            <strong>{{ $client?->name ?: $proforma->client_name }}</strong>
            @if($client?->address)<p>{{ $client->address }}</p>@endif
            @if($client?->city)<p>{{ $client->city }}</p>@endif
            @php $contact = collect([($client?->phone ?: $proforma->client_phone) ? 'Tél : ' . ($client?->phone ?: $proforma->client_phone) : null, $client?->email])->filter()->implode(' · '); @endphp
            @if($contact)<p>{{ $contact }}</p>@endif
            @if($client?->tax_id)<p>Compte contribuable : {{ $client->tax_id }}</p>@endif
        </div>
        <div class="box">
            <h2>Références</h2>
            <dl>
                @if($proforma->subject)<dt>Objet</dt><dd>{{ $proforma->subject }}</dd>@endif
                <dt>Mode de paiement</dt><dd>{{ $proforma->payment_method ?: '—' }}</dd>
                @if($proforma->delivery_location)<dt>Lieu de livraison</dt><dd>{{ $proforma->delivery_location }}</dd>@endif
            </dl>
        </div>
    </section>

    @include('admin.print.item-lines', ['lines' => $proforma->documentLines()])

    <section class="summary">
        <div class="notes">
            @if($proforma->payment_terms)
                <h3>Conditions de règlement</h3>
                <p>{{ $proforma->payment_terms }}</p>
            @endif
            @if($proforma->delivery_terms)
                <h3>Conditions de livraison</h3>
                <p>{{ $proforma->delivery_terms }}</p>
            @endif
            @if($bankDetails)
                <h3>Règlement par virement</h3>
                <p>{{ $bankDetails }}</p>
            @endif
            <p class="offer-note">Offre de prix{{ $proforma->due_date ? ' valable jusqu’au ' . $proforma->due_date->format('d/m/Y') : '' }} : ce document ne vaut pas facture.</p>
        </div>
        <table class="totals">
            @if($hasTaxBreakdown)
                @if($discount > 0)
                    <tr><td>Total HT brut</td><td>{{ money($grossHt) }}</td></tr>
                    <tr><td>Remises</td><td>− {{ money($discount) }}</td></tr>
                @endif
                <tr><td>Total HT</td><td>{{ money((float) $proforma->net_ht) }}</td></tr>
                <tr><td>{{ $proforma->taxLabel() }}</td><td>{{ money((float) $proforma->tax_amount) }}</td></tr>
            @endif
            <tr class="due"><td>Total TTC</td><td>{{ money((float) $proforma->total_ttc) }}</td></tr>
        </table>
    </section>

    @if($state !== 'expired')
        {{-- Le client retourne la proforma signée : c'est son acceptation de l'offre. --}}
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
        .offer-note { margin-top: 10px !important; font-style: italic; }
        .agreement { display: flex; justify-content: flex-end; margin-top: 18px; break-inside: avoid; }
        .agreement__box { width: 46%; min-height: 92px; padding: 10px 14px; border: 1px dashed #9aa3b5; border-radius: 10px; font-size: 11px; color: var(--muted); }
        .agreement__box strong { display: block; margin-bottom: 2px; color: var(--text); font-size: 12px; }
    </style>
@endpush
