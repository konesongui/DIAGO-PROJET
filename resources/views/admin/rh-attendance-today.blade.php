@extends('admin.layout')

@section('content')
<div class="card border-0">
    <div class="card-header border-0 d-flex justify-content-between align-items-center">
        <div><h2 class="fs-3 fw-bold mb-1">Présences QR du jour</h2><span class="text-muted">{{ now()->format('d/m/Y') }}</span></div>
        <div class="d-flex gap-2"><a href="{{ route('admin.rh') }}" class="btn btn-light">Retour à RH &amp; Paie</a><a href="{{ route('admin.rh.attendance.report') }}" class="btn btn-light-primary">Rapport détaillé</a><a href="{{ route('admin.rh.qr.display') }}" class="btn btn-primary">Afficher le QR</a></div>
    </div>
    <div class="card-body">
        <div class="row g-4 mb-5">
            @foreach([['Total', $stats['total'], 'primary'],['Arrivées', $stats['arrivals'], 'success'],['Départs', $stats['departures'], 'info'],['En attente', $stats['incomplete'], 'warning']] as [$label,$value,$color])
                <div class="col-md-3"><div class="p-4 rounded bg-light-{{ $color }}"><div class="text-muted">{{ $label }}</div><div class="fs-2 fw-bold">{{ $value }}</div></div></div>
            @endforeach
        </div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Employé</th><th>Matricule</th><th>Arrivée</th><th>Départ</th><th>Durée</th><th>Statut</th></tr></thead>
                <tbody>
                @forelse($attendances as $attendance)
                    @php $duration = $attendance->arrival_time && $attendance->departure_time ? \Carbon\Carbon::parse($attendance->arrival_time)->diff(\Carbon\Carbon::parse($attendance->departure_time))->format('%H:%I:%S') : '-'; @endphp
                    <tr><td class="fw-semibold">{{ $attendance->employee->full_name }}</td><td>{{ $attendance->employee->matricule }}</td><td>{{ $attendance->arrival_time ?: '-' }}</td><td>{{ $attendance->departure_time ?: '-' }}</td><td>{{ $duration }}</td><td><span class="badge badge-light-{{ $attendance->status === 'complete' ? 'success' : 'warning' }}">{{ $attendance->status === 'complete' ? 'Complet' : 'En attente' }}</span></td></tr>
                @empty<tr><td colspan="6" class="text-center text-muted py-8">Aucune présence QR enregistrée aujourd’hui.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
