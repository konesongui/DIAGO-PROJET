@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-5">
    <div>
        <div class="text-uppercase text-muted fs-8 fw-bold">RH &amp; Paie</div>
        <h1 class="fs-2 fw-bold mb-1">Tableau RH</h1>
        <p class="text-muted mb-0">Vue synthétique du personnel, des congés et de la paie.</p>
    </div>
    <a href="{{ route('admin.rh') }}" class="btn btn-light">Retour aux modules RH</a>
</div>

<div class="card border-0 shadow-sm mb-5">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.rh.tableau') }}" class="d-flex flex-wrap align-items-end gap-3">
            <div><label class="form-label fw-bold mb-1">Période du</label><input type="date" name="date_debut" value="{{ $periodFrom }}" class="form-control"></div>
            <div><label class="form-label fw-bold mb-1">au</label><input type="date" name="date_fin" value="{{ $periodTo }}" class="form-control"></div>
            <button type="submit" class="btn btn-primary">Filtrer toute la page</button>
            <a href="{{ route('admin.rh.tableau') }}" class="btn btn-light">Réinitialiser</a>
        </form>
    </div>
</div>

<div class="row g-5 mb-5">
    @php($quickStats = [
        ['Employés', $totalEmployees, 'Personnel enregistré', 'primary', 'employees'],
        ['Actifs', $activeEmployees, 'Personnel en poste', 'success', 'active'],
        ['En congé', $onLeave, 'Employés actuellement en congé', 'warning', 'on_leave'],
        ['Demandes en attente', $pendingLeaveRequests, 'Demandes à examiner', 'danger', 'pending_leaves'],
    ])
    @foreach($quickStats as [$label, $value, $description, $color, $detailKey])
        @php($modalId = 'rhDetail' . $loop->index)
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-5">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="text-muted text-uppercase fs-8 fw-bold">{{ $label }}</div>
                        <button type="button" class="btn btn-icon btn-sm btn-light-primary" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}" title="Voir les détails" aria-label="Voir les détails de {{ $label }}"><i class="bi bi-eye"></i></button>
                    </div>
                    <div class="fs-2hx fw-bold text-{{ $color }} mt-3">{{ $value }}</div>
                    <div class="text-muted mt-2">{{ $description }}</div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">{{ $label }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                    <div class="modal-body">
                        <div class="list-group list-group-flush">
                            @forelse($detailLists[$detailKey] as $item)
                                <div class="list-group-item px-0">
                                    <strong>{{ $item->employee->full_name ?? $item->full_name ?? 'Enregistrement' }}</strong>
                                    <div class="text-muted fs-8">{{ $item->matricule ?? '' }} {{ $item->department ?? '' }} {{ $item->status ?? '' }}</div>
                                </div>
                            @empty <div class="text-muted py-3">Aucun enregistrement.</div> @endforelse
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button></div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-5 mb-5">
    @foreach([
        ['Bulletins de paie', $totalPayrolls, 'Enregistrements de paie', 'info', 'payrolls'],
        ['Demandes de congé', $totalLeaveRequests, 'Toutes les demandes', 'primary', 'leaves'],
        ['Types de congé', $totalLeaveTypes, 'Règles configurées', 'success', 'leave_types'],
        ['Permissions', $totalPermissions, $pendingPermissions . ' en attente', 'warning', 'permissions'],
        ['Catégories salariales', $totalSalaryCategories, 'Catégories configurées', 'dark', 'salary_categories'],
        ['Présences QR', $totalAttendanceRecords, 'Pointages enregistrés', 'success', 'attendance'],
        ['Jetons QR', $totalQrTokens, 'Jetons générés', 'info', 'qr_tokens'],
        ['Départements', $departmentsCount, 'Départements employés', 'primary', 'departments'],
    ] as [$label, $value, $description, $color, $detailKey])
        @php($modalId = 'rhModuleDetail' . $loop->index)
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-5">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="text-muted text-uppercase fs-8 fw-bold">{{ $label }}</div>
                        <button type="button" class="btn btn-icon btn-sm btn-light-primary" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}" title="Voir les détails" aria-label="Voir les détails de {{ $label }}"><i class="bi bi-eye"></i></button>
                    </div>
                    <div class="fs-2hx fw-bold text-{{ $color }} mt-3">{{ $value }}</div>
                    <div class="text-muted mt-2">{{ $description }}</div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">{{ $label }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                    <div class="modal-body">
                        <div class="list-group list-group-flush">
                            @forelse($detailLists[$detailKey] as $item)
                                <div class="list-group-item px-0">
                                    <strong>{{ $item->employee->full_name ?? $item->name ?? 'Enregistrement' }}</strong>
                                    <div class="text-muted fs-8">
                                        @if($detailKey === 'payrolls') {{ sprintf('%02d/%04d', $item->month, $item->year) }} - {{ number_format((float) $item->net_salary, 0, ',', ' ') }} XOF
                                        @elseif($detailKey === 'leaves') {{ $item->leaveType->name ?? 'Congé' }} - {{ ucfirst($item->status ?? '') }}
                                        @elseif($detailKey === 'permissions') {{ $item->type ?? 'Permission' }} - {{ ucfirst($item->status ?? '') }}
                                        @elseif($detailKey === 'attendance') {{ $item->attendance_date?->format('d/m/Y') }} - {{ $item->status ?? '' }}
                                        @elseif($detailKey === 'qr_tokens') {{ $item->is_used ? 'Utilisé' : 'Disponible' }}
                                        @elseif($detailKey === 'salary_categories') {{ number_format((float) $item->amount, 0, ',', ' ') }} XOF
                                        @elseif($detailKey === 'leave_types') {{ $item->days }} jour(s)
                                        @else {{ $item->matricule ?? $item->department ?? $item->status ?? '' }}
                                        @endif
                                    </div>
                                </div>
                            @empty <div class="text-muted py-3">Aucun enregistrement.</div> @endforelse
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button></div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-5">
    <div class="col-xl-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">Résumé RH</h3>
            </div>
            <div class="card-body pt-2">
                <div class="d-flex justify-content-between border-bottom py-4">
                    <span class="text-muted">Employés inactifs</span><strong>{{ $inactiveEmployees }}</strong>
                </div>
                <div class="d-flex justify-content-between border-bottom py-4">
                    <span class="text-muted">Congés validés</span><strong>{{ $approvedLeaveRequests }}</strong>
                </div>
                <div class="d-flex justify-content-between py-4">
                    <span class="text-muted">Paie du mois</span><strong>{{ number_format((float) $monthlyPayrollTotal, 0, ',', ' ') }} XOF</strong>
                </div>
                <a href="{{ route('admin.rh.payroll') }}" class="btn btn-light-primary w-100 mt-3">Gérer les bulletins de paie</a>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">Derniers bulletins</h3>
            </div>
            <div class="card-body pt-2">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr class="text-muted text-uppercase fs-8"><th>Employé</th><th>Période</th><th class="text-end">Net</th></tr></thead>
                        <tbody>
                        @forelse($recentPayrolls as $payroll)
                            <tr>
                                <td>{{ $payroll->employee->full_name ?? 'Employé' }}</td>
                                <td>{{ sprintf('%02d/%04d', $payroll->month, $payroll->year) }}</td>
                                <td class="text-end fw-bold">{{ number_format((float) $payroll->net_salary, 0, ',', ' ') }} XOF</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-5">Aucun bulletin enregistré.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-5 mt-1">
    <div class="col-xl-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">Effectifs par département</h3>
            </div>
            <div class="card-body">
                @php($maxDepartment = max((int) ($departmentStats->max() ?? 0), 1))
                @forelse($departmentStats as $department => $count)
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="fw-semibold text-gray-700">{{ $department }}</span>
                            <span class="fw-bold">{{ $count }}</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-primary" style="width: {{ ($count / $maxDepartment) * 100 }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="text-muted text-center py-5">Aucun département renseigné.</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">Congés</h3>
            </div>
            <div class="card-body">
                @php($leaveTotal = max((int) $leaveStats->sum(), 1))
                <div class="d-flex justify-content-center mb-5">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 150px;height:150px;background: conic-gradient(#3b82f6 0 {{ (($leaveStats->get('Approved', 0) / $leaveTotal) * 100) }}%, #f59e0b {{ (($leaveStats->get('Approved', 0) / $leaveTotal) * 100) }}% {{ ((($leaveStats->get('Approved', 0) + $leaveStats->get('Pending', 0)) / $leaveTotal) * 100) }}%, #ef4444 {{ ((($leaveStats->get('Approved', 0) + $leaveStats->get('Pending', 0)) / $leaveTotal) * 100) }}% 100%);">
                        <div class="bg-white rounded-circle d-flex flex-column align-items-center justify-content-center" style="width: 92px;height:92px;">
                            <strong class="fs-2">{{ $leaveStats->sum() }}</strong>
                            <span class="text-muted fs-8">demandes</span>
                        </div>
                    </div>
                </div>
                @foreach($leaveStats as $status => $count)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span class="text-muted">{{ $status }}</span><strong>{{ $count }}</strong>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">Hommes / Femmes</h3>
            </div>
            <div class="card-body">
                @php($genderTotal = max((int) $genderStats->sum(), 1))
                @php($women = (int) $genderStats->get('Femmes', 0))
                @php($men = (int) $genderStats->get('Hommes', 0))
                <div class="d-flex justify-content-center mb-5">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 150px;height:150px;background: conic-gradient(#ec4899 0 {{ (($women / $genderTotal) * 100) }}%, #2563eb {{ (($women / $genderTotal) * 100) }}% 100%);">
                        <div class="bg-white rounded-circle d-flex flex-column align-items-center justify-content-center" style="width: 92px;height:92px;">
                            <strong class="fs-2">{{ $genderStats->sum() }}</strong>
                            <span class="text-muted fs-8">employés</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted">Femmes</span><strong>{{ $women }}</strong></div>
                <div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted">Hommes</span><strong>{{ $men }}</strong></div>
                <div class="d-flex justify-content-between py-2"><span class="text-muted">Non renseigné</span><strong>{{ $genderStats->get('Non renseigné', 0) }}</strong></div>
            </div>
        </div>
    </div>
</div>
@endsection
