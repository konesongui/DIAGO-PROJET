@extends('admin.layout')

@section('content')
<div class="card border-0 mb-5">
    <div class="card-header border-0 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3"><h2 class="fs-3 fw-bold mb-0">Rapport de présence QR</h2><span class="badge badge-light-primary">QR</span></div>
        <a href="{{ route('admin.rh') }}" class="btn btn-light">Retour à RH &amp; Paie</a>
    </div>
    <div class="card-body">
        <form method="POST" class="row g-3 align-items-end mb-5">@csrf
            <div class="col-md-3"><label class="form-label">Date de début</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Date de fin</label><input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Employé</label><select name="employee_id" class="form-select"><option value="">-- Tous les employés --</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string) $employeeId === (string) $employee->id)>{{ $employee->matricule }} - {{ $employee->full_name }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Rechercher</button></div>
        </form>
        <form method="POST" class="row g-3 align-items-end">@csrf
            <input type="hidden" name="from" value="{{ $from->toDateString() }}"><input type="hidden" name="to" value="{{ $to->toDateString() }}"><input type="hidden" name="employee_id" value="{{ $employeeId }}">
            <div class="col-12"><h3 class="fs-5 fw-bold mb-0">Paramètres horaires <span class="badge badge-light-success ms-2">Travail</span></h3></div>
            @foreach([['start_time','Début','qr_attendance_start_time'],['break_start','Pause','qr_attendance_break_start'],['break_end','Reprise','qr_attendance_break_end'],['end_time','Départ','qr_attendance_end_time']] as [$name,$label,$key])
                <div class="col-md-2"><label class="form-label">{{ $label }}</label><input type="time" name="{{ $name }}" value="{{ substr($schedule[$key], 0, 5) }}" class="form-control"></div>
            @endforeach
            <div class="col-md-2"><label class="form-label">Heures régl.</label><input type="number" step="0.01" min="0" name="regular_hours" value="{{ $schedule['qr_attendance_regular_hours'] }}" class="form-control"></div>
            <div class="col-md-2"><button class="btn btn-success w-100">Enregistrer</button></div>
        </form>
    </div>
</div>

<div class="row g-4 mb-5">
    @foreach([['Pointages',$summary['records'],'primary'],['Arrivées',$summary['arrivals'],'success'],['Départs',$summary['departures'],'info'],['Écart',$summary['incomplete'],'warning'],['Vérifiés',$summary['verified'],'success'],['Durée',gmdate('H:i',$summary['total_seconds']),'primary']] as [$label,$value,$color])
        <div class="col-md-2 col-sm-6"><div class="card border-0 h-100"><div class="card-body"><div class="text-muted small">{{ $label }}</div><div class="fs-2 fw-bold text-{{ $color }}">{{ $value }}</div></div></div></div>
    @endforeach
</div>

<div class="card border-0 mb-5">
    <div class="card-header border-0"><h3 class="fs-4 fw-bold mb-0">Résultats détaillés</h3></div>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Date</th><th>Employé</th><th>Matricule</th><th>Arrivée</th><th>Départ</th><th>Durée</th><th>Téléphone</th><th>Vérification</th><th>Photo</th></tr></thead><tbody>
    @forelse($attendances as $attendance)
        @php $duration = $attendance->arrival_time && $attendance->departure_time ? \Carbon\Carbon::parse($attendance->arrival_time)->diff(\Carbon\Carbon::parse($attendance->departure_time))->format('%H:%I:%S') : '-'; @endphp
        <tr><td>{{ $attendance->attendance_date->format('d/m/Y') }}</td><td class="fw-semibold">{{ $attendance->employee->full_name }}</td><td>{{ $attendance->employee->matricule }}</td><td>{{ $attendance->arrival_time ?: '-' }}</td><td>{{ $attendance->departure_time ?: '-' }}</td><td>{{ $duration }}</td><td>{{ $attendance->employee->phone ?: '-' }}</td><td><span class="badge badge-light-success">{{ $attendance->verification_status }}</span></td><td>{{ $attendance->photo_path ? 'Photo capturée' : 'Absente' }}</td></tr>
    @empty<tr><td colspan="9" class="text-center text-muted py-8">Aucune présence pour cette période.</td></tr>@endforelse
    </tbody></table></div>
</div>

<div class="card border-0">
    <div class="card-header border-0"><h3 class="fs-4 fw-bold mb-0">Total par employé</h3></div>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Employé</th><th>Matricule</th><th>Pointages</th><th>Heure régl.</th><th>Heure effect.</th><th>Écart</th></tr></thead><tbody>
    @forelse($employeeTotals as $total)
        <tr><td class="fw-semibold">{{ $total['employee']->full_name }}</td><td>{{ $total['employee']->matricule }}</td><td>{{ $total['count'] }}</td><td>{{ number_format($total['regular_seconds'] / 3600, 2, ',', ' ') }} h</td><td>{{ number_format($total['actual_seconds'] / 3600, 2, ',', ' ') }} h</td><td class="{{ $total['gap_seconds'] < 0 ? 'text-danger' : 'text-success' }}">{{ $total['gap_seconds'] < 0 ? '-' : '+' }}{{ number_format(abs($total['gap_seconds']) / 3600, 2, ',', ' ') }} h</td></tr>
    @empty<tr><td colspan="6" class="text-center text-muted py-8">Aucune synthèse disponible.</td></tr>@endforelse
    </tbody></table></div>
</div>
@endsection
