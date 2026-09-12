@extends('admin.layout')

@section('content')
@php
    // État affiché : le contrat d'abord, puis le congé validé qui couvre la journée.
    $stateOf = fn ($employee) => $employee->status === 'inactive'
        ? 'left'
        : ($onLeaveToday->has($employee->id) ? 'on_leave' : 'active');
    $states = [
        'active' => ['En poste', 'success', 'bi-person-check', 'En poste'],
        'on_leave' => ['En congé', 'warning', 'bi-airplane', 'En congé'],
        'left' => ['Contrat clôturé', 'neutral', 'bi-person-dash', 'Sortis'],
    ];
    $byState = $employees->groupBy($stateOf);
    $active = $byState->get('active', collect());
    $onLeave = $byState->get('on_leave', collect());
    $left = $byState->get('left', collect());
    $payrollMass = (float) $active->concat($onLeave)->sum('monthly_salary');
    $stats = [
        ['Effectif', $active->count() + $onLeave->count(), 'bi-people', 'purple', $employees->count() . ' fiche(s) au total'],
        ['En congé aujourd’hui', $onLeave->count(), 'bi-airplane', 'orange', 'congés validés couvrant le ' . now()->format('d/m/Y')],
        ['Masse salariale', money($payrollMass), 'bi-cash-coin', 'green', 'salaires de base des employés en poste'],
        ['Contrats clôturés', $left->count(), 'bi-person-dash', 'red', 'fiches conservées pour l’historique'],
    ];
    $contracts = $employees->pluck('contract_type')->filter()->unique()->sort()->values();
    $columns = [
        'photo' => 'Photo', 'matricule' => 'Matricule', 'civility' => 'Civilité', 'name' => 'Nom et prénom',
        'service' => 'Service', 'position' => 'Fonction', 'nationality' => 'Nationalité', 'contract' => 'Type de contrat',
        'address' => 'Adresse', 'birth_date' => 'Date de naissance', 'entry_date' => 'Date d’entrée',
        'exit_date' => 'Date de sortie', 'salary_category' => 'Catégorie salariale', 'cnps' => 'CNPS',
        'phone' => 'Téléphone', 'status' => 'Statut', 'actions' => 'Action',
    ];
    $initials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.rh')" back-label="RH & Paie">
        <x-slot:actions>
            <a href="{{ route('admin.departments.index') }}" class="dg-btn dg-btn--outline"><i class="bi bi-diagram-3"></i>Gérer les services</a>
            <a href="{{ route('admin.rh.employees.create') }}" class="dg-btn dg-btn--primary"><i class="bi bi-person-plus"></i>Nouvel employé</a>
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="dg-kpi-grid">
        @foreach($stats as [$label, $value, $icon, $color, $hint])
            <x-dg.kpi :label="$label" :value="$value" :icon="$icon" :color="$color" :hint="$hint" />
        @endforeach
    </div>

    <div class="dg-card dg-card--table">
        <div class="dg-card__header flex-wrap">
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-purple"><i class="bi bi-people"></i></span>Personnel</h2>
            <div class="d-flex flex-wrap align-items-center gap-2" role="search" aria-label="Filtrer le personnel">
                <label class="dg-search" style="max-width:210px">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input id="employeeSearch" type="search" placeholder="Nom, matricule, poste…" aria-label="Rechercher un employé">
                </label>
                @if($departments->isNotEmpty())
                    <select id="employeeDepartment" class="form-select" style="max-width:172px" aria-label="Service">
                        <option value="">Tous services</option>
                        @foreach($departments as $department)
                            <option value="{{ mb_strtolower($department) }}">{{ $department }}</option>
                        @endforeach
                    </select>
                @endif
                @if($contracts->isNotEmpty())
                    <select id="employeeContract" class="form-select" style="max-width:158px" aria-label="Type de contrat">
                        <option value="">Tous contrats</option>
                        @foreach($contracts as $contract)
                            <option value="{{ mb_strtolower($contract) }}">{{ $contract }}</option>
                        @endforeach
                    </select>
                @endif
                <div class="dropdown">
                    <button type="button" class="dg-btn dg-btn--outline" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-layout-three-columns"></i>Colonnes
                    </button>
                    <div class="dropdown-menu dropdown-menu-end dg-dropdown employee-columns-menu p-0" data-employee-columns-menu>
                        <div class="dg-dropdown__header">Afficher les colonnes</div>
                        @foreach($columns as $key => $label)
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" value="{{ $key }}" data-column-toggle="{{ $key }}" @disabled($key === 'name' || $key === 'actions')>
                                <span class="form-check-label">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        @if($employees->isNotEmpty())
            <div class="dg-tabs mb-4" role="group" aria-label="Filtrer par état">
                <button type="button" class="dg-tab is-active" data-state-filter="" aria-pressed="true">Tous ({{ $employees->count() }})</button>
                @foreach($states as $value => [, , , $tabLabel])
                    <button type="button" class="dg-tab" data-state-filter="{{ $value }}" aria-pressed="false">{{ $tabLabel }} ({{ $byState->get($value, collect())->count() }})</button>
                @endforeach
            </div>
        @endif

        <div class="table-responsive employee-table-wrapper">
            <table class="table align-middle mb-0 employee-table no-export no-column-sort" data-employee-table>
                <thead>
                    <tr>
                        @foreach($columns as $key => $label)
                            <th data-column="{{ $key }}" class="{{ $key === 'actions' ? 'text-end' : '' }}">{{ $key === 'actions' ? '' : $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                @forelse($employees as $employee)
                    @php
                        $state = $stateOf($employee);
                        [$stateLabel, $stateTone, $stateIcon] = $states[$state];
                        $leave = $onLeaveToday->get($employee->id);
                    @endphp
                    <tr data-state="{{ $state }}"
                        data-department="{{ mb_strtolower((string) $employee->department) }}"
                        data-contract="{{ mb_strtolower((string) $employee->contract_type) }}">
                        <td data-column="photo">
                            @if($employee->photo_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($employee->photo_path) }}" alt="" class="employee-photo">
                            @else
                                <span class="employee-photo employee-photo--initials">{{ $initials($employee->full_name) }}</span>
                            @endif
                        </td>
                        <td data-column="matricule"><span class="dg-badge dg-badge--neutral">{{ $employee->matricule ?: '—' }}</span></td>
                        <td data-column="civility">{{ $employee->civility ?: '—' }}</td>
                        <td data-column="name">
                            <a href="{{ route('admin.rh.employees.show', $employee) }}" class="d-block fw-semibold text-reset text-decoration-none">{{ $employee->full_name }}</a>
                            <span class="d-block dg-muted text-truncate" style="font-size:12.5px;max-width:220px">{{ $employee->email ?: 'Sans e-mail' }}</span>
                        </td>
                        <td data-column="service">{{ $employee->department ?: '—' }}</td>
                        <td data-column="position">{{ $employee->position ?: '—' }}</td>
                        <td data-column="nationality">{{ $employee->nationality ?: '—' }}</td>
                        <td data-column="contract">{{ $employee->contract_type ?: '—' }}</td>
                        <td data-column="address">{{ $employee->address ?: '—' }}</td>
                        <td data-column="birth_date">{{ $employee->birth_date?->format('d/m/Y') ?: '—' }}</td>
                        <td data-column="entry_date">{{ ($employee->registered_at ?? $employee->hire_date)?->format('d/m/Y') ?: '—' }}</td>
                        <td data-column="exit_date">{{ $employee->contract_end_date?->format('d/m/Y') ?: '—' }}</td>
                        <td data-column="salary_category">{{ $employee->salary_category ?: '—' }}</td>
                        <td data-column="cnps">{{ $employee->cnps_number ?: '—' }}</td>
                        <td data-column="phone">{{ $employee->phone ?: '—' }}</td>
                        <td data-column="status">
                            <span class="dg-badge dg-badge--{{ $stateTone }}"><i class="bi {{ $stateIcon }}"></i>{{ $stateLabel }}</span>
                            @if($leave)
                                <span class="d-block dg-muted" style="font-size:12px">{{ $leave->leaveType?->name }} jusqu’au {{ $leave->end_date->format('d/m/Y') }}</span>
                            @elseif($state === 'left' && $employee->contract_end_date)
                                <span class="d-block dg-muted" style="font-size:12px">depuis le {{ $employee->contract_end_date->format('d/m/Y') }}</span>
                            @endif
                        </td>
                        <td data-column="actions" class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light action-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions pour {{ $employee->full_name }}"></button>
                                <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                                    <li><a class="dropdown-item" href="{{ route('admin.rh.employees.show', $employee) }}"><i class="bi bi-eye"></i>Voir la fiche</a></li>
                                    <li><a class="dropdown-item" href="{{ route('admin.rh.employees.edit', $employee) }}"><i class="bi bi-pencil"></i>Modifier</a></li>
                                    @if($state !== 'left')
                                        <li>
                                            <form method="POST" action="{{ route('admin.rh.employees.terminate', $employee) }}" onsubmit="return confirm('Clôturer le contrat de {{ addslashes($employee->full_name) }} ? Son compte utilisateur sera désactivé.')">
                                                @csrf @method('PATCH')
                                                <button class="dropdown-item text-warning"><i class="bi bi-person-dash"></i>Mettre fin au contrat</button>
                                            </form>
                                        </li>
                                    @endif
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('admin.rh.employees.destroy', $employee) }}" onsubmit="return confirm('Supprimer définitivement la fiche de {{ addslashes($employee->full_name) }} ? Impossible si elle a des bulletins, des congés ou des pointages.')">
                                            @csrf @method('DELETE')
                                            <button class="dropdown-item text-danger"><i class="bi bi-trash"></i>Supprimer la fiche</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" class="p-5">
                            <div class="dg-chart-empty" style="min-height:220px">
                                <span class="dg-tile dg-tone-purple"><i class="bi bi-people"></i></span>
                                <div><strong>Aucun employé enregistré</strong>Créez une fiche : matricule, contrat et compte utilisateur sont générés, et l’employé entre dans la paie.</div>
                                <a href="{{ route('admin.rh.employees.create') }}" class="dg-btn dg-btn--primary dg-btn--sm"><i class="bi bi-person-plus"></i>Nouvel employé</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="dg-chart-empty mt-4 d-none" id="employeesNoResult" style="min-height:160px">
            <span class="dg-tile dg-tone-purple"><i class="bi bi-search"></i></span>
            <div><strong>Aucun employé ne correspond à vos filtres</strong>Modifiez la recherche, le service, le contrat ou l’état.</div>
        </div>
        <p class="dg-muted mt-3 mb-0" style="font-size:12.5px">
            <i class="bi bi-info-circle me-1"></i>« En congé » est lu dans les demandes de congé validées qui couvrent la journée. Une fiche qui a des bulletins de paie, des congés ou des pointages ne se supprime pas : mettez fin au contrat, l’historique reste consultable.
        </p>
    </div>
</div>

<style>
    .employee-table { min-width: 720px; }
    .dg-scope .employee-table > thead > tr > th, .dg-scope .employee-table > tbody > tr > td { padding-left: 10px !important; padding-right: 10px !important; }
    /* Seules les colonnes courtes restent insécables : le tableau tient sans défilement avec les colonnes par défaut. */
    .employee-table [data-column="matricule"], .employee-table [data-column="birth_date"], .employee-table [data-column="entry_date"],
    .employee-table [data-column="exit_date"], .employee-table [data-column="cnps"], .employee-table [data-column="phone"],
    .employee-table [data-column="photo"], .employee-table [data-column="actions"] { white-space: nowrap; }
    .employee-table [data-column="address"], .employee-table [data-column="position"], .employee-table [data-column="service"] { max-width: 190px; }
    .dg-scope .employee-table-wrapper { max-height: 70vh; overflow-y: auto; }
    .dg-scope .employee-table-wrapper thead th { position: sticky; top: 0; z-index: 2; background: var(--dg-table-head); }
    .employee-photo { width: 38px; height: 38px; border-radius: 12px; object-fit: cover; display: inline-flex; align-items: center; justify-content: center; }
    .employee-photo--initials { background: var(--dg-navy); color: #fff; font-size: 13px; font-weight: 600; letter-spacing: .02em; }
    .employee-columns-menu { min-width: 250px; max-height: 420px; overflow-y: auto; }
    .employee-columns-menu .form-check { display: flex; align-items: center; gap: 10px; padding: 8px 16px; margin: 0; cursor: pointer; }
    .employee-columns-menu .form-check:hover { background: var(--dg-table-head); }
    .employee-columns-menu .form-check-input { margin: 0; }
    .employee-columns-menu .form-check-label { cursor: pointer; font-size: 14px; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = document.querySelector('[data-employee-table]');
    const rows = Array.from(table.querySelectorAll('tbody tr[data-state]'));
    const search = document.getElementById('employeeSearch');
    const department = document.getElementById('employeeDepartment');
    const contract = document.getElementById('employeeContract');
    const tabs = Array.from(document.querySelectorAll('[data-state-filter]'));
    const noResult = document.getElementById('employeesNoResult');
    let state = '';

    const filterEmployees = function () {
        const term = search.value.trim().toLowerCase();
        let visible = 0;
        rows.forEach(function (row) {
            const show = (!term || row.textContent.toLowerCase().includes(term))
                && (!department || !department.value || row.dataset.department === department.value)
                && (!contract || !contract.value || row.dataset.contract === contract.value)
                && (!state || row.dataset.state === state);
            row.style.display = show ? '' : 'none';
            visible += show ? 1 : 0;
        });
        noResult.classList.toggle('d-none', !rows.length || visible > 0);
    };
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            state = tab.dataset.stateFilter;
            tabs.forEach(function (item) {
                item.classList.toggle('is-active', item === tab);
                item.setAttribute('aria-pressed', item === tab ? 'true' : 'false');
            });
            filterEmployees();
        });
    });
    search.addEventListener('input', filterEmployees);
    department?.addEventListener('change', filterEmployees);
    contract?.addEventListener('change', filterEmployees);

    // Colonnes affichées, mémorisées d'une visite à l'autre.
    const menu = document.querySelector('[data-employee-columns-menu]');
    const defaults = ['photo', 'matricule', 'name', 'service', 'position', 'contract', 'status', 'actions'];
    const storageKey = 'diago-rh-employee-columns-v3';
    let visibleColumns;
    try {
        const saved = JSON.parse(localStorage.getItem(storageKey));
        visibleColumns = Array.isArray(saved) && saved.length ? saved : defaults;
    } catch (error) {
        visibleColumns = defaults;
    }
    // Le nom et les actions restent toujours affichés : sans eux la ligne n'est plus lisible.
    const applyColumns = function () {
        visibleColumns = Array.from(new Set([...visibleColumns, 'name', 'actions']));
        table.querySelectorAll('[data-column]').forEach(function (cell) {
            cell.style.display = visibleColumns.includes(cell.dataset.column) ? '' : 'none';
        });
        menu.querySelectorAll('[data-column-toggle]').forEach(function (input) {
            input.checked = visibleColumns.includes(input.dataset.columnToggle);
        });
        localStorage.setItem(storageKey, JSON.stringify(visibleColumns));
    };
    menu.querySelectorAll('[data-column-toggle]').forEach(function (input) {
        input.addEventListener('change', function () {
            const key = input.dataset.columnToggle;
            visibleColumns = input.checked ? [...visibleColumns, key] : visibleColumns.filter(item => item !== key);
            applyColumns();
        });
    });
    applyColumns();
});
</script>
@endsection
