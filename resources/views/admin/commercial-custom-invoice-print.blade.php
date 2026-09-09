<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture personnalisée {{ $invoice->reference }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #1f2937; margin: 40px; }
        .header { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .title { font-size: 28px; font-weight: bold; }
        .meta { width: 260px; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #d1d5db; padding: 10px; text-align: left; }
        th { background: #f3f4f6; }
        .totals { margin-top: 30px; width: 320px; margin-left: auto; }
        .totals div { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .total-row { font-weight: bold; font-size: 18px; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="title">Facture personnalisée</div>
            <div><strong>Référence :</strong> {{ $invoice->reference }}</div>
        </div>
        <div class="meta">
            <div><strong>Date :</strong> {{ optional($invoice->quote_date)->format('d/m/Y') ?: '-' }}</div>
            <div><strong>Échéance :</strong> {{ optional($invoice->valid_until)->format('d/m/Y') ?: '-' }}</div>
            <div><strong>Client :</strong> {{ $invoice->client_name ?: 'Client' }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Article</th>
                <th>Qté</th>
                <th>PU</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
        @foreach($invoice->items ?? [] as $item)
            <tr>
                <td>{{ $item['item_name'] ?? 'Article' }}</td>
                <td>{{ $item['quantity'] ?? 0 }}</td>
                <td>{{ number_format((float) ($item['price'] ?? 0), 0, ',', ' ') }} CFA</td>
                <td>{{ number_format(((float) ($item['quantity'] ?? 0)) * ((float) ($item['price'] ?? 0)), 0, ',', ' ') }} CFA</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div><span>Total HT</span><span>{{ number_format($invoice->total_ht, 0, ',', ' ') }} CFA</span></div>
        <div><span>Remise</span><span>{{ number_format($invoice->total_discount, 0, ',', ' ') }} CFA</span></div>
        <div><span>TVA</span><span>{{ number_format($invoice->vat_amount, 0, ',', ' ') }} CFA</span></div>
        <div class="total-row"><span>Total TTC</span><span>{{ number_format($invoice->total_ttc, 0, ',', ' ') }} CFA</span></div>
    </div>
</body>
</html>
