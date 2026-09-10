@php
    $m = fn ($v) => number_format((float) $v, $data['decimals'] ?? 0, ',', ' ') . ' ' . ($data['currency_symbol'] ?? '');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 19px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 22px 0 8px; padding-bottom: 4px; border-bottom: 1px solid #cbd5e1; }
        .head { margin-bottom: 18px; }
        .muted { color: #6b7280; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 9px; text-transform: uppercase; color: #6b7280; padding: 5px 6px; border-bottom: 1px solid #e5e7eb; }
        td { padding: 5px 6px; border-bottom: 1px solid #f1f5f9; }
        td.num, th.num { text-align: right; }
        .total td { font-weight: bold; border-top: 1px solid #94a3b8; border-bottom: none; }
        .cols { width: 100%; }
        .cols td { border: none; vertical-align: top; padding: 0 8px 0 0; }
        .result { margin-top: 18px; padding: 10px 12px; background: #f1f5f9; }
        .result .big { font-size: 16px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="head">
        <h1>Bilan financier {{ $data['year'] }}</h1>
        <div class="muted">
            {{ $data['entreprise'] ?? '' }} — exercice du
            {{ \Carbon\Carbon::parse($data['from'])->format('d/m/Y') }} au
            {{ \Carbon\Carbon::parse($data['to'])->format('d/m/Y') }}<br>
            Document établi à partir du journal comptable, {{ $data['entry_count'] }} écriture(s).
            Édité le {{ now()->format('d/m/Y à H:i') }}.
        </div>
    </div>

    <h2>Bilan</h2>
    <table class="cols"><tr>
        <td width="50%">
            <table>
                <thead><tr><th colspan="2">Actif</th></tr>
                <tr><th>Compte</th><th class="num">Montant</th></tr></thead>
                <tbody>
                @forelse($data['balance_sheet']['assets'] as $l)
                    <tr><td>{{ $l['code'] }} {{ $l['name'] }}</td><td class="num">{{ $m($l['amount']) }}</td></tr>
                @empty
                    <tr><td colspan="2" class="muted">Aucun</td></tr>
                @endforelse
                    <tr class="total"><td>Total actif</td><td class="num">{{ $m($data['totals']['assets']) }}</td></tr>
                </tbody>
            </table>
        </td>
        <td width="50%">
            <table>
                <thead><tr><th colspan="2">Passif et capitaux propres</th></tr>
                <tr><th>Compte</th><th class="num">Montant</th></tr></thead>
                <tbody>
                @forelse($data['balance_sheet']['liabilities'] as $l)
                    <tr><td>{{ $l['code'] }} {{ $l['name'] }}</td><td class="num">{{ $m($l['amount']) }}</td></tr>
                @empty
                    <tr><td colspan="2" class="muted">Aucun</td></tr>
                @endforelse
                @foreach($data['balance_sheet']['equity'] as $l)
                    <tr><td>{{ $l['code'] }} {{ $l['name'] }}</td><td class="num">{{ $m($l['amount']) }}</td></tr>
                @endforeach
                    <tr><td>Résultat de l'exercice</td><td class="num">{{ $m($data['totals']['net_result']) }}</td></tr>
                    <tr class="total"><td>Total passif</td><td class="num">{{ $m($data['totals']['liabilities_and_equity']) }}</td></tr>
                </tbody>
            </table>
        </td>
    </tr></table>

    <h2>Compte de résultat</h2>
    <table>
        <thead><tr><th>Compte</th><th class="num">Montant</th></tr></thead>
        <tbody>
        @foreach($data['income_statement']['revenue'] as $l)
            <tr><td>{{ $l['code'] }} {{ $l['name'] }}</td><td class="num">{{ $m($l['amount']) }}</td></tr>
        @endforeach
            <tr class="total"><td>Total produits</td><td class="num">{{ $m($data['totals']['revenue']) }}</td></tr>
        @foreach($data['income_statement']['expenses'] as $l)
            <tr><td>{{ $l['code'] }} {{ $l['name'] }}</td><td class="num">{{ $m($l['amount']) }}</td></tr>
        @endforeach
            <tr class="total"><td>Total charges</td><td class="num">{{ $m($data['totals']['expenses']) }}</td></tr>
        </tbody>
    </table>

    <div class="result">
        <div class="muted">Résultat de l'exercice {{ $data['year'] }}</div>
        <div class="big">{{ $m($data['totals']['net_result']) }}
            <span class="muted">{{ $data['totals']['net_result'] >= 0 ? '(bénéfice)' : '(perte)' }}</span></div>
    </div>
</body>
</html>
