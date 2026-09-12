@extends('admin.print.layout')

@php
    $regimeUnconfirmed = in_array($invoice->tax_regime, [null, '', 'unknown'], true);
    $regimeLabel = $regimeUnconfirmed ? 'À confirmer' : (\App\Models\TaxRate::regimes()[$invoice->tax_regime] ?? $invoice->tax_regime);
    $verified = $invoice->status === 'imported';
@endphp

@section('title', 'Facture fournisseur ' . ($invoice->invoice_number ?: $invoice->id))
@section('doc-title', 'FACTURE FOURNISSEUR')

@section('doc-meta')
    <p class="number">N° {{ $invoice->invoice_number ?: 'à vérifier' }}</p>
    <p>Date de facture : {{ $invoice->invoice_date?->format('d/m/Y') ?: 'à vérifier' }}</p>
    <span class="pill pill--{{ $verified ? 'success' : 'warning' }}">{{ $verified ? 'Importée' : 'À vérifier' }}</span>
@endsection

@section('content')
    <section class="parties">
        <div class="box box--accent">
            <h2>Fournisseur</h2>
            <strong>{{ $invoice->supplier_name ?: 'Fournisseur à vérifier' }}</strong>
            <p>Compte contribuable (NCC) : {{ $invoice->supplier_tax_id ?: '—' }}</p>
        </div>
        <div class="box">
            <h2>Références</h2>
            <dl>
                <dt>Date de facture</dt><dd>{{ $invoice->invoice_date?->format('d/m/Y') ?: '—' }}</dd>
                <dt>Échéance</dt><dd>{{ $invoice->due_date?->format('d/m/Y') ?: '—' }}</dd>
                <dt>Régime fiscal</dt><dd>{{ $regimeLabel }}</dd>
                <dt>Enregistrée le</dt><dd>{{ $invoice->created_at?->format('d/m/Y') ?: '—' }}</dd>
            </dl>
        </div>
    </section>

    @include('admin.print.supplier-body')

    @if($invoice->fne_status === 'certified')
        <div class="callout">
            <strong>FACTURE CERTIFIÉE FNE</strong><br>
            Référence FNE : {{ $invoice->fne_reference ?: '—' }}
            · Certifiée le {{ $invoice->fne_certified_at?->format('d/m/Y à H:i') ?: '—' }}
            @if($invoice->fne_balance_sticker !== null) · Solde du sticker : {{ $invoice->fne_balance_sticker }}@endif
        </div>
    @endif
@endsection
