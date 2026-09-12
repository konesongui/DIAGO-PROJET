{{--
    Bilan annuel en PDF, rendu par dompdf : mise en page en tableaux (ni flexbox ni grille),
    couleurs écrites en dur (pas de variables CSS) et police DejaVu Sans, seule disponible.
    Variables : $report, $data (instantané calculé par AnnualReportService), $company.
--}}
@php
    $m = fn ($v) => number_format((float) $v, $data['decimals'] ?? 0, ',', ' ') . ' ' . ($data['currency_symbol'] ?? '');
    $totals = $data['totals'];
    $isProfit = $totals['net_result'] >= 0;
    $gap = round($totals['assets'] - $totals['liabilities_and_equity'], 2);

    $settings = $company?->settings ?? [];
    $companyName = $company?->name ?: ($data['entreprise'] ?? 'DIAGO');
    $logo = data_get($settings, 'logo');
    $logoPath = $logo && \Illuminate\Support\Facades\Storage::disk('public')->exists($logo)
        ? \Illuminate\Support\Facades\Storage::disk('public')->path($logo) : null;
    $contactLines = array_filter([
        data_get($settings, 'address'),
        collect([data_get($settings, 'phone') ? 'Tél : ' . data_get($settings, 'phone') : null, data_get($settings, 'email')])->filter()->implode(' · '),
    ]);
    $legalLines = array_filter([
        data_get($settings, 'legal_form'),
        data_get($settings, 'nccm_rccm') ? 'RCCM : ' . data_get($settings, 'nccm_rccm') : null,
        data_get($settings, 'taxpayer_account') ? 'Compte contribuable : ' . data_get($settings, 'taxpayer_account') : null,
        data_get($settings, 'tax_center') ? 'Centre des impôts : ' . data_get($settings, 'tax_center') : null,
    ]);
    $kpis = [
        ['Total actif', $totals['assets'], '#2563eb'],
        ['Passif et capitaux', $totals['liabilities_and_equity'], '#4f46e5'],
        ['Produits', $totals['revenue'], '#059669'],
        ['Charges', $totals['expenses'], '#ea580c'],
    ];
    $from = \Carbon\Carbon::parse($data['from'])->format('d/m/Y');
    $to = \Carbon\Carbon::parse($data['to'])->format('d/m/Y');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Bilan financier {{ $data['year'] }}</title>
<style>
    @page { margin: 14mm 13mm 20mm; }
    body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 9.5px; line-height: 1.45; color: #172033; }
    table { width: 100%; border-collapse: collapse; }
    .muted { color: #5b6478; }

    .bar { height: 5px; background: #273772; }
    .bar-accent { width: 38%; height: 3px; background: #FADF2F; margin-bottom: 12px; }

    .head td { vertical-align: top; }
    .company-name { font-size: 14px; font-weight: bold; color: #273772; }
    .head .muted { font-size: 8.5px; }
    .doc-title { text-align: right; font-size: 20px; font-weight: bold; letter-spacing: 1px; color: #273772; }
    .doc-sub { text-align: right; font-size: 11px; font-weight: bold; }
    .doc-meta { text-align: right; font-size: 8.5px; color: #5b6478; }
    .rule { height: 1px; background: #e3e7f0; margin: 10px 0 12px; }

    .kpis { margin-bottom: 14px; }
    .kpis td { width: 25%; padding: 0 4px; vertical-align: top; }
    .kpis td.first { padding-left: 0; }
    .kpis td.last { padding-right: 0; }
    .kpi { padding: 8px 10px; border: 1px solid #e3e7f0; border-radius: 6px; }
    .kpi-label { font-size: 8px; text-transform: uppercase; letter-spacing: .5px; color: #5b6478; }
    .kpi-dot { display: inline-block; width: 7px; height: 7px; border-radius: 2px; margin-right: 4px; }
    .kpi-value { margin-top: 3px; font-size: 12px; font-weight: bold; }

    h2 { margin: 0 0 6px; padding-left: 7px; border-left: 3px solid #273772; font-size: 11.5px; color: #273772; }
    .section { margin-bottom: 12px; }
    .cols td.col { width: 49%; vertical-align: top; padding: 0; }
    .cols td.gap { width: 2%; }

    .lines th { padding: 5px 7px; background: #273772; color: #fff; font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: .4px; text-align: left; }
    .lines td { padding: 4px 7px; border-bottom: 1px solid #eef1f6; }
    .lines .num { text-align: right; white-space: nowrap; }
    .lines .code { color: #8a93a6; margin-right: 4px; }
    .lines tr.total td { padding-top: 6px; padding-bottom: 6px; border-top: 1.5px solid #273772; border-bottom: 0; background: #eef1f8; font-weight: bold; color: #273772; }
    .lines tr.net td { font-style: italic; }
    .lines td.empty { color: #8a93a6; text-align: center; padding: 8px; }

    .check { margin-top: 6px; font-size: 8.5px; }
    .check-ok { color: #059669; }
    .check-gap { color: #d97706; }

    .result { margin-top: 4px; padding: 10px 14px; border: 1.5px solid; border-radius: 8px; }
    .result-label { font-size: 8.5px; text-transform: uppercase; letter-spacing: .5px; color: #5b6478; }
    .result-amount { font-size: 18px; font-weight: bold; }
    .pill { display: inline-block; margin-left: 8px; padding: 2px 9px; border-radius: 9px; font-size: 8.5px; font-weight: bold; vertical-align: middle; }

    .footer { position: fixed; bottom: -12mm; left: 0; right: 0; height: 10mm; padding-top: 4px; border-top: 1px solid #e3e7f0; text-align: center; font-size: 7.5px; color: #5b6478; }
    .footer strong { color: #172033; }
    .pagenum:after { content: counter(page); }
</style>
</head>
<body>
    <div class="footer">
        <strong>{{ $companyName }}</strong>@if($legalLines) · {{ implode(' · ', $legalLines) }}@endif<br>
        Établi à partir du journal comptable ({{ $data['entry_count'] }} écriture(s)) · édité le {{ now()->format('d/m/Y à H:i') }} · page <span class="pagenum"></span>
    </div>

    <div class="bar"></div>
    <div class="bar-accent"></div>

    <table class="head">
        <tr>
            @if($logoPath)<td style="width:58px"><img src="{{ $logoPath }}" style="max-width:50px; max-height:50px" alt=""></td>@endif
            <td>
                <div class="company-name">{{ $companyName }}</div>
                @foreach($contactLines as $line)<div class="muted">{{ $line }}</div>@endforeach
            </td>
            <td style="width:45%">
                <div class="doc-title">BILAN FINANCIER</div>
                <div class="doc-sub">Exercice {{ $data['year'] }}</div>
                <div class="doc-meta">du {{ $from }} au {{ $to }}</div>
            </td>
        </tr>
    </table>
    <div class="rule"></div>

    <table class="kpis">
        <tr>
            @foreach($kpis as [$label, $value, $color])
                <td class="{{ $loop->first ? 'first' : ($loop->last ? 'last' : '') }}">
                    <div class="kpi">
                        <div class="kpi-label"><span class="kpi-dot" style="background:{{ $color }}"></span>{{ $label }}</div>
                        <div class="kpi-value">{{ $m($value) }}</div>
                    </div>
                </td>
            @endforeach
        </tr>
    </table>

    <div class="section">
        <h2>Bilan au {{ $to }}</h2>
        <table class="cols">
            <tr>
                <td class="col">
                    <table class="lines">
                        <thead><tr><th>Actif</th><th class="num">Montant</th></tr></thead>
                        <tbody>
                        @forelse($data['balance_sheet']['assets'] as $l)
                            <tr><td><span class="code">{{ $l['code'] }}</span>{{ $l['name'] }}</td><td class="num">{{ $m($l['amount']) }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="empty">Aucun compte d’actif mouvementé</td></tr>
                        @endforelse
                            <tr class="total"><td>Total actif</td><td class="num">{{ $m($totals['assets']) }}</td></tr>
                        </tbody>
                    </table>
                </td>
                <td class="gap"></td>
                <td class="col">
                    <table class="lines">
                        <thead><tr><th>Passif et capitaux propres</th><th class="num">Montant</th></tr></thead>
                        <tbody>
                        @foreach($data['balance_sheet']['liabilities'] as $l)
                            <tr><td><span class="code">{{ $l['code'] }}</span>{{ $l['name'] }}</td><td class="num">{{ $m($l['amount']) }}</td></tr>
                        @endforeach
                        @foreach($data['balance_sheet']['equity'] as $l)
                            <tr><td><span class="code">{{ $l['code'] }}</span>{{ $l['name'] }}</td><td class="num">{{ $m($l['amount']) }}</td></tr>
                        @endforeach
                            <tr class="net"><td>Résultat de l’exercice</td><td class="num">{{ $m($totals['net_result']) }}</td></tr>
                            <tr class="total"><td>Total passif</td><td class="num">{{ $m($totals['liabilities_and_equity']) }}</td></tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
        @if(abs($gap) < 0.01)
            <div class="check check-ok">Bilan équilibré : le total de l’actif est égal au total du passif.</div>
        @else
            <div class="check check-gap">Écart de {{ $m(abs($gap)) }} entre l’actif et le passif : vérifiez les écritures de l’exercice.</div>
        @endif
    </div>

    <div class="section">
        <h2>Compte de résultat</h2>
        <table class="cols">
            <tr>
                <td class="col">
                    <table class="lines">
                        <thead><tr><th>Produits</th><th class="num">Montant</th></tr></thead>
                        <tbody>
                        @forelse($data['income_statement']['revenue'] as $l)
                            <tr><td><span class="code">{{ $l['code'] }}</span>{{ $l['name'] }}</td><td class="num">{{ $m($l['amount']) }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="empty">Aucun produit</td></tr>
                        @endforelse
                            <tr class="total"><td>Total produits</td><td class="num">{{ $m($totals['revenue']) }}</td></tr>
                        </tbody>
                    </table>
                </td>
                <td class="gap"></td>
                <td class="col">
                    <table class="lines">
                        <thead><tr><th>Charges</th><th class="num">Montant</th></tr></thead>
                        <tbody>
                        @forelse($data['income_statement']['expenses'] as $l)
                            <tr><td><span class="code">{{ $l['code'] }}</span>{{ $l['name'] }}</td><td class="num">{{ $m($l['amount']) }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="empty">Aucune charge</td></tr>
                        @endforelse
                            <tr class="total"><td>Total charges</td><td class="num">{{ $m($totals['expenses']) }}</td></tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <div class="result" style="border-color:{{ $isProfit ? '#059669' : '#dc2626' }}; background:{{ $isProfit ? '#f0faf6' : '#fdf2f2' }}">
        <div class="result-label">Résultat de l’exercice {{ $data['year'] }}</div>
        <div class="result-amount" style="color:{{ $isProfit ? '#059669' : '#dc2626' }}">
            {{ $m($totals['net_result']) }}
            <span class="pill" style="background:{{ $isProfit ? '#d9f2e8' : '#fbdada' }}; color:{{ $isProfit ? '#047857' : '#b91c1c' }}">{{ $isProfit ? 'Bénéfice' : 'Perte' }}</span>
        </div>
        <div class="muted" style="font-size:8.5px">Total produits {{ $m($totals['revenue']) }} − total charges {{ $m($totals['expenses']) }}</div>
    </div>
</body>
</html>
