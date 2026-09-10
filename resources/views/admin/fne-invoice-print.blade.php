<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture FNE {{ $invoice->fne_reference }}</title>
    <style>
        @page{size:A4;margin:1.5cm}*{box-sizing:border-box}body{font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#000;line-height:1.4}.company-frame{border:2px solid #000;padding:8px 12px;margin-bottom:10px;display:inline-block;font-weight:bold;font-size:14px;line-height:1.5}.header{display:flex;justify-content:space-between;margin-bottom:12px}.company-info,.client-info{width:48%}.title{font-size:15px;font-weight:bold;margin-bottom:12px}.badge{background:#198754;color:#fff;padding:6px 10px;border-radius:4px;font-weight:bold;display:inline-block;margin-top:10px}.rule{border:0;border-top:1px solid #000;margin:10px 0}.items,.summary,.totals{width:100%;border-collapse:collapse;margin-top:15px}.items{border:1px solid #000}.items th,.items td,.summary th,.summary td,.totals td{border:1px solid #000;padding:7px 5px}.items th,.summary th{background:#f2f2f2;text-align:left}.right{text-align:right}.totals{width:40%;margin-left:auto}.totals td:first-child{font-weight:bold}.fne-box{border:2px solid #198754;margin-top:18px;padding:10px;line-height:1.7}.fne-box strong{color:#198754}.verification{margin-top:14px;border:1px dashed #198754;padding:10px}.footer{margin-top:28px;border-top:1px solid #000;padding-top:8px;font-size:10px;display:flex;justify-content:space-between}@media print{.no-print{display:none}}
    </style>
</head>
<body>
    <div class="company-frame">
        {{ $company?->name ?: 'Diagoma ERP' }}<br>
        NCC : {{ data_get($company?->settings, 'ncc') ?: '-' }}<br>
        Régime d'imposition : {{ data_get($company?->settings, 'regime_imposition') ?: '-' }}<br>
        Centre des impôts : {{ data_get($company?->settings, 'centre_impot') ?: '-' }}
    </div>

    <div class="header">
        <div class="company-info">
            <strong>Informations société</strong><br>
            RCCM : {{ data_get($company?->settings, 'rccm') ?: '-' }}<br>
            Adresse : {{ data_get($company?->settings, 'address') ?: '-' }}<br>
            Téléphone : {{ data_get($company?->settings, 'phone') ?: '-' }}<br>
            Email : {{ data_get($company?->settings, 'email') ?: '-' }}<br><br>
            <strong>Informations facture</strong><br>
            Date : {{ optional($invoice->fne_certified_at ?: $invoice->created_at)->format('d/m/Y H:i') }}<br>
            Type : Facture {{ $documentType }}
        </div>
        <div class="client-info">
            <div class="title">FACTURE {{ strtoupper($documentType) }} N° {{ $invoice->fne_reference }}</div>
            <strong>{{ $documentType === 'Vente' ? 'Client' : 'Fournisseur' }}</strong><br>
            {{ $documentType === 'Vente' ? ($client?->name ?: $invoice->client_name) : ($invoice->supplier_name ?: '-') }}<br>
            {{ $documentType === 'Vente' ? ($client?->address ?: '-') : 'NCC / NIF : '.($invoice->supplier_tax_id ?: '-') }}<br>
            @if($documentType === 'Vente')Téléphone : {{ $client?->phone ?: '-' }}<br>NCC : {{ $client?->tax_id ?: '-' }}@endif
            <div class="badge">✓ CERTIFIÉE FNE</div>
        </div>
    </div>

    @if($documentType === 'Vente')
        <table class="items"><thead><tr><th>Réf</th><th>Désignation</th><th>P.U HT</th><th>Qté</th><th>Unité</th><th>Montant HT</th></tr></thead><tbody>
        @foreach(($invoice->delivery?->lines ?? []) as $line)
            <tr><td>{{ $line['item_reference'] ?? '-' }}</td><td>{{ $line['item_name'] ?? '-' }}</td><td class="right">{{ number_format((float)($line['unit_price'] ?? 0),0,',',' ') }}</td><td>{{ $line['quantity'] ?? 0 }}</td><td>{{ $line['unit'] ?? 'pcs' }}</td><td class="right">{{ number_format((float)($line['quantity'] ?? 0)*(float)($line['unit_price'] ?? 0),0,',',' ') }}</td></tr>
        @endforeach
        </tbody></table>
        <table class="totals"><tr><td>Total TTC</td><td class="right">{{ money((float)$invoice->amount) }}</td></tr></table>
    @else
        <table class="items"><thead><tr><th>Description</th><th>Référence</th><th>Qté</th><th>Prix unitaire</th><th>Total</th></tr></thead><tbody>
        @foreach(($invoice->extracted_data['items'] ?? []) as $item)
            <tr><td>{{ $item['description'] ?? $item['name'] ?? '-' }}</td><td>{{ $item['reference'] ?? '-' }}</td><td>{{ $item['quantity'] ?? 1 }}</td><td class="right">{{ number_format((float)($item['unit_price'] ?? $item['amount'] ?? 0),0,',',' ') }} {{ $invoice->currency }}</td><td class="right">{{ number_format((float)($item['total'] ?? (($item['quantity'] ?? 1)*($item['unit_price'] ?? $item['amount'] ?? 0))),0,',',' ') }} {{ $invoice->currency }}</td></tr>
        @endforeach
        </tbody></table>
        <table class="totals"><tr><td>Total HT</td><td class="right">{{ number_format((float)($invoice->total_ht ?? 0),0,',',' ') }} {{ $invoice->currency }}</td></tr><tr><td>Total TTC</td><td class="right">{{ number_format((float)($invoice->total_amount ?? 0),0,',',' ') }} {{ $invoice->currency }}</td></tr></table>
    @endif

    <div class="fne-box"><strong>DOCUMENT CERTIFIÉ ÉLECTRONIQUEMENT PAR LA FNE</strong><br>Référence FNE : {{ $invoice->fne_reference }}<br>Date de certification : {{ optional($invoice->fne_certified_at)->format('d/m/Y H:i') ?: '-' }}<br>Code de vérification : {{ $invoice->fne_token ?: '-' }}<br>Sticker / solde FNE : {{ $invoice->fne_balance_sticker ?? '-' }}</div>
    <div class="verification">Conservez cette facture et utilisez la référence FNE pour toute vérification auprès de l'administration fiscale.</div>
    <div class="footer"><span>{{ $company?->name ?: 'Diagoma ERP' }}<br>{{ data_get($company?->settings, 'address') ?: '-' }}</span><span>Référence FNE : {{ $invoice->fne_reference }}<br>Document certifié électroniquement</span></div>
    <script>window.onload=function(){window.print();};</script>
</body>
</html>
