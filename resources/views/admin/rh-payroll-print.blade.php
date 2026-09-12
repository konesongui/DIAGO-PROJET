@php
    // Bulletin de paie imprimable (PDF). Mise en page en tableaux : le moteur
    // PDF ne connaît ni flexbox ni grille. Couleurs de la charte DIAGO.
    $employee = $payroll->employee;
    $company = $employee?->entreprise;
    $settings = $company?->settings ?? [];
    $logoPath = data_get($settings, 'logo');
    $logoFile = $logoPath ? public_path('storage/' . ltrim($logoPath, '/')) : null;
    $logoData = ($logoFile && is_file($logoFile))
        ? 'data:' . (mime_content_type($logoFile) ?: 'image/png') . ';base64,' . base64_encode(file_get_contents($logoFile))
        : null;
    $periodStart = \Carbon\Carbon::create($payroll->year, $payroll->month, 1);
    $periodEnd = $periodStart->copy()->endOfMonth();
    $seniority = $employee?->hire_date ? \Carbon\Carbon::parse($employee->hire_date)->diffInYears($periodEnd) : 0;
    $amount = fn ($value) => number_format((float) $value, 0, ',', ' ');
    $rate = fn ($value) => number_format((float) $value, 2, ',', ' ');
    $paymentLabels = ['cash' => 'Espèces', 'bank' => 'Virement bancaire', 'transfer' => 'Virement'];
    $earnings = collect([
        ['Salaire catégoriel', $payroll->base_salary],
        ['Sursalaire', $payroll->sursalary],
        ['Prime d’ancienneté', $payroll->seniority_bonus],
        ['Prime de transport', $payroll->transport_allowance],
        ['Prime de responsabilité', $payroll->responsibility_bonus],
        ['Bonus', $payroll->bonus],
        ['Prime de rendement', $payroll->performance_bonus],
        ['Prime de risque', $payroll->risk_bonus],
        ['Prime d’assiduité', $payroll->attendance_bonus],
        ['Gratification', $payroll->gratification],
        ['Congé payé', $payroll->leave_pay],
        ['Indemnités', $payroll->indemnities],
    ])->filter(fn ($line) => (float) $line[1] > 0);
    $legalLines = array_filter([
        data_get($settings, 'legal_form'),
        data_get($settings, 'nccm_rccm') ? 'RCCM : ' . data_get($settings, 'nccm_rccm') : null,
        data_get($settings, 'taxpayer_account') ? 'Compte contribuable : ' . data_get($settings, 'taxpayer_account') : null,
        data_get($settings, 'cnps_number') ? 'N° employeur CNPS : ' . data_get($settings, 'cnps_number') : null,
    ]);
@endphp
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Bulletin de paie - {{ $employee?->full_name }}</title>
<style>
    @page { size: A4 portrait; margin: 10mm; }
    body { font: 9px DejaVu Sans, Arial, sans-serif; color: #172033; margin: 0; }
    .band { height: 5px; background: #273772; }
    .band-accent { height: 5px; width: 30%; background: #FADF2F; }
    table { border-collapse: collapse; width: 100%; }
    .head td { vertical-align: top; padding: 10px 0 12px; }
    .company-name { font-size: 14px; font-weight: bold; color: #273772; text-transform: uppercase; }
    .company p { margin: 1px 0; color: #5b6478; line-height: 1.35; }
    .logo { max-width: 110px; max-height: 46px; margin-bottom: 4px; }
    .doc-title { font-size: 16px; font-weight: bold; color: #273772; text-align: right; }
    .doc-period { text-align: right; color: #5b6478; margin-top: 2px; }
    .identity { border: 1px solid #e3e7f0; background: #F4F6FB; }
    .identity td { padding: 4px 8px; line-height: 1.4; width: 33.33%; }
    .identity strong { color: #273772; }
    .lines { margin-top: 10px; }
    .lines th { background: #273772; color: #fff; font-size: 8.5px; padding: 5px 6px; text-align: left; }
    .lines th.num, .lines td.num { text-align: right; }
    .lines td { border-bottom: 1px solid #e3e7f0; padding: 4px 6px; }
    .lines tr.section td { background: #eceff6; font-weight: bold; color: #273772; }
    .lines tr.total td { border-top: 1px solid #273772; font-weight: bold; background: #F4F6FB; }
    .net { margin-top: 10px; background: #273772; color: #fff; }
    .net td { padding: 9px 12px; font-size: 12px; font-weight: bold; }
    .net td.amount { text-align: right; font-size: 15px; color: #FADF2F; }
    .signatures { margin-top: 26px; }
    .signatures td { width: 50%; padding-top: 6px; border-top: 1px dashed #9aa3b5; text-align: center; color: #5b6478; }
    .footer { margin-top: 14px; border-top: 1px solid #e3e7f0; padding-top: 6px; text-align: center; color: #5b6478; font-size: 8px; line-height: 1.5; }
</style>
</head>
<body>
<div class="band"></div>
<div class="band-accent"></div>

<table class="head">
    <tr>
        <td style="width:55%">
            @if($logoData)<img src="{{ $logoData }}" alt="" class="logo"><br>@endif
            <span class="company-name">{{ $company?->name ?? 'Entreprise' }}</span>
            <div class="company">
                @if(data_get($settings, 'address'))<p>{{ data_get($settings, 'address') }}</p>@endif
                <p>{{ collect([data_get($settings, 'phone') ? 'Tél : ' . data_get($settings, 'phone') : null, data_get($settings, 'email')])->filter()->implode(' · ') }}</p>
            </div>
        </td>
        <td>
            <div class="doc-title">BULLETIN DE PAIE</div>
            <div class="doc-period">
                {{ ucfirst($periodStart->translatedFormat('F Y')) }}<br>
                Période du {{ $periodStart->format('d/m/Y') }} au {{ $periodEnd->format('d/m/Y') }}
            </div>
        </td>
    </tr>
</table>

<table class="identity">
    <tr>
        <td><strong>Employé :</strong> {{ $employee?->full_name }}</td>
        <td><strong>Matricule :</strong> {{ $employee?->matricule ?: '—' }}</td>
        <td><strong>N° CNPS :</strong> {{ $employee?->cnps_number ?: '—' }}</td>
    </tr>
    <tr>
        <td><strong>Fonction :</strong> {{ $employee?->position ?: '—' }}</td>
        <td><strong>Service :</strong> {{ $employee?->department ?: '—' }}</td>
        <td><strong>Catégorie :</strong> {{ $employee?->salary_category ?: '—' }}</td>
    </tr>
    <tr>
        <td><strong>Contrat :</strong> {{ $employee?->contract_type ?: '—' }}</td>
        <td><strong>Embauche :</strong> {{ $employee?->hire_date?->format('d/m/Y') ?: '—' }}</td>
        <td><strong>Ancienneté :</strong> {{ $seniority }} an(s)</td>
    </tr>
    <tr>
        <td><strong>Parts IGR :</strong> {{ $rate($payroll->part_igr) }}</td>
        <td><strong>Enfants :</strong> {{ (int) $payroll->children_count }}</td>
        <td><strong>Paiement :</strong> {{ $paymentLabels[$payroll->payment_mode] ?? '—' }}</td>
    </tr>
</table>

<table class="lines">
    <thead>
        <tr>
            <th>Désignation</th>
            <th class="num">Base</th>
            <th class="num">Taux</th>
            <th class="num">Gains</th>
            <th class="num">Retenues salariales</th>
            <th class="num">Charges patronales</th>
        </tr>
    </thead>
    <tbody>
        <tr class="section"><td colspan="6">Rémunération</td></tr>
        @foreach($earnings as [$label, $value])
            <tr><td>{{ $label }}</td><td class="num">—</td><td class="num">—</td><td class="num">{{ $amount($value) }}</td><td class="num">—</td><td class="num">—</td></tr>
        @endforeach
        @if((float) $payroll->overtime_hours > 0)
            <tr><td>Heures supplémentaires</td><td class="num">—</td><td class="num">{{ $rate($payroll->overtime_hours) }} h</td><td class="num">—</td><td class="num">—</td><td class="num">—</td></tr>
        @endif
        <tr class="total"><td>Salaire brut</td><td class="num">—</td><td class="num">—</td><td class="num">{{ $amount($payroll->gross_salary) }}</td><td class="num">—</td><td class="num">—</td></tr>
        <tr><td>Brut fiscal</td><td class="num">—</td><td class="num">—</td><td class="num">{{ $amount($payroll->fiscal_gross) }}</td><td class="num">—</td><td class="num">—</td></tr>
        <tr><td>Brut social</td><td class="num">—</td><td class="num">—</td><td class="num">{{ $amount($payroll->social_gross) }}</td><td class="num">—</td><td class="num">—</td></tr>

        <tr class="section"><td colspan="6">Cotisations et impôts</td></tr>
        <tr><td>Impôt sur les traitements et salaires (ITS)</td><td class="num">{{ $amount($payroll->fiscal_gross) }}</td><td class="num">—</td><td class="num">—</td><td class="num">{{ $amount($payroll->income_tax) }}</td><td class="num">{{ $amount($payroll->fiscal_gross * .012) }}</td></tr>
        <tr><td>Couverture maladie universelle (CMU)</td><td class="num">—</td><td class="num">—</td><td class="num">—</td><td class="num">{{ $amount($payroll->cmu) }}</td><td class="num">{{ $amount($payroll->cmu) }}</td></tr>
        <tr><td>CNPS — retraite</td><td class="num">{{ $amount($payroll->social_gross) }}</td><td class="num">6,30 / 7,70</td><td class="num">—</td><td class="num">{{ $amount($payroll->cnps_employee) }}</td><td class="num">{{ $amount($payroll->cnps_employer) }}</td></tr>
        <tr><td>CNPS — accident du travail</td><td class="num">{{ $amount($payroll->base_salary) }}</td><td class="num">4,00</td><td class="num">—</td><td class="num">—</td><td class="num">{{ $amount($payroll->work_accident) }}</td></tr>
        <tr><td>CNPS — prestations familiales</td><td class="num">{{ $amount($payroll->base_salary) }}</td><td class="num">5,75</td><td class="num">—</td><td class="num">—</td><td class="num">{{ $amount($payroll->family_benefits) }}</td></tr>
        <tr><td>FDFP — taxe d’apprentissage</td><td class="num">{{ $amount($payroll->fiscal_gross) }}</td><td class="num">0,40</td><td class="num">—</td><td class="num">—</td><td class="num">{{ $amount($payroll->fdfp_apprenticeship) }}</td></tr>
        <tr><td>FDFP — formation professionnelle</td><td class="num">{{ $amount($payroll->fiscal_gross) }}</td><td class="num">1,20</td><td class="num">—</td><td class="num">—</td><td class="num">{{ $amount($payroll->fdfp_training) }}</td></tr>
        @if((float) $payroll->deductions > 0)
            <tr><td>Autres retenues</td><td class="num">—</td><td class="num">—</td><td class="num">—</td><td class="num">{{ $amount($payroll->deductions) }}</td><td class="num">—</td></tr>
        @endif
        <tr class="total"><td>Totaux</td><td class="num">—</td><td class="num">—</td><td class="num">{{ $amount($payroll->gross_salary) }}</td><td class="num">{{ $amount($payroll->total_employee_deductions) }}</td><td class="num">{{ $amount($payroll->total_employer_deductions) }}</td></tr>
    </tbody>
</table>

<table class="net">
    <tr>
        <td>NET À PAYER</td>
        <td class="amount">{{ $amount($payroll->net_salary) }} {{ currency_symbol() }}</td>
    </tr>
</table>

<table class="signatures">
    <tr>
        <td>Signature de l’employé</td>
        <td>Signature de l’employeur</td>
    </tr>
</table>

<div class="footer">
    @if($legalLines)<div>{{ $company?->name }} · {{ implode(' · ', $legalLines) }}</div>@endif
    <div>Bulletin établi le {{ now()->format('d/m/Y') }} · à conserver sans limitation de durée</div>
</div>
</body>
</html>
