{{--
    Lignes et totaux d'une facture fournisseur imprimée, tels que lus dans son PDF.
    Variables : $invoice (SupplierInvoice) ; $withNotes (vrai par défaut).
--}}
@php
    $withNotes ??= true;
    // Devise de la facture : symbole de l'entreprise si identique, sinon code lu dans le PDF.
    $currency = ! $invoice->currency || $invoice->currency === company_currency()['code'] ? currency_symbol() : $invoice->currency;
    $amount = fn ($value) => $value !== null ? number_format((float) $value, 0, ',', ' ') . ' ' . $currency : 'À vérifier';
    $items = collect($invoice->extracted_data['items'] ?? []);
    $itemPrice = fn ($item) => (float) ($item['unit_price'] ?? $item['amount'] ?? 0);
    $itemTotal = fn ($item) => (float) ($item['total'] ?? ((float) ($item['quantity'] ?? 1) * $itemPrice($item)));
    $regimeUnconfirmed = in_array($invoice->tax_regime, [null, '', 'unknown'], true);
@endphp

<table class="items">
    <thead>
        <tr>
            <th>Description</th>
            <th>Référence</th>
            <th class="center">Qté</th>
            <th class="num">Prix unitaire</th>
            <th class="num">Total</th>
        </tr>
    </thead>
    <tbody>
    @forelse($items as $item)
        <tr>
            <td class="strong">{{ $item['description'] ?? $item['name'] ?? '—' }}</td>
            <td>{{ $item['reference'] ?? '—' }}</td>
            <td class="center">{{ rtrim(rtrim(number_format((float) ($item['quantity'] ?? 1), 2, ',', ' '), '0'), ',') }}</td>
            <td class="num">{{ $amount($itemPrice($item)) }}</td>
            <td class="num strong">{{ $amount($itemTotal($item)) }}</td>
        </tr>
    @empty
        <tr><td colspan="5" class="center">Aucune ligne détaillée n’a été extraite du PDF : seuls les totaux sont connus.</td></tr>
    @endforelse
    </tbody>
</table>

<section class="summary">
    <div class="notes">
        @if($withNotes)
            <h3>Justificatif</h3>
            <p>Document établi à partir de la facture PDF « {{ $invoice->original_filename ?: 'sans nom' }} »
                importée le {{ $invoice->created_at?->format('d/m/Y') ?: '—' }}. Les montants sont ceux lus dans le PDF.</p>
            @if($regimeUnconfirmed)
                <h3>Régime fiscal à confirmer</h3>
                <p>Le régime fiscal de cette facture n’a pas encore été confirmé ; il se confirme sur la fiche de la facture, et la déclaration de TVA la signale en attendant.</p>
            @endif
        @endif
    </div>
    <table class="totals">
        @if($invoice->subtotal !== null && $invoice->total_ht !== null && (float) $invoice->subtotal !== (float) $invoice->total_ht)
            <tr><td>Sous-total</td><td>{{ $amount($invoice->subtotal) }}</td></tr>
        @endif
        <tr><td>Total HT</td><td>{{ $amount($invoice->total_ht ?? $invoice->subtotal) }}</td></tr>
        <tr><td>TVA</td><td>{{ $amount($invoice->tax_amount) }}</td></tr>
        <tr class="due"><td>Total TTC</td><td>{{ $amount($invoice->total_amount) }}</td></tr>
    </table>
</section>
