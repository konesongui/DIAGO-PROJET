@extends('admin.print.layout')

@php
    $quote = $order->quote;
    $delivery = $order->delivery;
    $invoice = $delivery?->invoice;
    [$statusLabel, $statusTone] = $invoice
        ? ['Livrée et facturée', 'success']
        : ($delivery?->status === 'validated' ? ['Livrée, à facturer', 'warning'] : ['À livrer', 'warning']);

    // Totaux portés par la commande. Les commandes anciennes n'ont pas de
    // ventilation fiscale : seul leur TTC est alors sûr.
    $netHt = (float) ($order->total_ht ?? 0);
    $hasTaxBreakdown = ! in_array($order->tax_regime, [null, '', 'unknown'], true);
    $rate = rtrim(rtrim(number_format((float) $order->tax_rate, 2, ',', ''), '0'), ',');
    $taxLabel = in_array($order->tax_regime, ['exempt', 'export', 'reverse_charge'], true)
        ? 'TVA (' . mb_strtolower(\App\Models\TaxRate::regimes()[$order->tax_regime]) . ')'
        : 'TVA (' . $rate . ' %)';
@endphp

@section('title', 'Bon de commande ' . ($order->reference ?: $order->id))
@section('doc-title', 'BON DE COMMANDE')

@section('doc-meta')
    <p class="number">N° {{ $order->reference ?: $order->id }}</p>
    <p>Date : {{ $order->created_at?->format('d/m/Y') ?: '—' }}</p>
    <span class="pill pill--{{ $statusTone }}">{{ $statusLabel }}</span>
@endsection

@section('content')
    <section class="parties">
        <div class="box box--accent">
            <h2>Client</h2>
            <strong>{{ $client?->name ?: $order->client_name }}</strong>
            @if($client?->address)<p>{{ $client->address }}</p>@endif
            @if($client?->city)<p>{{ $client->city }}</p>@endif
            @if($client?->phone || $client?->email)<p>{{ collect([$client?->phone ? 'Tél : ' . $client->phone : null, $client?->email])->filter()->implode(' · ') }}</p>@endif
            @if($client?->tax_id)<p>Compte contribuable : {{ $client->tax_id }}</p>@endif
        </div>
        <div class="box">
            <h2>Références</h2>
            <dl>
                @if($quote?->subject)<dt>Objet</dt><dd>{{ $quote->subject }}</dd>@endif
                @if($quote?->reference)<dt>Devis</dt><dd>{{ $quote->reference }}@if($quote->quote_date) du {{ $quote->quote_date->format('d/m/Y') }}@endif</dd>@endif
                @if($order->customer_order_code)<dt>Commande du client</dt><dd>{{ $order->customer_order_code }}</dd>@endif
                @if($quote?->payment_method)<dt>Mode de paiement</dt><dd>{{ $quote->payment_method }}</dd>@endif
                @if($quote?->delivery_location)<dt>Lieu de livraison</dt><dd>{{ $quote->delivery_location }}</dd>@endif
            </dl>
        </div>
    </section>

    @include('admin.print.item-lines', ['lines' => $order->lines ?? []])

    <section class="summary">
        <div class="notes">
            @if($quote?->delivery_terms)
                <h3>Conditions de livraison</h3>
                <p>{{ $quote->delivery_terms }}</p>
            @endif
            @if($quote?->payment_terms)
                <h3>Conditions de règlement</h3>
                <p>{{ $quote->payment_terms }}</p>
            @endif
            @if($invoice)
                <h3>Suite donnée</h3>
                <p>Livrée le {{ $delivery?->updated_at?->format('d/m/Y') }} · facture n° {{ $invoice->id }} du {{ ($invoice->issued_at ?: $invoice->created_at)?->format('d/m/Y') }}.</p>
            @endif
        </div>
        <table class="totals">
            @if($hasTaxBreakdown)
                <tr><td>Total HT</td><td>{{ money($netHt) }}</td></tr>
                <tr><td>{{ $taxLabel }}</td><td>{{ money((float) $order->tax_amount) }}</td></tr>
            @endif
            <tr class="due"><td>Total TTC</td><td>{{ money((float) $order->total_ttc) }}</td></tr>
        </table>
    </section>
@endsection
