<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture fournisseur {{ $invoice->invoice_number ?: $invoice->id }}</title>
    <style>
        @page{margin:18mm 16mm}*{box-sizing:border-box}body{font-family:Arial,Helvetica,sans-serif;color:#555;font-size:12px;margin:0}.header{border-bottom:1px solid #aaa;padding:8px 0 14px;margin-bottom:22px}.company{float:left;width:55%}.company h2{margin:0 0 8px;color:#17233d;font-size:20px}.company p{line-height:1.55;margin:0}.document{float:right;width:42%;text-align:right}.document h1{color:#0087c3;font-size:25px;font-weight:400;margin:0 0 10px}.document p{line-height:1.6;margin:0}.clearfix:after{content:"";display:table;clear:both}.supplier{border-left:6px solid #0087c3;padding:7px 0 7px 12px;line-height:1.55;margin-bottom:18px}.supplier strong{font-size:15px;color:#222}.items{width:100%;border-collapse:collapse;margin-top:18px}.items th{background:#eee;padding:11px 8px;text-align:left;font-weight:400}.items td{background:#f7f7f7;padding:10px 8px;border-bottom:1px solid white}.right{text-align:right;white-space:nowrap}.totals{width:42%;margin:18px 0 0 auto;border-collapse:collapse}.totals td{padding:8px 10px;text-align:right;border-top:1px solid #ddd}.totals tr:last-child td{color:#168b47;font-size:16px;border-top:1px solid #168b47}.fne{margin-top:22px;border:1px solid #168b47;padding:12px;line-height:1.7}.fne strong{color:#168b47}.footer{border-top:1px solid #aaa;color:#777;text-align:center;margin-top:38px;padding-top:10px;font-size:10px}@media print{.no-print{display:none}}
    </style>
</head>
<body>
    <header class="header clearfix">
        <div class="company"><h2>{{ $company?->name ?: 'Diagoma ERP' }}</h2><p>{{ data_get($company?->settings, 'address') ?: '-' }}<br>Tél : {{ data_get($company?->settings, 'phone') ?: '-' }}<br>Email : {{ data_get($company?->settings, 'email') ?: '-' }}<br>NIF : {{ data_get($company?->settings, 'nif') ?: '-' }}</p></div>
        <div class="document"><h1>FACTURE FOURNISSEUR</h1><p>N° : {{ $invoice->invoice_number ?: $invoice->id }}<br>Date : {{ optional($invoice->invoice_date)->format('d/m/Y') ?: '-' }}</p></div>
    </header>
    <div class="supplier"><strong>Fournisseur</strong><br>{{ $invoice->supplier_name ?: 'À vérifier' }}<br>NCC / NIF : {{ $invoice->supplier_tax_id ?: '-' }}</div>
    <table class="items"><thead><tr><th>Description</th><th>Référence</th><th>Qté</th><th>Prix unitaire</th><th>Total</th></tr></thead><tbody>
    @foreach(($invoice->extracted_data['items'] ?? []) as $item)
        <tr><td>{{ $item['description'] ?? $item['name'] ?? '-' }}</td><td>{{ $item['reference'] ?? '-' }}</td><td>{{ $item['quantity'] ?? 1 }}</td><td class="right">{{ number_format((float)($item['unit_price'] ?? $item['amount'] ?? 0), 0, ',', ' ') }} {{ $invoice->currency }}</td><td class="right">{{ number_format((float)($item['total'] ?? (($item['quantity'] ?? 1) * ($item['unit_price'] ?? $item['amount'] ?? 0))), 0, ',', ' ') }} {{ $invoice->currency }}</td></tr>
    @endforeach
    @if(empty($invoice->extracted_data['items']))<tr><td colspan="5">Aucune ligne détaillée extraite du PDF.</td></tr>@endif
    </tbody></table>
    <table class="totals"><tr><td>Total HT</td><td>{{ $invoice->total_ht !== null ? number_format((float)$invoice->total_ht, 0, ',', ' ').' '.$invoice->currency : '-' }}</td></tr><tr><td>Taxe</td><td>{{ $invoice->tax_amount !== null ? number_format((float)$invoice->tax_amount, 0, ',', ' ').' '.$invoice->currency : '-' }}</td></tr><tr><td><strong>Total TTC</strong></td><td><strong>{{ $invoice->total_amount !== null ? number_format((float)$invoice->total_amount, 0, ',', ' ').' '.$invoice->currency : '-' }}</strong></td></tr></table>
    @if($invoice->fne_status === 'certified')<div class="fne"><strong>FACTURE CERTIFIÉE FNE</strong><br>Référence FNE : {{ $invoice->fne_reference ?: '-' }}<br>Date de certification : {{ optional($invoice->fne_certified_at)->format('d/m/Y H:i') ?: '-' }}<br>Sticker / solde FNE : {{ $invoice->fne_balance_sticker ?? '-' }}</div>@endif
    <footer class="footer">{{ $company?->name ?: 'Diagoma ERP' }} – Document généré le {{ now()->format('d/m/Y à H:i') }}</footer><script>window.print()</script>
</body>
</html>
