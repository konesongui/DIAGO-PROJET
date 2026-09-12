@extends('admin.layout')

@section('content')
@php
    $from = $month->copy()->startOfMonth();
    $to = $month->copy()->endOfMonth();
    $days = (int) $from->daysInMonth;
    $today = now()->startOfDay();
    $isCurrentMonth = $today->between($from, $to);
    // Une couleur par type de congé, prise dans la palette de la charte.
    $palette = ['#2563eb', '#059669', '#7c3aed', '#d97706', '#0891b2', '#db2777', '#4338ca', '#0f766e'];
    $types = $leaves->map(fn ($leave) => $leave->leaveType?->name ?: 'Congé')->unique()->values();
    $colorOf = fn ($name) => $palette[$types->search($name) % count($palette)];
    // Une ligne par employé, avec ses congés positionnés sur le mois.
    $rows = $leaves->groupBy('employee_id')->map(fn ($items) => [
        'employee' => $items->first()->employee,
        'days' => $items->sum(fn ($leave) => $leave->start_date->copy()->max($from)->diffInDays($leave->end_date->copy()->min($to)) + 1),
        'bars' => $items->map(function ($leave) use ($from, $to, $days, $colorOf) {
            $start = $leave->start_date->copy()->max($from);
            $end = $leave->end_date->copy()->min($to);
            $name = $leave->leaveType?->name ?: 'Congé';

            return [
                'offset' => ($start->day - 1) / $days * 100,
                'width' => ($start->diffInDays($end) + 1) / $days * 100,
                'color' => $colorOf($name),
                'label' => $name,
                'title' => $name . ' · ' . $leave->start_date->format('d/m/Y') . ' au ' . $leave->end_date->format('d/m/Y') . ' (' . $leave->days . ' jour(s))',
                'starts_before' => $leave->start_date->lt($from),
                'ends_after' => $leave->end_date->gt($to),
            ];
        })->values(),
    ])->sortBy(fn ($row) => $row['employee']?->full_name)->values();
    $monthLabel = ucfirst($from->translatedFormat('F Y'));
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.rh')" back-label="RH & Paie">
        <x-slot:actions>
            <form method="GET" action="{{ route('admin.rh.leaveCalendar') }}" class="dg-period" aria-label="Mois affiché">
                <a href="{{ route('admin.rh.leaveCalendar', ['mois' => $from->copy()->subMonth()->format('Y-m')]) }}" class="dg-btn dg-btn--outline" title="Mois précédent" aria-label="Mois précédent"><i class="bi bi-chevron-left"></i></a>
                <input type="month" name="mois" value="{{ $from->format('Y-m') }}" class="dg-input" aria-label="Mois">
                <a href="{{ route('admin.rh.leaveCalendar', ['mois' => $from->copy()->addMonth()->format('Y-m')]) }}" class="dg-btn dg-btn--outline" title="Mois suivant" aria-label="Mois suivant"><i class="bi bi-chevron-right"></i></a>
                <button type="submit" class="dg-btn dg-btn--outline"><i class="bi bi-funnel"></i>Afficher</button>
            </form>
            <a href="{{ route('admin.rh.leaves') }}" class="dg-btn dg-btn--primary"><i class="bi bi-calendar-range"></i>Demandes de congé</a>
        </x-slot:actions>
    </x-dg.page-header>

    <div class="dg-kpi-grid">
        <x-dg.kpi label="Employés en congé" :value="$rows->count()" icon="bi-people" color="blue" :hint="'sur ' . mb_strtolower($monthLabel)" />
        <x-dg.kpi label="Jours de congé" :value="$rows->sum('days')" icon="bi-calendar-check" color="green" hint="jours posés dans le mois" />
        <x-dg.kpi label="Types de congé" :value="$types->count()" icon="bi-tags" color="purple" hint="représentés ce mois-ci" />
        <x-dg.kpi label="Demandes en attente" :value="$pending" icon="bi-hourglass-split" color="orange" hint="sur le mois, à valider" />
    </div>

    <x-dg.card :title="$monthLabel" icon="bi-calendar3" color="cyan">
        <x-slot:actions>
            @if($types->isNotEmpty())
                <div class="leave-legend">
                    @foreach($types as $type)
                        <span><i style="background:{{ $colorOf($type) }}"></i>{{ $type }}</span>
                    @endforeach
                </div>
            @endif
        </x-slot:actions>

        @if($rows->isNotEmpty())
            <div class="table-responsive">
                <div class="leave-calendar">
                    <div class="leave-calendar__head">
                        <span class="leave-calendar__name">Employé</span>
                        <div class="leave-calendar__days">
                            @for($day = 1; $day <= $days; $day++)
                                @php $date = $from->copy()->day($day); @endphp
                                <span class="{{ $date->isWeekend() ? 'is-weekend' : '' }} {{ $isCurrentMonth && $date->isSameDay($today) ? 'is-today' : '' }}">{{ $day }}</span>
                            @endfor
                        </div>
                    </div>
                    @foreach($rows as $row)
                        <div class="leave-calendar__row">
                            <span class="leave-calendar__name">
                                <span class="d-block fw-semibold text-truncate">{{ $row['employee']?->full_name ?? 'Employé supprimé' }}</span>
                                <span class="d-block dg-muted text-truncate" style="font-size:12px">{{ $row['employee']?->department ?: '—' }} · {{ $row['days'] }} jour(s)</span>
                            </span>
                            <div class="leave-calendar__track">
                                @for($day = 1; $day <= $days; $day++)
                                    @php $date = $from->copy()->day($day); @endphp
                                    <span class="leave-calendar__cell {{ $date->isWeekend() ? 'is-weekend' : '' }} {{ $isCurrentMonth && $date->isSameDay($today) ? 'is-today' : '' }}"></span>
                                @endfor
                                @foreach($row['bars'] as $bar)
                                    <span class="leave-calendar__bar {{ $bar['starts_before'] ? 'starts-before' : '' }} {{ $bar['ends_after'] ? 'ends-after' : '' }}"
                                        style="left:{{ $bar['offset'] }}%;width:{{ $bar['width'] }}%;background:{{ $bar['color'] }}"
                                        title="{{ $bar['title'] }}">{{ $bar['label'] }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <p class="dg-muted mt-3 mb-0" style="font-size:12.5px"><i class="bi bi-info-circle me-1"></i>Seuls les congés validés apparaissent. Une barre qui touche le bord du mois se poursuit au-delà.</p>
        @else
            <div class="dg-chart-empty" style="min-height:240px">
                <span class="dg-tile dg-tone-cyan"><i class="bi bi-calendar3"></i></span>
                <div><strong>Aucun congé validé en {{ mb_strtolower($monthLabel) }}</strong>Les congés acceptés s’affichent ici, employé par employé, jour par jour.</div>
                <a href="{{ route('admin.rh.leaves') }}" class="dg-btn dg-btn--outline dg-btn--sm"><i class="bi bi-calendar-range"></i>Voir les demandes</a>
            </div>
        @endif
    </x-dg.card>
</div>

<style>
    .leave-calendar { min-width: 860px; }
    .leave-calendar__head, .leave-calendar__row { display: grid; grid-template-columns: 190px minmax(0, 1fr); align-items: center; gap: 12px; }
    .leave-calendar__head { padding-bottom: 8px; border-bottom: 1px solid var(--dg-border); font-size: 12px; color: var(--dg-muted); }
    .leave-calendar__row { padding: 8px 0; border-bottom: 1px solid var(--dg-border); }
    .leave-calendar__row:last-child { border-bottom: 0; }
    .leave-calendar__name { min-width: 0; font-size: 14px; }
    .leave-calendar__days { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(0, 1fr); text-align: center; }
    .leave-calendar__days span { padding: 2px 0; border-radius: 4px; }
    .leave-calendar__days .is-weekend { background: var(--dg-table-head); }
    .leave-calendar__days .is-today { background: var(--dg-navy); color: #fff; font-weight: 600; }
    .leave-calendar__track { position: relative; display: grid; grid-auto-flow: column; grid-auto-columns: minmax(0, 1fr); height: 30px; }
    .leave-calendar__cell { border-right: 1px solid var(--dg-border); }
    .leave-calendar__cell:last-child { border-right: 0; }
    .leave-calendar__cell.is-weekend { background: rgba(39, 55, 114, .04); }
    .leave-calendar__cell.is-today { background: rgba(250, 223, 47, .22); }
    .leave-calendar__bar {
        position: absolute; top: 4px; bottom: 4px; display: flex; align-items: center; overflow: hidden;
        padding: 0 8px; border-radius: 8px; color: #fff; font-size: 11.5px; font-weight: 600; white-space: nowrap;
    }
    .leave-calendar__bar.starts-before { border-top-left-radius: 0; border-bottom-left-radius: 0; }
    .leave-calendar__bar.ends-after { border-top-right-radius: 0; border-bottom-right-radius: 0; }
    .leave-legend { display: flex; flex-wrap: wrap; gap: 12px; font-size: 12.5px; color: var(--dg-muted); }
    .leave-legend span { display: inline-flex; align-items: center; gap: 6px; }
    .leave-legend i { width: 12px; height: 12px; border-radius: 3px; }
</style>
@endsection
