<p>Bonjour {{ $payroll->employee->full_name }},</p>
<p>Votre bulletin de paie pour {{ \Carbon\Carbon::create()->month($payroll->month)->translatedFormat('F') }} {{ $payroll->year }} est disponible en pièce jointe.</p>
<p>Cordialement,<br>Le service administratif</p>
