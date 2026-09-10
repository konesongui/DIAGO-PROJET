<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bulletin de paie - {{ $payroll->employee->full_name }}</title>
    <style>
        @page { size: A4 portrait; margin: 7mm; }
        body { font: 9px Arial, sans-serif; color: #24303b; margin: 0; background: #fff; }
        .sheet { width: 100%; margin: auto; border: 1px solid #173f5f; padding: 8px; box-sizing: border-box; }
        .header-table, .identity-table { width: 100%; border-collapse: collapse; }
        .header-table { border-bottom: 3px solid #13a6a6; padding-bottom: 5px; }
        .header-table td { vertical-align: middle; }
        .company { width: 31%; text-align: center; padding: 3px; }
        .company-logo { width: 62px; height: 48px; object-fit: contain; margin-bottom: 3px; }
        .company-name { color: #173f5f; font-size: 13px; font-weight: bold; text-transform: uppercase; }
        .company p { margin: 2px 0; line-height: 1.25; }
        .document-title { color: #173f5f; text-align: center; font-size: 17px; font-weight: bold; margin: 0 0 7px; }
        .identity { border: 1px solid #b6c7d2; border-radius: 3px; padding: 6px; background: #f5fafb; }
        .identity-table td { padding: 3px 5px; line-height: 1.25; }
        h2 { background: #e9ecef; border: 1px solid #aaa; font-size: 9px; padding: 3px; text-align: center; margin: 3px 0; }
        p { margin: 2px 0; line-height: 1.15; }
        .payroll-table { width: 100%; border-collapse: collapse; margin-top: 9px; table-layout: fixed; }
        .payroll-table th, .payroll-table td { border: 1px solid #8da3af; padding: 3px 4px; line-height: 1.2; }
        .payroll-table th { background: #173f5f; color: #fff; font-size: 8px; }
        .payroll-table td { font-size: 8.5px; }
        .payroll-table tr:nth-child(even) td { background: #f6f9fa; }
        .right { text-align: right; }
        .total td { font-weight: bold; background: #e8f1f4 !important; }
        .net td { font-size: 12px; font-weight: bold; color: #125c45; background: #d9f2e6 !important; }
        .signatures { width: 100%; border-collapse: collapse; margin-top: 22px; }
        .signature { width: 50%; border-top: 1px dashed #607d8b; padding-top: 6px; text-align: center; color: #52636d; font-size: 9px; }
        @media print { .sheet { border: 0; } }
    </style>
</head>
<body>
<div class="sheet">
    @php
        $settings = $payroll->employee->entreprise?->settings ?? [];
        $logoPath = data_get($settings, 'logo');
        $logoFile = $logoPath ? public_path('storage/' . ltrim($logoPath, '/')) : null;
        $logoData = ($logoFile && is_file($logoFile))
            ? 'data:' . (mime_content_type($logoFile) ?: 'image/png') . ';base64,' . base64_encode(file_get_contents($logoFile))
            : null;
        $periodStart = \Carbon\Carbon::create($payroll->year, $payroll->month, 1);
        $periodEnd = $periodStart->copy()->endOfMonth();
        $seniority = $payroll->employee->hire_date ? \Carbon\Carbon::parse($payroll->employee->hire_date)->diffInYears($periodEnd) : 0;
        $money = fn ($value) => number_format((float) $value, 0, ',', '.');
    @endphp
    <table class="header-table">
        <tr>
            <td class="company">
                @if($logoData)<img src="{{ $logoData }}" alt="Logo" class="company-logo">@endif
                <div class="company-name">{{ $payroll->employee->entreprise?->name ?? 'Entreprise' }}</div>
                <p>{{ $settings['address'] ?? '-' }}</p>
                <p>{{ $settings['phone'] ?? '-' }} | {{ $settings['email'] ?? '-' }}</p>
            </td>
            <td class="identity">
                <div class="document-title">BULLETIN DE PAIE - {{ ucfirst($periodStart->translatedFormat('F')) }} {{ $payroll->year }}</div>
                <table class="identity-table">
                    <tr><td><strong>Matricule :</strong> {{ $payroll->employee->matricule }}</td><td><strong>Employé :</strong> {{ $payroll->employee->full_name }}</td><td><strong>Heures sup. :</strong> {{ number_format($payroll->overtime_hours, 2, ',', ' ') }} h</td></tr>
                    <tr><td><strong>Statut :</strong> {{ $payroll->employee->marital_status ?: '-' }}</td><td><strong>Contrat :</strong> {{ $payroll->employee->contract_type ?: '-' }}</td><td><strong>Catégorie :</strong> {{ $payroll->employee->salary_category ?: '-' }}</td></tr>
                    <tr><td><strong>CNPS :</strong> {{ $payroll->employee->cnps_number ?: '-' }}</td><td><strong>Mode :</strong> {{ $payroll->payment_mode ?: '-' }}</td><td><strong>Parts IGR :</strong> {{ $payroll->part_igr }}</td></tr>
                    <tr><td><strong>Enfants :</strong> {{ $payroll->children_count }}</td><td><strong>Embauche :</strong> {{ optional($payroll->employee->hire_date)->format('d/m/Y') ?: '-' }}</td><td><strong>Ancienneté :</strong> {{ $seniority }} ans</td></tr>
                    <tr><td><strong>Fonction :</strong> {{ $payroll->employee->position ?: '-' }}</td><td><strong>Service :</strong> {{ $payroll->employee->department ?: '-' }}</td><td><strong>Période :</strong> {{ $periodStart->format('d/m/Y') }} - {{ $periodEnd->format('d/m/Y') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="payroll-table">
        <thead><tr><th rowspan="2">DÉSIGNATION</th><th rowspan="2">BASE</th><th colspan="2">PART SALARIALE</th><th colspan="2">PART PATRONALE</th></tr><tr><th>Nbre/taux</th><th>GAINS / RETENUES</th><th>Nbre/taux</th><th>RETENUES</th></tr></thead>
        <tbody>
        @foreach([
            ['Salaire catégoriel', $payroll->base_salary, '30', $payroll->base_salary, '', ''],
            ['Sursalaire', $payroll->sursalary, '30', $payroll->sursalary, '', ''],
            ['Prime d’ancienneté', $payroll->seniority_bonus, '1', $payroll->seniority_bonus, '', ''],
            ['Prime de transport', $payroll->transport_allowance, '1', $payroll->transport_allowance, '', ''],
            ['Prime de responsabilité', $payroll->responsibility_bonus, '1', $payroll->responsibility_bonus, '', ''],
            ['Bonus', $payroll->bonus, '1', $payroll->bonus, '', ''],
            ['Prime de rendement', $payroll->performance_bonus, '1', $payroll->performance_bonus, '', ''],
            ['Prime de risque', $payroll->risk_bonus, '1', $payroll->risk_bonus, '', ''],
            ['Prime d’assiduité', $payroll->attendance_bonus, '1', $payroll->attendance_bonus, '', ''],
            ['Prime de gratification', $payroll->gratification, '1', $payroll->gratification, '', ''],
            ['Congé payé', $payroll->leave_pay, '1', $payroll->leave_pay, '', ''],
            ['Indemnités', $payroll->indemnities, '1', $payroll->indemnities, '', ''],
        ] as $line)
            @if((float) $line[1] > 0)<tr><td>{{ $line[0] }}</td><td class="right">{{ $money($line[1]) }}</td><td>{{ $line[2] }}</td><td class="right">{{ $money($line[3]) }}</td><td>{{ $line[4] }}</td><td class="right">{{ $line[5] }}</td></tr>@endif
        @endforeach
        <tr class="total"><td>Total Brut</td><td></td><td></td><td class="right">{{ $money($payroll->gross_salary) }}</td><td></td><td></td></tr>
        <tr class="total"><td>Total Brut Fiscal</td><td></td><td></td><td class="right">{{ $money($payroll->fiscal_gross) }}</td><td></td><td></td></tr>
        <tr class="total"><td>Total Brute Social</td><td></td><td></td><td class="right">{{ $money($payroll->social_gross) }}</td><td></td><td></td></tr>
        <tr><td>ITS</td><td class="right">{{ $money($payroll->fiscal_gross) }}</td><td></td><td class="right">{{ $money($payroll->income_tax) }}</td><td>1,2</td><td class="right">{{ $money($payroll->fiscal_gross * .012) }}</td></tr>
        <tr><td>CMU</td><td>-</td><td></td><td class="right">{{ $money($payroll->cmu) }}</td><td></td><td class="right">{{ $money($payroll->cmu) }}</td></tr>
        <tr><td>CNPS, Régime de Retraite</td><td class="right">{{ $money($payroll->social_gross) }}</td><td>6,30</td><td class="right">{{ $money($payroll->cnps_employee) }}</td><td>7,70</td><td class="right">{{ $money($payroll->cnps_employer) }}</td></tr>
        <tr><td>CNPS, Accident Travail</td><td class="right">{{ $money($payroll->base_salary) }}</td><td></td><td></td><td>4,00</td><td class="right">{{ $money($payroll->work_accident) }}</td></tr>
        <tr><td>CNPS, Prest. Famil</td><td class="right">{{ $money($payroll->base_salary) }}</td><td></td><td></td><td>5,75</td><td class="right">{{ $money($payroll->family_benefits) }}</td></tr>
        <tr><td>FDFP, Taxe Apprentissage</td><td class="right">{{ $money($payroll->fiscal_gross) }}</td><td></td><td></td><td>0,40</td><td class="right">{{ $money($payroll->fdfp_apprenticeship) }}</td></tr>
        <tr><td>FDFP, Form. Prof. Continue</td><td class="right">{{ $money($payroll->fiscal_gross) }}</td><td></td><td></td><td>1,20</td><td class="right">{{ $money($payroll->fdfp_training) }}</td></tr>
        <tr class="total"><td>Total des retenues</td><td></td><td></td><td class="right">{{ $money($payroll->total_employee_deductions) }}</td><td></td><td class="right">{{ $money($payroll->total_employer_deductions) }}</td></tr>
        <tr class="net"><td>NET À PAYER</td><td colspan="5">{{ $money($payroll->net_salary) }} {{ currency_symbol() }}</td></tr>
        </tbody>
    </table>
    <table class="signatures"><tr><td class="signature">Signature employé</td><td class="signature">Signature employeur</td></tr></table>
</div>
</body>
</html>
