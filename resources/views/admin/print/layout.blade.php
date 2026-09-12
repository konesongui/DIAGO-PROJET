{{--
    Mise en page commune des documents imprimables (charte Diago).
    Variables : $company (Entreprise). Sections : title, doc-title, doc-meta, content.
    La page s'affiche comme une feuille A4 et ouvre la boîte d'impression une fois les polices chargées.
--}}
@php
    $settings = $company?->settings ?? [];
    $companyName = $company?->name ?: 'DIAGO';
    $logo = data_get($settings, 'logo');
    $contactLines = array_filter([
        data_get($settings, 'address'),
        // La boîte postale est souvent saisie avec son préfixe « BP ».
        data_get($settings, 'po_box') ? (preg_match('/\bB\.?P\b/i', data_get($settings, 'po_box')) ? data_get($settings, 'po_box') : 'BP ' . data_get($settings, 'po_box')) : null,
        collect([data_get($settings, 'phone') ? 'Tél : ' . data_get($settings, 'phone') : null, data_get($settings, 'email')])->filter()->implode(' · '),
        data_get($settings, 'website'),
    ]);
    // Mentions légales du pied de page, dans l'ordre d'usage sur une facture ivoirienne.
    $legalLines = array_filter([
        data_get($settings, 'legal_form'),
        data_get($settings, 'nccm_rccm') ? 'RCCM : ' . data_get($settings, 'nccm_rccm') : null,
        data_get($settings, 'taxpayer_account') ? 'Compte contribuable : ' . data_get($settings, 'taxpayer_account') : null,
        data_get($settings, 'tax_regime') ? 'Régime : ' . data_get($settings, 'tax_regime') : null,
        data_get($settings, 'tax_center') ? 'Centre des impôts : ' . data_get($settings, 'tax_center') : null,
    ]);
@endphp
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title')</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    @page { size: A4; margin: 12mm 12mm 14mm; }
    :root {
        --navy: #273772; --yellow: #FADF2F; --text: #172033; --muted: #5b6478; --line: #e3e7f0; --soft: #F4F6FB;
        --green: #059669; --orange: #d97706; --red: #dc2626;
    }
    * { box-sizing: border-box; }
    html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    body { margin: 0; background: #e9ecf3; color: var(--text); font: 400 12px/1.55 'Poppins', Arial, Helvetica, sans-serif; }
    .sheet { width: 210mm; min-height: 297mm; margin: 24px auto; padding: 12mm; background: #fff; box-shadow: 0 10px 30px rgba(23, 32, 51, .12); position: relative; }
    .sheet::before { content: ""; position: absolute; inset: 0 0 auto; height: 6px; background: var(--navy); }
    .sheet::after { content: ""; position: absolute; top: 6px; left: 0; width: 38%; height: 3px; background: var(--yellow); }

    .toolbar { position: sticky; top: 0; z-index: 2; display: flex; justify-content: center; gap: 10px; padding: 12px; background: var(--navy); }
    .toolbar button { display: inline-flex; align-items: center; gap: 8px; min-height: 38px; padding: 0 18px; border: 1px solid rgba(255,255,255,.35); border-radius: 10px; background: transparent; color: #fff; font: 600 13px 'Poppins', Arial, sans-serif; cursor: pointer; }
    .toolbar .primary { background: var(--yellow); border-color: var(--yellow); color: var(--text); }

    .doc-header { display: flex; justify-content: space-between; gap: 24px; padding-bottom: 14px; border-bottom: 1px solid var(--line); }
    .company { display: flex; gap: 14px; align-items: flex-start; max-width: 58%; }
    .company img { max-width: 64px; max-height: 64px; object-fit: contain; }
    .company .company__name { margin: 0 0 4px; font-size: 17px; font-weight: 700; color: var(--navy); line-height: 1.25; }
    .company p { margin: 0; color: var(--muted); font-size: 11px; }
    .doc-id { text-align: right; }
    .doc-id h1 { margin: 0; font-size: 26px; font-weight: 700; letter-spacing: .04em; color: var(--navy); line-height: 1.1; }
    .doc-id .number { margin: 4px 0 8px; font-size: 14px; font-weight: 600; }
    .doc-id p { margin: 0; color: var(--muted); font-size: 11px; }

    .pill { display: inline-block; margin-top: 8px; padding: 3px 12px; border-radius: 999px; font-size: 10.5px; font-weight: 600; }
    .pill--success { background: #e6f6f0; color: var(--green); }
    .pill--warning { background: #fdf3e2; color: var(--orange); }
    .pill--danger { background: #fdeaea; color: var(--red); }
    .pill--neutral { background: var(--soft); color: var(--muted); }

    .parties { display: flex; gap: 14px; margin: 16px 0; }
    .box { flex: 1; padding: 12px 14px; border: 1px solid var(--line); border-radius: 10px; }
    .box--accent { border-left: 4px solid var(--navy); }
    .box h2 { margin: 0 0 6px; font-size: 10px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }
    .box strong { display: block; font-size: 13.5px; color: var(--text); }
    .box p { margin: 0; font-size: 11px; }
    .box dl { display: grid; grid-template-columns: auto 1fr; gap: 3px 12px; margin: 0; font-size: 11px; }
    .box dt { color: var(--muted); }
    .box dd { margin: 0; font-weight: 500; }

    table.items { width: 100%; border-collapse: separate; border-spacing: 0; }
    .items th { padding: 9px 10px; background: var(--navy); color: #fff; font-size: 10px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; text-align: left; }
    .items th:first-child { border-radius: 8px 0 0 8px; }
    .items th:last-child { border-radius: 0 8px 8px 0; }
    /* Recouvre la fine couture que Chrome laisse entre deux cellules colorées dans le PDF. */
    .items th + th, .totals .due td + td { box-shadow: -1px 0 0 var(--navy); }
    .items td { padding: 8px 10px; border-bottom: 1px solid var(--line); vertical-align: top; }
    .items tbody tr:nth-child(even) td { background: #fafbfd; }
    .items .muted { display: block; color: var(--muted); font-size: 10.5px; }
    .items .specs { display: block; margin-top: 3px; font-size: 10px; line-height: 1.5; color: var(--muted); }
    .items .specs > span { white-space: nowrap; }
    .items .specs > span:not(:last-child)::after { content: '·'; margin: 0 1px 0 5px; }
    .items .specs b { font-weight: 600; color: var(--navy); }
    .num { text-align: right !important; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .center { text-align: center !important; }
    .strong { font-weight: 600; }

    .summary { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; margin-top: 14px; break-inside: avoid; }
    .notes { flex: 1; font-size: 11px; color: var(--muted); }
    .notes h3 { margin: 0 0 4px; font-size: 11px; font-weight: 600; color: var(--text); }
    .notes p { margin: 0 0 8px; }
    table.totals { width: 46%; border-collapse: separate; border-spacing: 0; }
    .totals td { padding: 5px 12px; }
    .totals td:last-child { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .totals .sep td { border-top: 1px solid var(--line); }
    .totals .grand td { padding-top: 9px; font-size: 14px; font-weight: 700; color: var(--navy); border-top: 2px solid var(--navy); }
    .totals .due td { background: var(--navy); color: #fff; font-size: 13px; font-weight: 700; }
    .totals .due td:first-child { border-radius: 8px 0 0 8px; }
    .totals .due td:last-child { border-radius: 0 8px 8px 0; color: var(--yellow); }

    .callout { margin-top: 14px; padding: 12px 14px; border: 1px solid var(--green); border-left-width: 4px; border-radius: 10px; font-size: 11px; break-inside: avoid; }
    .callout strong { color: var(--green); letter-spacing: .04em; }

    .fiscal-frame { margin-top: 14px; padding: 10px 14px; border: 2px solid var(--text); border-radius: 6px; font-size: 11px; }
    .fiscal-frame strong { display: block; margin-bottom: 2px; font-size: 13px; }
    .fiscal-frame dl { display: grid; grid-template-columns: repeat(2, auto 1fr); gap: 2px 12px; margin: 0; }
    .fiscal-frame dt { color: var(--muted); }
    .fiscal-frame dd { margin: 0; font-weight: 600; }
    .certification { display: flex; gap: 18px; align-items: center; margin-top: 14px; padding: 12px 16px; border: 2px solid var(--green); border-radius: 10px; break-inside: avoid; }
    .certification__body { flex: 1; font-size: 11px; }
    .certification__body h3 { display: flex; align-items: center; gap: 8px; margin: 0 0 6px; font-size: 12px; font-weight: 700; letter-spacing: .04em; color: var(--green); }
    .certification__body dl { display: grid; grid-template-columns: auto 1fr; gap: 2px 12px; margin: 0 0 8px; }
    .certification__body dt { color: var(--muted); }
    .certification__body dd { margin: 0; font-weight: 600; word-break: break-all; }
    .certification__body p { margin: 0; color: var(--muted); }
    .certification__qr { text-align: center; font-size: 9.5px; color: var(--muted); }
    .certification__qr div { width: 112px; height: 112px; margin: 0 auto 4px; }
    .certification__qr img, .certification__qr canvas { width: 112px !important; height: 112px !important; }
    .pill svg { width: 11px; height: 11px; margin-right: 4px; vertical-align: -1px; }
    .doc-footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid var(--line); text-align: center; color: var(--muted); font-size: 9.5px; }
    .doc-footer strong { color: var(--text); }

    @media print {
        body { background: #fff; }
        .toolbar { display: none; }
        .sheet { width: auto; min-height: 0; margin: 0; padding: 4mm 0 0; box-shadow: none; }
    }
    @media screen and (max-width: 820px) {
        .sheet { width: auto; margin: 0; padding: 20px 16px; }
        .doc-header, .parties, .summary { flex-direction: column; }
        .company { max-width: none; }
        .doc-id { text-align: left; }
        table.totals { width: 100%; }
    }
</style>
</head>
<body>
<div class="toolbar">
    <button type="button" class="primary" onclick="window.print()">Imprimer</button>
    <button type="button" onclick="window.close()">Fermer</button>
</div>
<main class="sheet">
    <header class="doc-header">
        <div class="company">
            @if($logo)<img src="{{ asset('storage/' . ltrim($logo, '/')) }}" alt="">@endif
            <div>
                <p class="company__name">{{ $companyName }}</p>
                @foreach($contactLines as $line)<p>{{ $line }}</p>@endforeach
            </div>
        </div>
        <div class="doc-id">
            <h1>@yield('doc-title')</h1>
            @yield('doc-meta')
        </div>
    </header>

    @yield('content')

    <footer class="doc-footer">
        <strong>{{ $companyName }}</strong>@if($legalLines) · {{ implode(' · ', $legalLines) }}@endif
        <br>Document généré le {{ now()->format('d/m/Y à H:i') }}
    </footer>
</main>
@stack('scripts')
<script>
    // Impression automatique, une fois la police chargée pour que l'aperçu soit fidèle.
    window.addEventListener('load', function () {
        (document.fonts ? document.fonts.ready : Promise.resolve()).then(function () { window.print(); });
    });
</script>
</body>
</html>
