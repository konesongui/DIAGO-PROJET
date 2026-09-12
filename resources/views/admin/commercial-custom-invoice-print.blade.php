@extends('admin.print.layout')

@php
    $state = $invoice->state();
    [$stateLabel, $stateTone] = \App\Models\CustomInvoice::states()[$state];
    $isDraft = ! $invoice->issued_at;

    // Totaux : lignes et forfaits HT, remise, HT net (base de la TVA), TVA et TTC portés par la facture.
    $grossHt = (float) $invoice->total_ht;
    $discount = (float) $invoice->total_discount;
    $netHt = (float) ($invoice->subtotal_after_discount ?: max(0, $grossHt - $discount));
    $credited = (float) $invoice->credited_amount;
    $creditNotes = $invoice->creditNotes;
    $plannedMethod = ['cash' => 'Espèces', 'bank' => 'Banque'][$invoice->payment_method] ?? $invoice->payment_method;

    $settings = $company?->settings ?? [];
    $bankDetails = collect([data_get($settings, 'bank_name'), data_get($settings, 'bank_account') ? 'compte n° ' . data_get($settings, 'bank_account') : null])->filter()->implode(', ');
@endphp

@section('title', 'Facture ' . $invoice->reference)
@section('doc-title', 'FACTURE')

@section('doc-meta')
    <p class="number">N° {{ $invoice->reference }}</p>
    <p>Date : {{ ($invoice->quote_date ?? $invoice->created_at)?->format('d/m/Y') ?: '—' }}@if($invoice->valid_until) · échéance le {{ $invoice->valid_until->format('d/m/Y') }}@endif</p>
    <span class="pill pill--{{ $state === 'draft' ? 'warning' : $stateTone }}">{{ $state === 'draft' ? 'Brouillon, non émise' : $stateLabel . ($isDraft ? ', non émise' : '') }}</span>
@endsection

@section('content')
    @if($isDraft)
        {{-- Un brouillon imprimé ne doit pas pouvoir passer pour la facture définitive. --}}
        <div class="draft-mark" aria-hidden="true">BROUILLON</div>
    @endif

    <section class="parties">
        <div class="box box--accent">
            <h2>Client</h2>
            <strong>{{ $invoice->client_name ?: 'Client' }}</strong>
            @if($invoice->client_phone || $invoice->client_email)
                <p>{{ collect([$invoice->client_phone ? 'Tél : ' . $invoice->client_phone : null, $invoice->client_email])->filter()->implode(' · ') }}</p>
            @endif
            @if($invoice->delivery_location)<p>Livraison : {{ $invoice->delivery_location }}</p>@endif
        </div>
        <div class="box">
            <h2>Références</h2>
            <dl>
                @if($invoice->subject)<dt>Objet</dt><dd>{{ $invoice->subject }}</dd>@endif
                <dt>Mode de règlement</dt><dd>{{ $plannedMethod ?: '—' }}</dd>
                @if($invoice->issued_at)<dt>Émise le</dt><dd>{{ $invoice->issued_at->format('d/m/Y') }}</dd>@endif
            </dl>
        </div>
    </section>

    @include('admin.print.item-lines', ['lines' => $invoice->documentLines()])

    <section class="summary">
        <div class="notes">
            @if($invoice->payment_terms)
                <h3>Conditions de règlement</h3>
                <p>{{ $invoice->payment_terms }}</p>
            @endif
            @if($invoice->delivery_terms)
                <h3>Conditions de livraison</h3>
                <p>{{ $invoice->delivery_terms }}</p>
            @endif
            @if($bankDetails && $state !== 'cancelled')
                <h3>Règlement par virement</h3>
                <p>{{ $bankDetails }}</p>
            @endif
            @if($creditNotes->isNotEmpty())
                <h3>Avoirs émis sur cette facture</h3>
                @foreach($creditNotes as $note)
                    <p>{{ $note->reference }} du {{ $note->created_at->format('d/m/Y') }} : − {{ money((float) $note->total_ttc) }}<br>{{ $note->reason }}</p>
                @endforeach
            @endif
        </div>
        <table class="totals">
            @if($discount > 0)
                <tr><td>Total HT brut</td><td>{{ money($grossHt) }}</td></tr>
                <tr><td>Remise</td><td>− {{ money($discount) }}</td></tr>
            @endif
            <tr><td>Total HT</td><td>{{ money($netHt) }}</td></tr>
            <tr><td>{{ $invoice->taxLabel() }}</td><td>{{ money((float) $invoice->tax_amount) }}</td></tr>
            <tr class="grand"><td>Total TTC</td><td>{{ money((float) $invoice->total_ttc) }}</td></tr>
            @if($credited > 0)
                <tr class="sep"><td>Avoirs</td><td>− {{ money($credited) }}</td></tr>
                <tr><td>Net dû</td><td>{{ money($invoice->amountDue()) }}</td></tr>
            @endif
            <tr class="{{ $credited > 0 ? '' : 'sep' }}"><td>Déjà payé</td><td>{{ money((float) $invoice->paid_amount) }}</td></tr>
            @if($state !== 'cancelled')
                <tr class="due"><td>Reste à payer</td><td>{{ money($invoice->remainingAmount()) }}</td></tr>
            @endif
        </table>
    </section>
@endsection

@push('scripts')
    <style>
        /* Au-dessus du contenu, très pâle : le fond des lignes du tableau ne le masque pas. */
        .draft-mark { position: absolute; top: 44%; left: 50%; z-index: 2; transform: translate(-50%, -50%) rotate(-28deg); font-size: 96px; font-weight: 700; letter-spacing: .12em; color: rgba(217, 119, 6, .07); pointer-events: none; white-space: nowrap; }
    </style>
@endpush
