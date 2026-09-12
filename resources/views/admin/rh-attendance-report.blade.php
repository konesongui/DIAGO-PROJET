@extends('admin.layout')

@section('content')
@php
    $hours = fn (int $seconds) => intdiv(abs($seconds), 3600) . ' h ' . str_pad((string) intdiv(abs($seconds) % 3600, 60), 2, '0', STR_PAD_LEFT);
    $stats = [
        ['Jours pointés', $summary['records'], 'bi-calendar-check', 'blue', $summary['arrivals'] . ' arrivée(s), ' . $summary['departures'] . ' départ(s)'],
        ['Heures travaillées', $hours($summary['worked_seconds']), 'bi-clock-history', 'green', 'pauses déduites'],
        ['Retards', $summary['late'], 'bi-alarm', 'orange', 'arrivées après ' . substr($schedule['qr_attendance_start_time'], 0, 5)],
        ['Départs manquants', $summary['incomplete'], 'bi-question-circle', 'red', 'journées sans pointage de sortie'],
    ];
    $scheduleErrors = $errors->any() && old('_schedule_form');
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.rh')" back-label="RH & Paie">
        <x-slot:actions>
            <a href="{{ route('admin.rh.attendance.today') }}" class="dg-btn dg-btn--outline"><i class="bi bi-check2-circle"></i>Présences du jour</a>
            <button type="button" class="dg-btn dg-btn--outline" data-bs-toggle="modal" data-bs-target="#scheduleModal"><i class="bi bi-clock"></i>Horaires de travail</button>
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($periodError)<div class="alert alert-warning">{{ $periodError }}</div>@endif
    @if($errors->any() && ! $scheduleErrors)<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="dg-card mb-5">
        {{-- La recherche passe par l'URL : elle ne touche pas aux horaires. --}}
        <form method="GET" action="{{ route('admin.rh.attendance.report') }}" class="row g-3 align-items-end">
            <div class="col-sm-6 col-lg-3">
                <label class="form-label" for="reportFrom">Du</label>
                <input id="reportFrom" type="date" name="from" value="{{ $from->toDateString() }}" class="form-control">
            </div>
            <div class="col-sm-6 col-lg-3">
                <label class="form-label" for="reportTo">Au</label>
                <input id="reportTo" type="date" name="to" value="{{ $to->toDateString() }}" class="form-control">
            </div>
            <div class="col-sm-8 col-lg-4">
                <label class="form-label" for="reportEmployee">Employé</label>
                <select id="reportEmployee" name="employee_id" class="form-select">
                    <option value="">Tous les employés</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) $employeeId === (string) $employee->id)>{{ $employee->full_name }}{{ $employee->matricule ? ' — ' . $employee->matricule : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-4 col-lg-2 d-flex gap-2">
                <button type="submit" class="dg-btn dg-btn--primary flex-grow-1"><i class="bi bi-funnel"></i>Rechercher</button>
                <a href="{{ route('admin.rh.attendance.report') }}" class="dg-btn dg-btn--outline" title="Réinitialiser" aria-label="Réinitialiser"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
        <p class="dg-muted mt-3 mb-0" style="font-size:12.5px">
            <i class="bi bi-clock me-1"></i>Horaire de référence : {{ substr($schedule['qr_attendance_start_time'], 0, 5) }} – {{ substr($schedule['qr_attendance_end_time'], 0, 5) }},
            pause de {{ substr($schedule['qr_attendance_break_start'], 0, 5) }} à {{ substr($schedule['qr_attendance_break_end'], 0, 5) }},
            soit {{ number_format($dailyHours, 2, ',', ' ') }} h par jour travaillé.
        </p>
    </div>

    <div class="dg-kpi-grid">
        @foreach($stats as [$label, $value, $icon, $color, $hint])
            <x-dg.kpi :label="$label" :value="$value" :icon="$icon" :color="$color" :hint="$hint" />
        @endforeach
    </div>

    <div class="dg-card dg-card--table mb-5">
        <div class="dg-card__header flex-wrap">
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-purple"><i class="bi bi-people"></i></span>Total par employé</h2>
            <span class="dg-card__meta">{{ $from->format('d/m/Y') }} au {{ $to->format('d/m/Y') }}</span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0 totals-table no-export">
                <thead>
                    <tr>
                        <th>Employé</th>
                        <th class="text-end">Jours pointés</th>
                        <th class="text-end">Retards</th>
                        <th class="text-end">Heures attendues</th>
                        <th class="text-end">Heures travaillées</th>
                        <th class="text-end">Écart</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($employeeTotals as $total)
                    <tr>
                        <td>
                            <span class="d-block fw-semibold">{{ $total['employee']?->full_name ?? 'Employé supprimé' }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ collect([$total['employee']?->matricule, $total['employee']?->department])->filter()->implode(' · ') ?: '—' }}</span>
                        </td>
                        <td class="text-end dg-cell-num">{{ $total['days'] }}</td>
                        <td class="text-end dg-cell-num {{ $total['late'] > 0 ? 'dg-amount-negative' : '' }}">{{ $total['late'] }}</td>
                        <td class="text-end dg-cell-num">{{ $hours($total['expected_seconds']) }}</td>
                        <td class="text-end dg-cell-num">{{ $hours($total['worked_seconds']) }}</td>
                        <td class="text-end text-nowrap fw-semibold {{ $total['gap_seconds'] < 0 ? 'dg-amount-negative' : 'text-success' }}">
                            {{ $total['gap_seconds'] < 0 ? '−' : '+' }} {{ $hours($total['gap_seconds']) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-5">
                            <div class="dg-chart-empty" style="min-height:180px">
                                <span class="dg-tile dg-tone-purple"><i class="bi bi-people"></i></span>
                                <div><strong>Aucun pointage sur la période</strong>Choisissez une autre période, ou affichez le QR code pour commencer à pointer.</div>
                                <a href="{{ route('admin.rh.qr.display') }}" class="dg-btn dg-btn--outline dg-btn--sm"><i class="bi bi-qr-code"></i>Afficher le QR</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <p class="dg-muted mt-3 mb-0" style="font-size:12.5px"><i class="bi bi-info-circle me-1"></i>Les heures attendues comptent la journée de référence pour chaque jour réellement pointé. Une journée sans pointage de départ ne compte aucune heure travaillée.</p>
    </div>

    <div class="dg-card dg-card--table">
        <div class="dg-card__header flex-wrap">
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-blue"><i class="bi bi-list-check"></i></span>Pointages détaillés</h2>
            @if($attendances->isNotEmpty())
                <label class="dg-search" style="max-width:240px">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input id="reportSearch" type="search" placeholder="Employé, matricule…" aria-label="Rechercher un pointage">
                </label>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0 report-table no-export" id="reportTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employé</th>
                        <th>Arrivée</th>
                        <th>Départ</th>
                        <th class="text-end">Travaillé</th>
                        <th>Contrôle</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($attendances as $attendance)
                    @php
                        $late = $attendance->arrival_time && $attendance->arrival_time > $schedule['qr_attendance_start_time'] . ':00';
                        $worked = null;
                        if ($attendance->arrival_time && $attendance->departure_time) {
                            // Même calcul que les totaux : la pause de l'entreprise est déduite.
                            $date = $attendance->attendance_date->format('Y-m-d');
                            $arrival = \Carbon\Carbon::parse($date . ' ' . $attendance->arrival_time);
                            $departure = \Carbon\Carbon::parse($date . ' ' . $attendance->departure_time);
                            $breakStart = \Carbon\Carbon::parse($date . ' ' . $schedule['qr_attendance_break_start']);
                            $breakEnd = \Carbon\Carbon::parse($date . ' ' . $schedule['qr_attendance_break_end']);
                            $pause = $arrival->lt($breakEnd) && $departure->gt($breakStart)
                                ? max(0, min($departure->timestamp, $breakEnd->timestamp) - max($arrival->timestamp, $breakStart->timestamp))
                                : 0;
                            $worked = $hours(max(0, $departure->diffInSeconds($arrival) - $pause));
                        }
                    @endphp
                    <tr>
                        <td class="text-nowrap">{{ $attendance->attendance_date->format('d/m/Y') }}</td>
                        <td>
                            <span class="d-block fw-semibold">{{ $attendance->employee?->full_name ?? 'Employé supprimé' }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ $attendance->employee?->matricule ?: '—' }}</span>
                        </td>
                        <td class="text-nowrap">
                            {{ $attendance->arrival_time ? substr($attendance->arrival_time, 0, 5) : '—' }}
                            @if($late)<span class="d-block dg-amount-negative" style="font-size:12px">en retard</span>@endif
                        </td>
                        <td class="text-nowrap">{{ $attendance->departure_time ? substr($attendance->departure_time, 0, 5) : '—' }}</td>
                        <td class="text-end text-nowrap dg-cell-num">{{ $worked ?? '—' }}</td>
                        <td>
                            <span class="dg-badge dg-badge--{{ $attendance->departure_time ? 'success' : 'warning' }}"><i class="bi {{ $attendance->departure_time ? 'bi-check-circle' : 'bi-hourglass-split' }}"></i>{{ $attendance->departure_time ? 'Journée complète' : 'Départ manquant' }}</span>
                            <span class="d-block dg-muted" style="font-size:12px">{{ $attendance->photo_path ? 'Photo enregistrée' : 'Sans photo' }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="dg-muted text-center py-5">Aucun pointage sur la période.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="dg-chart-empty mt-4 d-none" id="reportNoResult" style="min-height:140px">
            <span class="dg-tile dg-tone-blue"><i class="bi bi-search"></i></span>
            <div><strong>Aucun pointage ne correspond</strong>Modifiez votre recherche.</div>
        </div>
    </div>

    {{-- Horaires de travail de l'entreprise. --}}
    <div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.rh.attendance.report') }}">
                    @csrf
                    <input type="hidden" name="_schedule_form" value="1">
                    <input type="hidden" name="from" value="{{ $from->toDateString() }}">
                    <input type="hidden" name="to" value="{{ $to->toDateString() }}">
                    <input type="hidden" name="employee_id" value="{{ $employeeId }}">
                    <div class="modal-header">
                        <h5 class="modal-title" id="scheduleModalTitle"><i class="bi bi-clock me-2"></i>Horaires de travail</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        @if($scheduleErrors)<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
                        <div class="row g-3">
                            @foreach([
                                ['start_time', 'Début de journée', 'qr_attendance_start_time'],
                                ['break_start', 'Début de pause', 'qr_attendance_break_start'],
                                ['break_end', 'Reprise', 'qr_attendance_break_end'],
                                ['end_time', 'Fin de journée', 'qr_attendance_end_time'],
                            ] as [$name, $label, $key])
                                <div class="col-6">
                                    <label class="form-label" for="schedule{{ $name }}">{{ $label }}</label>
                                    <input id="schedule{{ $name }}" type="time" name="{{ $name }}" value="{{ old($name, substr($schedule[$key], 0, 5)) }}" class="form-control @error($name) is-invalid @enderror" required>
                                </div>
                            @endforeach
                            <div class="col-12">
                                <label class="form-label" for="scheduleRegular">Heures mensuelles de référence</label>
                                <input id="scheduleRegular" type="number" step="0.01" min="0" max="744" name="regular_hours" value="{{ old('regular_hours', $schedule['qr_attendance_regular_hours']) }}" class="form-control @error('regular_hours') is-invalid @enderror" required>
                                <div class="form-text">Sert de repère pour la paie ; les écarts du rapport sont calculés sur la journée de travail ci-dessus.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .totals-table { min-width: 760px; }
    .report-table { min-width: 720px; }
    .dg-scope .totals-table > thead > tr > th, .dg-scope .totals-table > tbody > tr > td,
    .dg-scope .report-table > thead > tr > th, .dg-scope .report-table > tbody > tr > td { padding-left: 10px !important; padding-right: 10px !important; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('reportSearch');
    if (search) {
        const rows = Array.from(document.querySelectorAll('#reportTable tbody tr'));
        const noResult = document.getElementById('reportNoResult');
        search.addEventListener('input', function () {
            const term = search.value.trim().toLowerCase();
            let visible = 0;
            rows.forEach(function (row) {
                const show = !term || row.textContent.toLowerCase().includes(term);
                row.style.display = show ? '' : 'none';
                visible += show ? 1 : 0;
            });
            noResult.classList.toggle('d-none', visible > 0);
        });
    }
    @if($scheduleErrors)
        window.addEventListener('load', () => new bootstrap.Modal(document.getElementById('scheduleModal')).show());
    @endif
});
</script>
@endsection
