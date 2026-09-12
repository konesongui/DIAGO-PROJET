{{--
    Lignes et totaux d'une facture de vente imprimée.
    Variables : $invoice (CommercialInvoice), $company ; $withNotes et $withPayments (vrai par défaut).
--}}
@php
    $withNotes ??= true;
    $withPayments ??= true;
    $quote = $invoice->delivery?->order?->quote;
    $lines = collect($invoice->delivery?->lines ?? []);

    // Totaux : lignes HT, remise globale du devis, HT net, TVA et TTC portés par la facture.
    $grossHt = $lines->sum(fn ($line) => (float) ($line['quantity'] ?? 0) * (float) ($line['unit_price'] ?? 0));
    $discount = (float) ($quote?->total_discount ?? 0);
    // Anciennes factures sans régime fiscal : seule la valeur TTC est fiable.
    $hasTaxBreakdown = ! in_array($invoice->tax_regime, [null, '', 'unknown'], true) && $invoice->total_ht !== null;
    $rate = rtrim(rtrim(number_format((float) $invoice->tax_rate, 2, ',', ''), '0'), ',');
    $taxLabel = in_array($invoice->tax_regime, ['exempt', 'export', 'reverse_charge'], true)
        ? 'TVA (' . mb_strtolower(\App\Models\TaxRate::regimes()[$invoice->tax_regime]) . ')'
        : 'TVA (' . $rate . ' %)';
    $remaining = max(0, (float) $invoice->amount - (float) $invoice->paid_amount);

    $settings = $company?->settings ?? [];
    $bankDetails = collect([data_get($settings, 'bank_name'), data_get($settings, 'bank_account') ? 'compte n° ' . data_get($settings, 'bank_account') : null])->filter()->implode(', ');
    $hasNotes = $withNotes && ($quote?->payment_terms || $bankDetails || $quote?->delivery_terms);
@endphp

@include('admin.print.item-lines', ['lines' => $lines])

<section class="summary">
    <div class="notes">
        @if($hasNotes)
            @if($quote?->payment_terms)
                <h3>Conditions de règlement</h3>
                <p>{{ $quote->payment_terms }}</p>
            @endif
            @if($bankDetails)
                <h3>Règlement par virement</h3>
                <p>{{ $bankDetails }}</p>
            @endif
            @if($quote?->delivery_terms)
                <h3>Conditions de livraison</h3>
                <p>{{ $quote->delivery_terms }}</p>
            @endif
        @endif
    </div>
    <table class="totals">
        @if($hasTaxBreakdown)
            @if($discount > 0)
                <tr><td>Total HT brut</td><td>{{ money($grossHt) }}</td></tr>
                <tr><td>Remise</td><td>− {{ money($discount) }}</td></tr>
            @endif
            <tr><td>Total HT</td><td>{{ money((float) $invoice->total_ht) }}</td></tr>
            <tr><td>{{ $taxLabel }}</td><td>{{ money((float) $invoice->tax_amount) }}</td></tr>
        @endif
        @if($withPayments)
            <tr class="grand"><td>Total TTC</td><td>{{ money((float) $invoice->amount) }}</td></tr>
            <tr class="sep"><td>Déjà payé</td><td>{{ money((float) $invoice->paid_amount) }}</td></tr>
            @if($invoice->status !== 'cancelled')
                <tr class="due"><td>Reste à payer</td><td>{{ money($remaining) }}</td></tr>
            @endif
        @else
            <tr class="due"><td>Total TTC</td><td>{{ money((float) $invoice->amount) }}</td></tr>
        @endif
    </table>
</section>
