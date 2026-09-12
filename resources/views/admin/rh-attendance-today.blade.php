@extends('admin.layout')

@section('content')
@php
    $arrived = $attendances->whereNotNull('arrival_time');
    $left = $attendances->whereNotNull('departure_time');
    $stats = [
        ['Présents', $arrived->count(), 'bi-person-check', 'green', 'sur ' . $expected . ' employé(s) en poste'],
        ['Encore au travail', $arrived->count() - $left->count(), 'bi-clock-history', 'blue', 'arrivés, pas encore repartis'],
        ['Absents', $missing->count(), 'bi-person-x', 'red', $onLeave->count() . ' en congé validé, non comptés'],
        ['Départs enregistrés', $left->count(), 'bi-box-arrow-right', 'purple', 'journées complètes'],
    ];
    $duration = function ($attendance) {
        if (! $attendance->arrival_time || ! $attendance->departure_time) {
            return null;
        }
        $date = $attendance->attendance_date->format('Y-m-d');
        $seconds = \Carbon\Carbon::parse($date . ' ' . $attendance->departure_time)
            ->diffInSeconds(\Carbon\Carbon::parse($date . ' ' . $attendance->arrival_time));

        return intdiv($seconds, 3600) . ' h ' . str_pad((string) intdiv($seconds % 3600, 60), 2, '0', STR_PAD_LEFT);
    };
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.rh')" back-label="RH & Paie">
        <x-slot:actions>
            <a href="{{ route('admin.rh.attendance.report') }}" class="dg-btn dg-btn--outline"><i class="bi bi-graph-up"></i>Rapport de présence</a>
            <a href="{{ route('admin.rh.qr.display') }}" class="dg-btn dg-btn--primary"><i class="bi bi-qr-code"></i>Afficher le QR</a>
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="dg-kpi-grid">
        @foreach($stats as [$label, $value, $icon, $color, $hint])
            <x-dg.kpi :label="$label" :value="$value" :icon="$icon" :color="$color" :hint="$hint" />
        @endforeach
    </div>

    <div class="dg-grid-2">
        <div class="dg-card dg-card--table">
            <div class="dg-card__header flex-wrap">
                <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-green"><i class="bi bi-check2-circle"></i></span>Pointages du jour</h2>
                @if($attendances->isNotEmpty())
                    <label class="dg-search" style="max-width:220px">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input id="attendanceSearch" type="search" placeholder="Employé, matricule…" aria-label="Rechercher un pointage">
                    </label>
                @endif
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 attendance-table no-export" id="attendanceTable">
                    <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Arrivée</th>
                            <th>Départ</th>
                            <th class="text-end">Présence</th>
                            <th>État</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($attendances as $attendance)
                        <tr>
                            <td>
                                <span class="d-block fw-semibold">{{ $attendance->employee?->full_name ?? 'Employé supprimé' }}</span>
                                <span class="d-block dg-muted" style="font-size:12.5px">{{ collect([$attendance->employee?->matricule, $attendance->employee?->department])->filter()->implode(' · ') ?: '—' }}</span>
                            </td>
                            <td class="text-nowrap">{{ $attendance->arrival_time ? substr($attendance->arrival_time, 0, 5) : '—' }}</td>
                            <td class="text-nowrap">{{ $attendance->departure_time ? substr($attendance->departure_time, 0, 5) : '—' }}</td>
                            <td class="text-end text-nowrap dg-cell-num">{{ $duration($attendance) ?? '—' }}</td>
                            <td>
                                <span class="dg-badge dg-badge--{{ $attendance->departure_time ? 'success' : 'warning' }}">
                                    <i class="bi {{ $attendance->departure_time ? 'bi-check-circle' : 'bi-clock-history' }}"></i>{{ $attendance->departure_time ? 'Journée complète' : 'Au travail' }}
                                </span>
                                @if($attendance->photo_path)<span class="d-block dg-muted" style="font-size:12px"><i class="bi bi-camera me-1"></i>photo enregistrée</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-5">
                                <div class="dg-chart-empty" style="min-height:200px">
                                    <span class="dg-tile dg-tone-green"><i class="bi bi-qr-code"></i></span>
                                    <div><strong>Aucun pointage aujourd’hui</strong>Affichez le QR code à l’entrée : chaque scan enregistre une arrivée, puis un départ.</div>
                                    <a href="{{ route('admin.rh.qr.display') }}" class="dg-btn dg-btn--primary dg-btn--sm"><i class="bi bi-qr-code"></i>Afficher le QR</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <p class="dg-muted mt-3 mb-0" style="font-size:12.5px"><i class="bi bi-info-circle me-1"></i>La présence est le temps passé sur place, pause comprise. Le rapport de présence en déduit la pause de l’entreprise.</p>
            <div class="dg-chart-empty mt-4 d-none" id="attendanceNoResult" style="min-height:140px">
                <span class="dg-tile dg-tone-green"><i class="bi bi-search"></i></span>
                <div><strong>Aucun pointage ne correspond</strong>Modifiez votre recherche.</div>
            </div>
        </div>

        <x-dg.card title="Absents du jour" icon="bi-person-x" color="red" :meta="$missing->count() . ' employé(s)'">
            @if($missing->isNotEmpty())
                <ul class="dg-list">
                    @foreach($missing as $employee)
                        <li class="dg-list-row">
                            <span class="dg-tile dg-tile--sm dg-tone-red"><i class="bi bi-person-x"></i></span>
                            <div class="dg-list-row__body">
                                <div class="dg-list-row__title">{{ $employee->full_name }}</div>
                                <div class="dg-list-row__sub">{{ collect([$employee->matricule, $employee->department])->filter()->implode(' · ') ?: '—' }}</div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="dg-chart-empty" style="min-height:160px">
                    <span class="dg-tile dg-tone-green"><i class="bi bi-check2-circle"></i></span>
                    <div><strong>Personne ne manque à l’appel</strong>Tous les employés en poste ont pointé, hors congés validés.</div>
                </div>
            @endif

            @if($onLeave->isNotEmpty())
                <h3 class="attendance-subtitle">En congé validé aujourd’hui</h3>
                <ul class="dg-list">
                    @foreach($onLeave as $employee)
                        <li class="dg-list-row">
                            <span class="dg-tile dg-tile--sm dg-tone-orange"><i class="bi bi-airplane"></i></span>
                            <div class="dg-list-row__body">
                                <div class="dg-list-row__title">{{ $employee->full_name }}</div>
                                <div class="dg-list-row__sub">{{ $employee->department ?: '—' }}</div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-dg.card>
    </div>
</div>

<style>
    .attendance-table { min-width: 520px; }
    .dg-scope .attendance-table > thead > tr > th, .dg-scope .attendance-table > tbody > tr > td { padding-left: 10px !important; padding-right: 10px !important; }
    .attendance-subtitle { margin: 20px 0 10px; font-size: 14px; font-weight: 600; color: var(--dg-text); }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('attendanceSearch');
    if (!search) return;
    const rows = Array.from(document.querySelectorAll('#attendanceTable tbody tr'));
    const noResult = document.getElementById('attendanceNoResult');
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
});
</script>
@endsection
