@extends('admin.layout')

@section('content')
@php
    $money = fn ($value) => money((float) $value);
    $period = \Illuminate\Support\Carbon::create($year, $month, 1);
    $sent = $payrolls->whereNotNull('sent_at');
    $stats = [
        ['Bulletins', $payrolls->count(), 'bi-receipt', 'orange', $missing->count() . ' employé(s) sans bulletin ce mois'],
        ['Salaire brut', $money($payrolls->sum('gross_salary')), 'bi-cash-stack', 'blue', 'gains cumulés du mois'],
        ['Net versé', $money($payrolls->sum('net_salary')), 'bi-wallet2', 'green', 'montant payé aux employés'],
        ['Charges patronales', $money($payrolls->sum('total_employer_deductions')), 'bi-building', 'purple', 'en plus du brut'],
    ];
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.rh')" back-label="RH & Paie">
        <x-slot:actions>
            <a href="{{ route('admin.rh.payrollBook') }}" class="dg-btn dg-btn--outline"><i class="bi bi-journal-text"></i>Livre de paie</a>
            <a href="{{ route('admin.rh.payroll.create') }}" class="dg-btn dg-btn--primary"><i class="bi bi-plus-lg"></i>Générer un bulletin</a>
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($periodError)<div class="alert alert-warning">{{ $periodError }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="dg-card mb-5">
        <form method="GET" action="{{ route('admin.rh.payroll') }}" class="row g-3 align-items-end">
            <div class="col-sm-4 col-lg-3">
                <label class="form-label" for="payrollMonth">Mois</label>
                <select id="payrollMonth" name="month" class="form-select">
                    @for($index = 1; $index <= 12; $index++)
                        <option value="{{ $index }}" @selected($month === $index)>{{ ucfirst(\Illuminate\Support\Carbon::create()->month($index)->translatedFormat('F')) }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-sm-3 col-lg-2">
                <label class="form-label" for="payrollYear">Année</label>
                <input id="payrollYear" type="number" name="year" min="2000" max="2100" value="{{ $year }}" class="form-control">
            </div>
            <div class="col-sm-5 col-lg-4">
                <label class="form-label" for="payrollEmployee">Employé</label>
                <select id="payrollEmployee" name="employee_id" class="form-select">
                    <option value="">Tous les employés</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) $employeeId === (string) $employee->id)>{{ $employee->full_name }}{{ $employee->matricule ? ' — ' . $employee->matricule : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-12 col-lg-3 d-flex gap-2">
                <button type="submit" class="dg-btn dg-btn--primary flex-grow-1"><i class="bi bi-funnel"></i>Afficher</button>
                <a href="{{ route('admin.rh.payroll') }}" class="dg-btn dg-btn--outline" title="Revenir au mois précédent" aria-label="Revenir au mois précédent"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>

    <div class="dg-kpi-grid">
        @foreach($stats as [$label, $value, $icon, $color, $hint])
            <x-dg.kpi :label="$label" :value="$value" :icon="$icon" :color="$color" :hint="$hint" />
        @endforeach
    </div>

    <form method="POST" action="{{ route('admin.rh.payroll.emailBulk') }}" id="bulkPayrollForm">@csrf</form>

    <div class="dg-card dg-card--table">
        <div class="dg-card__header flex-wrap">
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-orange"><i class="bi bi-receipt"></i></span>Bulletins de {{ mb_strtolower($period->translatedFormat('F Y')) }}</h2>
            <div class="d-flex flex-wrap align-items-center gap-2">
                @if($payrolls->isNotEmpty())
                    <span class="dg-card__meta">{{ $sent->count() }} envoyé(s) sur {{ $payrolls->count() }}</span>
                    <button type="button" class="dg-btn dg-btn--outline dg-btn--sm d-none" id="bulkEmail"><i class="bi bi-envelope"></i><span id="bulkEmailLabel">Envoyer</span></button>
                @endif
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 payrolls-table no-export" id="payrollsTable">
                <thead>
                    <tr>
                        <th class="payrolls-table__check"><input class="form-check-input" type="checkbox" id="selectAllPayrolls" aria-label="Tout sélectionner"></th>
                        <th>Employé</th>
                        <th class="text-end">Brut</th>
                        <th class="text-end">Retenues</th>
                        <th class="text-end">Net à payer</th>
                        <th>Envoi</th>
                        <th class="text-end"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($payrolls as $payroll)
                    <tr>
                        <td class="payrolls-table__check">
                            <input class="form-check-input payroll-checkbox" form="bulkPayrollForm" type="checkbox" name="payroll_ids[]" value="{{ $payroll->id }}"
                                aria-label="Sélectionner le bulletin de {{ $payroll->employee?->full_name }}">
                        </td>
                        <td>
                            <a href="{{ route('admin.rh.payroll.show', $payroll) }}" class="d-block fw-semibold text-reset text-decoration-none">{{ $payroll->employee?->full_name ?? 'Employé supprimé' }}</a>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ collect([$payroll->employee?->matricule, $payroll->employee?->department])->filter()->implode(' · ') ?: '—' }}</span>
                        </td>
                        <td class="text-end dg-cell-num">{{ $money($payroll->gross_salary) }}</td>
                        <td class="text-end dg-cell-num dg-amount-negative">− {{ $money($payroll->total_employee_deductions) }}</td>
                        <td class="text-end dg-cell-num fw-semibold">{{ $money($payroll->net_salary) }}</td>
                        <td>
                            @if($payroll->sent_at)
                                <span class="dg-badge dg-badge--success"><i class="bi bi-check-circle"></i>Envoyé</span>
                                <span class="d-block dg-muted" style="font-size:12px">le {{ $payroll->sent_at->format('d/m/Y') }}</span>
                            @elseif(filter_var($payroll->employee?->email, FILTER_VALIDATE_EMAIL))
                                <span class="dg-badge dg-badge--warning"><i class="bi bi-envelope"></i>À envoyer</span>
                            @else
                                <span class="dg-badge dg-badge--neutral"><i class="bi bi-envelope-slash"></i>Sans e-mail</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light action-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions pour le bulletin de {{ $payroll->employee?->full_name }}"></button>
                                <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                                    <li><a class="dropdown-item" href="{{ route('admin.rh.payroll.show', $payroll) }}"><i class="bi bi-eye"></i>Voir le bulletin</a></li>
                                    <li><a class="dropdown-item" href="{{ route('admin.rh.payroll.pdf', $payroll) }}"><i class="bi bi-file-earmark-pdf"></i>Télécharger le PDF</a></li>
                                    @if(filter_var($payroll->employee?->email, FILTER_VALIDATE_EMAIL))
                                        <li>
                                            <form method="POST" action="{{ route('admin.rh.payroll.email', $payroll) }}" onsubmit="return confirm('Envoyer ce bulletin à {{ addslashes($payroll->employee->email) }} ?')">
                                                @csrf
                                                <button class="dropdown-item"><i class="bi bi-envelope"></i>Envoyer par e-mail</button>
                                            </form>
                                        </li>
                                    @endif
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-5">
                            <div class="dg-chart-empty" style="min-height:200px">
                                <span class="dg-tile dg-tone-orange"><i class="bi bi-receipt"></i></span>
                                <div><strong>Aucun bulletin pour {{ mb_strtolower($period->translatedFormat('F Y')) }}</strong>Générez les bulletins du mois : le net est sorti de la caisse ou de la banque choisie.</div>
                                <a href="{{ route('admin.rh.payroll.create') }}" class="dg-btn dg-btn--primary dg-btn--sm"><i class="bi bi-plus-lg"></i>Générer un bulletin</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
                @if($payrolls->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td colspan="2">Total du mois</td>
                            <td class="text-end dg-cell-num">{{ $money($payrolls->sum('gross_salary')) }}</td>
                            <td class="text-end dg-cell-num">− {{ $money($payrolls->sum('total_employee_deductions')) }}</td>
                            <td class="text-end dg-cell-num">{{ $money($payrolls->sum('net_salary')) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @if($missing->isNotEmpty())
            <div class="payroll-missing mt-4">
                <i class="bi bi-exclamation-triangle"></i>
                <div>
                    <strong>{{ $missing->count() }} employé(s) en poste sans bulletin ce mois :</strong>
                    {{ $missing->take(6)->pluck('full_name')->implode(', ') }}{{ $missing->count() > 6 ? ', et ' . ($missing->count() - 6) . ' autre(s)' : '' }}.
                </div>
            </div>
        @endif
    </div>
</div>

<style>
    .payrolls-table { min-width: 800px; }
    .payrolls-table__check { width: 44px; }
    .dg-scope .payrolls-table > thead > tr > th, .dg-scope .payrolls-table > tbody > tr > td, .dg-scope .payrolls-table > tfoot > tr > td { padding-left: 10px !important; padding-right: 10px !important; }
    .payrolls-table tfoot td { border-top: 2px solid var(--dg-border-strong); font-weight: 700; color: var(--dg-navy); }
    .payroll-missing { display: flex; gap: 10px; padding: 12px 14px; border-radius: var(--dg-radius); background: rgba(217, 119, 6, .08); font-size: 13px; }
    .payroll-missing .bi { color: #d97706; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const boxes = Array.from(document.querySelectorAll('.payroll-checkbox'));
    const all = document.getElementById('selectAllPayrolls');
    const button = document.getElementById('bulkEmail');
    if (!boxes.length || !button) return;
    const label = document.getElementById('bulkEmailLabel');
    const refresh = function () {
        const selected = boxes.filter(box => box.checked).length;
        button.classList.toggle('d-none', selected === 0);
        label.textContent = 'Envoyer ' + selected + ' bulletin(s)';
        if (all) all.checked = selected === boxes.length;
    };
    boxes.forEach(box => box.addEventListener('change', refresh));
    all?.addEventListener('change', function () {
        boxes.forEach(box => { box.checked = all.checked; });
        refresh();
    });
    button.addEventListener('click', function () {
        const selected = boxes.filter(box => box.checked).length;
        if (selected && confirm('Envoyer ' + selected + ' bulletin(s) par e-mail aux employés concernés ?')) {
            document.getElementById('bulkPayrollForm').submit();
        }
    });
    refresh();
});
</script>
@endsection
