<?php

namespace App\Http\Controllers\Admin;

use App\Models\Employee;
use App\Models\SalaryCategory;
use App\Models\PermissionRequest;
use App\Models\LeaveType;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\QrToken;
use App\Models\StaffAttendanceQr;
use App\Models\CashAccount;
use App\Models\BankAccount;
use App\Models\CashMovement;
use App\Models\BankTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class RhController extends AdminController
{
    private function modules(): array
    {
        return [
            'tableau-rh' => ['title' => 'Tableau RH', 'description' => 'Vue d’ensemble du personnel, des congés et de la paie.', 'icon' => 'bi-bar-chart', 'url' => route('admin.rh'), 'color' => 'blue'],
            'personnel' => ['title' => 'Liste du personnel', 'description' => 'Fiches et informations des employés.', 'icon' => 'bi-people', 'color' => 'purple'],
            'qr-code' => ['title' => 'Afficher QR Code', 'description' => 'QR pour pointage à l’entrée.', 'icon' => 'bi-qr-code', 'color' => 'cyan'],
            'presences' => ['title' => 'Présences du jour', 'description' => 'Suivi des présences et absences du jour.', 'icon' => 'bi-check2-circle', 'color' => 'green'],
            'rapport-presence' => ['title' => 'Rapport Présence', 'description' => 'Rapports et statistiques QR.', 'icon' => 'bi-graph-up', 'color' => 'teal'],
            'bulletins-paie' => ['title' => 'Bulletin de paie', 'description' => 'Gestion des paies et bulletins.', 'icon' => 'bi-receipt', 'color' => 'orange'],
            'livre-paie' => ['title' => 'Livre de paie', 'description' => 'Registre des paies de l’entreprise.', 'icon' => 'bi-journal-text', 'color' => 'indigo'],
            'categories-salariales' => ['title' => 'Catégorie salariale', 'description' => 'Gestion des catégories salariales.', 'icon' => 'bi-tag', 'color' => 'pink'],
            'parametrage-conges' => ['title' => 'Paramétrage de congé', 'description' => 'Configurez les règles de congés.', 'icon' => 'bi-gear', 'color' => 'blue'],
            'conges' => ['title' => 'Liste des congés', 'description' => 'Validation des demandes de congés.', 'icon' => 'bi-calendar-range', 'color' => 'green'],
            'calendrier-conges' => ['title' => 'Calendrier des congés', 'description' => 'Visualisation globale des congés.', 'icon' => 'bi-calendar3', 'color' => 'cyan'],
            'permissions' => ['title' => 'Demande de permission', 'description' => 'Gérez les demandes de permission.', 'icon' => 'bi-pencil-square', 'color' => 'purple'],
        ];
    }

    public function index()
    {
        $companyId = auth()->user()->entreprise_id;
        $employees = Employee::where('entreprise_id', $companyId)->latest('hire_date')->limit(6)->get();
        $totalEmployees = Employee::where('entreprise_id', $companyId)->count();
        $activeEmployees = Employee::where('entreprise_id', $companyId)->where('status', 'active')->count();
        $onLeave = Employee::where('entreprise_id', $companyId)->where('status', 'on_leave')->count();
        $inactiveEmployees = Employee::where('entreprise_id', $companyId)->whereNotIn('status', ['active', 'on_leave'])->count();

        $payrollTrend = collect(range(5, 0))->map(function ($offset) use ($companyId) {
            $monthDate = now()->subMonths($offset)->startOfMonth();
            $total = Payroll::where('entreprise_id', $companyId)
                ->where('year', $monthDate->year)
                ->where('month', $monthDate->month)
                ->sum('net_salary');

            return [
                'label' => $monthDate->translatedFormat('M'),
                'value' => (float) $total,
            ];
        });

        $trendMax = max($payrollTrend->max('value') ?? 0, 1);
        $chartPoints = $payrollTrend->map(function ($point, $index) use ($trendMax) {
            $x = 20 + ($index * 110);
            $y = 170 - (($point['value'] / $trendMax) * 120);
            return $x . ',' . $y;
        })->implode(' ');

        $pendingLeaveRequests = LeaveRequest::where('entreprise_id', $companyId)->where('status', 'pending')->count();
        $approvedLeaveRequests = LeaveRequest::where('entreprise_id', $companyId)->where('status', 'approved')->count();
        $totalLeaveRequests = LeaveRequest::where('entreprise_id', $companyId)->count();
        $totalLeaveTypes = LeaveType::where('entreprise_id', $companyId)->count();
        $totalPermissions = PermissionRequest::where('entreprise_id', $companyId)->count();
        $pendingPermissions = PermissionRequest::where('entreprise_id', $companyId)->where('status', 'pending')->count();
        $totalPayrolls = Payroll::where('entreprise_id', $companyId)->count();
        $totalSalaryCategories = SalaryCategory::where('entreprise_id', $companyId)->count();
        $totalQrTokens = QrToken::where('entreprise_id', $companyId)->count();
        $totalAttendanceRecords = StaffAttendanceQr::where('entreprise_id', $companyId)->count();
        $departmentsCount = Employee::where('entreprise_id', $companyId)
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct('department')
            ->count('department');
        $departmentStats = Employee::where('entreprise_id', $companyId)
            ->get(['department'])
            ->groupBy(function ($employee) {
                return trim((string) $employee->department) ?: 'Non affecté';
            })
            ->map->count()
            ->sortDesc();
        $leaveStats = LeaveRequest::where('entreprise_id', $companyId)
            ->get(['status'])
            ->groupBy(fn ($leave) => ucfirst((string) ($leave->status ?: 'inconnu')))
            ->map->count();
        $genderStats = Employee::where('entreprise_id', $companyId)
            ->get(['gender'])
            ->groupBy(function ($employee) {
                $gender = strtolower(trim((string) $employee->gender));
                return in_array($gender, ['f', 'female', 'féminin', 'femme'], true) ? 'Femmes'
                    : (in_array($gender, ['m', 'male', 'masculin', 'homme'], true) ? 'Hommes' : 'Non renseigné');
            })
            ->map->count();
        $departmentStats = Employee::where('entreprise_id', $companyId)
            ->get(['department'])
            ->groupBy(function ($employee) {
                return trim((string) $employee->department) ?: 'Non affecté';
            })
            ->map->count()
            ->sortDesc();
        $leaveStats = LeaveRequest::where('entreprise_id', $companyId)
            ->get(['status'])
            ->groupBy(fn ($leave) => ucfirst((string) ($leave->status ?: 'inconnu')))
            ->map->count();
        $genderStats = Employee::where('entreprise_id', $companyId)
            ->get(['gender'])
            ->groupBy(function ($employee) {
                $gender = strtolower(trim((string) $employee->gender));
                return in_array($gender, ['f', 'female', 'féminin', 'femme'], true) ? 'Femmes'
                    : (in_array($gender, ['m', 'male', 'masculin', 'homme'], true) ? 'Hommes' : 'Non renseigné');
            })
            ->map->count();
        $monthlyPayrollTotal = Payroll::where('entreprise_id', $companyId)
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->sum('net_salary');

        $recentPayrolls = Payroll::where('entreprise_id', $companyId)
            ->with('employee')
            ->latest('year')
            ->latest('month')
            ->limit(5)
            ->get();

        $attendanceRate = $totalEmployees > 0 ? round((($activeEmployees / $totalEmployees) * 100), 1) : 0;

        return $this->page('rh', [
            'title' => 'RH & Paie',
            'cards' => [
                ['title' => 'Employés', 'description' => 'Total du personnel', 'value' => (string) $totalEmployees, 'link' => route('admin.rh.module', 'personnel')],
                ['title' => 'Actifs', 'description' => 'Personnel en poste', 'value' => (string) $activeEmployees, 'link' => route('admin.rh.module', 'personnel')],
                ['title' => 'Congés', 'description' => 'Demandes en attente', 'value' => (string) $pendingLeaveRequests, 'link' => route('admin.rh.leaves')],
                ['title' => 'Paie', 'description' => 'Ce mois', 'value' => number_format((float) $monthlyPayrollTotal, 0, ',', ' ') . ' XOF', 'link' => route('admin.rh.payroll')],
            ],
            'employees' => $employees,
            'totalEmployees' => $totalEmployees,
            'activeEmployees' => $activeEmployees,
            'onLeave' => $onLeave,
            'inactiveEmployees' => $inactiveEmployees,
            'attendanceRate' => $attendanceRate,
            'payrollTrend' => $payrollTrend,
            'chartPoints' => $chartPoints,
            'pendingLeaveRequests' => $pendingLeaveRequests,
            'approvedLeaveRequests' => $approvedLeaveRequests,
            'totalLeaveRequests' => $totalLeaveRequests,
            'totalLeaveTypes' => $totalLeaveTypes,
            'totalPermissions' => $totalPermissions,
            'pendingPermissions' => $pendingPermissions,
            'totalPayrolls' => $totalPayrolls,
            'totalSalaryCategories' => $totalSalaryCategories,
            'totalQrTokens' => $totalQrTokens,
            'totalAttendanceRecords' => $totalAttendanceRecords,
            'departmentsCount' => $departmentsCount,
            'departmentStats' => $departmentStats,
            'leaveStats' => $leaveStats,
            'genderStats' => $genderStats,
            'monthlyPayrollTotal' => $monthlyPayrollTotal,
            'recentPayrolls' => $recentPayrolls,
            'modules' => $this->modules(),
        ]);
    }

    public function module(string $module)
    {
        $definition = $this->modules()[$module] ?? null;
        abort_unless($definition, 404);
        if ($module === 'tableau-rh') {
            return redirect()->route('admin.rh.tableau');
        }
        if ($module === 'categories-salariales') {
           return redirect()->route('admin.rh.salaryCategories');
        }
        if ($module === 'permissions') {
            return redirect()->route('admin.rh.permissions');
        }
        if ($module === 'parametrage-conges') return redirect()->route('admin.rh.leaveTypes');
        if ($module === 'conges') return redirect()->route('admin.rh.leaves');
        if ($module === 'calendrier-conges') return redirect()->route('admin.rh.leaveCalendar');
        if ($module === 'bulletins-paie') return redirect()->route('admin.rh.payroll');
        if ($module === 'livre-paie') return redirect()->route('admin.rh.payrollBook');
        if ($module === 'qr-code') return redirect()->route('admin.rh.qr.display');
        if ($module === 'presences') return redirect()->route('admin.rh.attendance.today');
        if ($module === 'rapport-presence') return redirect()->route('admin.rh.attendance.report');

        if ($module === 'personnel') {
            return $this->employees($definition);
        }

        abort(404);
    }

    /**
     * Liste du personnel : fiches, service, contrat et état de chaque employé.
     *
     * Personne n'est jamais passé « en congé » dans la fiche : l'état est lu
     * dans les demandes de congé validées qui couvrent la journée.
     */
    protected function employees(array $definition)
    {
        $companyId = auth()->user()->entreprise_id;
        $today = now()->startOfDay();
        $employees = Employee::where('entreprise_id', $companyId)
            ->orderBy('full_name')->get();
        $onLeaveToday = LeaveRequest::where('entreprise_id', $companyId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)
            ->with('leaveType:id,name')
            ->get()
            ->keyBy('employee_id');

        return $this->page('rh-employees', [
            'title' => $definition['title'],
            'subtitle' => 'Fiches du personnel, contrats et présence du jour.',
            'module' => $definition,
            'modules' => $this->modules(),
            'employees' => $employees,
            'onLeaveToday' => $onLeaveToday,
            'payrollCounts' => Payroll::where('entreprise_id', $companyId)
                ->selectRaw('employee_id, count(*) as total')->groupBy('employee_id')->pluck('total', 'employee_id'),
            'departments' => \App\Models\Department::where('entreprise_id', $companyId)->where('is_active', true)
                ->orderBy('name')->pluck('name')
                ->merge($employees->pluck('department')->filter())->unique()->sort()->values(),
        ]);
    }

    public function tableau(Request $request)
    {
        $companyId = auth()->user()->entreprise_id;
        $today = now()->startOfDay();
        // Période d'analyse : depuis le 1er janvier par défaut.
        $periodError = null;
        try {
            $from = Carbon::parse($request->input('date_debut', now()->startOfYear()->toDateString()))->startOfDay();
            $to = Carbon::parse($request->input('date_fin', now()->toDateString()))->endOfDay();
        } catch (\Throwable) {
            $from = $to = null;
        }
        if (! $from || $from->gt($to)) {
            $periodError = 'La période choisie est invalide : la date de début doit précéder la date de fin. Affichage depuis le 1er janvier.';
            $from = now()->startOfYear();
            $to = now()->endOfDay();
        }

        // L'effectif est un état du jour : le filtrer sur la période ne laissait
        // que les fiches créées pendant la période, et l'effectif semblait vide.
        $employees = Employee::where('entreprise_id', $companyId)->get();
        $onLeaveToday = LeaveRequest::where('entreprise_id', $companyId)->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->pluck('employee_id');
        $active = $employees->where('status', '!=', 'inactive');
        $hired = $employees->filter(fn ($employee) => ($employee->registered_at ?? $employee->hire_date)?->between($from, $to));
        $left = $employees->filter(fn ($employee) => $employee->status === 'inactive' && $employee->contract_end_date?->between($from, $to));

        $leaveRequests = LeaveRequest::where('entreprise_id', $companyId)
            ->with(['employee:id,full_name,department', 'leaveType:id,name'])->get();
        $periodLeaves = $leaveRequests->filter(fn ($leave) => $leave->start_date?->between($from, $to));
        $permissions = PermissionRequest::where('entreprise_id', $companyId)->with('employee:id,full_name')->get();
        $periodPermissions = $permissions->filter(fn ($permission) => $permission->start_date?->between($from, $to));

        $payrolls = Payroll::where('entreprise_id', $companyId)->with('employee:id,full_name,department')->get()
            ->filter(fn ($payroll) => Carbon::create($payroll->year, $payroll->month, 1)
                ->between($from->copy()->startOfMonth(), $to->copy()->endOfMonth()))->values();
        // Masse salariale mois par mois, sur la période (24 mois au plus).
        $cursor = $from->copy()->startOfMonth()->max($to->copy()->startOfMonth()->subMonths(23));
        $months = collect();
        while ($cursor->lte($to)) {
            $ofMonth = $payrolls->filter(fn ($payroll) => $payroll->year === (int) $cursor->format('Y') && $payroll->month === (int) $cursor->format('m'));
            $months->push([
                'label' => ucfirst($cursor->translatedFormat($from->year === $to->year ? 'M' : 'M y')),
                'net' => round((float) $ofMonth->sum('net_salary'), 2),
                'charges' => round((float) $ofMonth->sum('employer_charges'), 2),
                'count' => $ofMonth->count(),
            ]);
            $cursor->addMonth();
        }

        $attendanceToday = StaffAttendanceQr::where('entreprise_id', $companyId)
            ->whereDate('attendance_date', $today->toDateString())->get();

        return $this->page('rh-tableau', [
            'title' => 'Tableau RH',
            'subtitle' => 'Effectif, mouvements, congés et masse salariale de la période.',
            'periodFrom' => $from->toDateString(),
            'periodTo' => $to->toDateString(),
            'periodError' => $periodError,
            'headcount' => $active->count(),
            'onLeaveToday' => $active->whereIn('id', $onLeaveToday)->count(),
            'hired' => $hired->values(),
            'left' => $left->values(),
            'payrollTotal' => round((float) $payrolls->sum('net_salary'), 2),
            'payrollCharges' => round((float) $payrolls->sum('employer_charges'), 2),
            'payrolls' => $payrolls->sortByDesc(fn ($payroll) => sprintf('%04d%02d', $payroll->year, $payroll->month))->take(8)->values(),
            'payrollCount' => $payrolls->count(),
            'months' => $months,
            'pendingLeaves' => $leaveRequests->where('status', 'pending')->values(),
            'periodLeaveDays' => (int) $periodLeaves->where('status', 'approved')->sum('days'),
            'pendingPermissions' => $permissions->where('status', 'pending')->values(),
            'periodPermissions' => $periodPermissions->count(),
            'presentToday' => $attendanceToday->whereNotNull('arrival_time')->count(),
            'stillWorking' => $attendanceToday->whereNotNull('arrival_time')->whereNull('departure_time')->count(),
            'departments' => $active->groupBy(fn ($employee) => trim((string) $employee->department) ?: 'Sans service')
                ->map(fn ($items, $name) => ['name' => $name, 'count' => $items->count(),
                    'salary' => round((float) $items->sum('monthly_salary'), 2)])
                ->sortByDesc('count')->values(),
            'contracts' => $active->groupBy(fn ($employee) => trim((string) $employee->contract_type) ?: 'Non renseigné')->map->count()->sortDesc(),
            'genders' => $active->groupBy(function ($employee) {
                $gender = mb_strtolower(trim((string) $employee->gender));

                return in_array($gender, ['f', 'female', 'féminin', 'femme'], true) ? 'Femmes'
                    : (in_array($gender, ['m', 'male', 'masculin', 'homme'], true) ? 'Hommes' : 'Non renseigné');
            })->map->count(),
            'salaryMass' => round((float) $active->sum('monthly_salary'), 2),
        ]);
    }

    public function leaveTypes()
    {
        $companyId = auth()->user()->entreprise_id;
        // Usage réel de chaque type : demandes déposées et jours déjà validés.
        $usage = LeaveRequest::where('entreprise_id', $companyId)
            ->selectRaw("leave_type_id, count(*) as requests, sum(case when status = 'approved' then days else 0 end) as days")
            ->groupBy('leave_type_id')->get()->keyBy('leave_type_id');

        return $this->page('rh-leave-types', [
            'title' => 'Paramétrage des congés',
            'subtitle' => 'Types de congé ouverts aux employés et nombre de jours accordés par an.',
            'types' => LeaveType::where('entreprise_id', $companyId)->orderBy('name')->get(),
            'usage' => $usage,
        ]);
    }

    public function storeLeaveType(Request $request)
    {
        $data = $request->validate(
            ['name' => ['required', 'string', 'max:100'], 'days' => ['required', 'integer', 'min:1', 'max:365'], 'description' => ['nullable', 'string', 'max:1000']],
            $this->leaveTypeMessages()
        );
        $this->ensureLeaveTypeNameIsFree($data['name']);
        LeaveType::create($data + ['entreprise_id' => auth()->user()->entreprise_id, 'is_active' => true]);

        return back()->with('success', 'Type de congé « ' . $data['name'] . ' » créé : ' . $data['days'] . ' jour(s) par an et par employé.');
    }

    public function updateLeaveType(Request $request, LeaveType $leaveType)
    {
        abort_unless($leaveType->entreprise_id === auth()->user()->entreprise_id, 403);
        $data = $request->validate(
            ['name' => ['required', 'string', 'max:100'], 'days' => ['required', 'integer', 'min:1', 'max:365'], 'description' => ['nullable', 'string', 'max:1000']],
            $this->leaveTypeMessages()
        );
        $this->ensureLeaveTypeNameIsFree($data['name'], $leaveType->id);
        $leaveType->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Type de congé « ' . $data['name'] . ' » mis à jour.');
    }

    public function destroyLeaveType(LeaveType $leaveType)
    {
        abort_unless($leaveType->entreprise_id === auth()->user()->entreprise_id, 403);
        if ($leaveType->requests()->exists()) {
            return back()->withErrors(['type' => 'Des demandes utilisent le congé « ' . $leaveType->name . ' » : désactivez-le plutôt, l’historique doit rester lisible.']);
        }
        $leaveType->delete();

        return back()->with('success', 'Type de congé « ' . $leaveType->name . ' » supprimé.');
    }

    private function leaveTypeMessages(): array
    {
        return [
            'name.required' => 'Donnez un nom au type de congé.',
            'days.required' => 'Indiquez le nombre de jours accordés par an.',
            'days.*' => 'Le nombre de jours doit être compris entre 1 et 365.',
        ];
    }

    /** Deux types de congé de même nom rendraient les soldes illisibles. */
    private function ensureLeaveTypeNameIsFree(string $name, ?int $ignoreId = null): void
    {
        $exists = LeaveType::where('entreprise_id', auth()->user()->entreprise_id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn ($query) => $query->where('id', '<>', $ignoreId))
            ->exists();
        if ($exists) {
            throw \Illuminate\Validation\ValidationException::withMessages(['name' => 'Un type de congé porte déjà ce nom.']);
        }
    }

    public function leaveRequests()
    {
        $user = auth()->user();
        $companyId = $user->entreprise_id;
        $isAdmin = $user->hasRole('admin');
        $employee = Employee::where('user_id', $user->id)->first();

        $requests = LeaveRequest::where('entreprise_id', $companyId)
            ->with(['employee:id,full_name,department,position', 'leaveType:id,name,days', 'reviewer:id,name'])
            ->when(! $isAdmin, fn ($query) => $query->where('employee_id', $employee?->id))
            ->orderByDesc('start_date')->orderByDesc('id')->get();

        return $this->page('rh-leave-requests', [
            'title' => 'Liste des congés',
            'subtitle' => $isAdmin ? 'Demandes de congé des employés, soldes et validation.' : 'Vos demandes de congé et votre solde.',
            'requests' => $requests,
            'types' => LeaveType::where('entreprise_id', $companyId)->where('is_active', true)->orderBy('name')->get(),
            'employees' => $isAdmin
                ? Employee::where('entreprise_id', $companyId)->where('status', 'active')->orderBy('full_name')->get(['id', 'full_name', 'department'])
                : collect(),
            'balances' => $this->leaveBalances($companyId, now()->year),
            'year' => now()->year,
            'isAdmin' => $isAdmin,
            'employee' => $employee,
        ]);
    }

    /**
     * Jours de congé déjà validés cette année, par employé et par type.
     *
     * @return \Illuminate\Support\Collection<string, int> clé « employé-type »
     */
    private function leaveBalances(int $companyId, int $year): \Illuminate\Support\Collection
    {
        return LeaveRequest::where('entreprise_id', $companyId)
            ->where('status', 'approved')->whereYear('start_date', $year)
            ->get(['employee_id', 'leave_type_id', 'days'])
            ->groupBy(fn ($request) => $request->employee_id . '-' . $request->leave_type_id)
            ->map(fn ($group) => (int) $group->sum('days'));
    }

    public function storeLeaveRequest(Request $request)
    {
        $user = auth()->user();
        $companyId = $user->entreprise_id;
        $isAdmin = $user->hasRole('admin');
        $rules = [
            'leave_type_id' => ['required', 'integer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
        if ($isAdmin) {
            $rules['employee_id'] = ['required', 'integer'];
        }
        $data = $request->validate($rules, [
            'employee_id.required' => 'Choisissez l’employé concerné.',
            'leave_type_id.required' => 'Choisissez le type de congé.',
            'start_date.required' => 'Indiquez le premier jour du congé.',
            'end_date.after_or_equal' => 'Le dernier jour du congé doit suivre le premier.',
            'end_date.required' => 'Indiquez le dernier jour du congé.',
        ]);

        $employee = $isAdmin
            ? Employee::where('entreprise_id', $companyId)->where('status', 'active')->find($data['employee_id'])
            : Employee::where('user_id', $user->id)->first();
        $fail = fn (string $field, string $message) => throw \Illuminate\Validation\ValidationException::withMessages([$field => $message]);
        if (! $employee) {
            $fail($isAdmin ? 'employee_id' : 'leave_type_id', $isAdmin
                ? 'Choisissez un employé actif de l’entreprise.'
                : 'Votre compte n’est relié à aucune fiche employé : demandez à l’administration d’enregistrer votre congé.');
        }
        $type = LeaveType::where('entreprise_id', $companyId)->where('is_active', true)->find($data['leave_type_id']);
        if (! $type) {
            $fail('leave_type_id', 'Choisissez un type de congé encore ouvert.');
        }

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        $days = $start->diffInDays($end) + 1;
        if ($days > $type->days) {
            $fail('end_date', 'Le congé « ' . $type->name .' » est limité à ' . $type->days . ' jour(s) par an : cette demande en compte ' . $days . '.');
        }

        // Solde annuel : les jours déjà validés cette année s'ajoutent à la demande.
        $used = (int) LeaveRequest::where('entreprise_id', $companyId)
            ->where('employee_id', $employee->id)->where('leave_type_id', $type->id)
            ->where('status', 'approved')->whereYear('start_date', $start->year)->sum('days');
        if ($used + $days > $type->days) {
            $fail('end_date', $employee->full_name . ' a déjà pris ' . $used . ' jour(s) de « ' . $type->name . ' » en ' . $start->year
                . ' : il reste ' . max(0, $type->days - $used) . ' jour(s) sur ' . $type->days . '.');
        }

        // Deux congés ne peuvent pas se chevaucher pour le même employé.
        $overlap = LeaveRequest::where('entreprise_id', $companyId)
            ->where('employee_id', $employee->id)->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)
            ->with('leaveType:id,name')->first();
        if ($overlap) {
            $fail('start_date', $employee->full_name . ' a déjà un congé sur cette période ('
                . ($overlap->leaveType?->name ?: 'congé') . ' du ' . $overlap->start_date->format('d/m/Y') . ' au ' . $overlap->end_date->format('d/m/Y') . ').');
        }

        LeaveRequest::create([
            'entreprise_id' => $companyId,
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $days,
            'reason' => $data['reason'] ?? null,
            // Saisi par l'administration, le congé est validé d'emblée : c'est elle qui valide.
            'status' => $isAdmin ? 'approved' : 'pending',
            'reviewed_by' => $isAdmin ? $user->id : null,
            'review_comment' => $isAdmin ? 'Congé enregistré par l’administration.' : null,
        ]);

        return back()->with('success', $isAdmin
            ? 'Congé de ' . $employee->full_name . ' enregistré et validé : ' . $days . ' jour(s) du ' . $start->format('d/m/Y') . ' au ' . $end->format('d/m/Y') . '.'
            : 'Demande envoyée : ' . $days . ' jour(s) du ' . $start->format('d/m/Y') . ' au ' . $end->format('d/m/Y') . '.');
    }

    public function reviewLeaveRequest(Request $request, LeaveRequest $leaveRequest)
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);
        abort_unless($leaveRequest->entreprise_id === auth()->user()->entreprise_id, 403);
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'review_comment' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($leaveRequest->status !== 'pending') {
            return back()->withErrors(['request' => 'Cette demande a déjà été traitée ('
                . ($leaveRequest->status === 'approved' ? 'validée' : 'refusée') . ').']);
        }

        // Une validation ne doit pas créer un chevauchement avec un congé déjà validé.
        if ($data['status'] === 'approved') {
            $overlap = LeaveRequest::where('entreprise_id', $leaveRequest->entreprise_id)
                ->where('employee_id', $leaveRequest->employee_id)->where('status', 'approved')
                ->whereDate('start_date', '<=', $leaveRequest->end_date)->whereDate('end_date', '>=', $leaveRequest->start_date)
                ->first();
            if ($overlap) {
                return back()->withErrors(['request' => 'Un congé validé couvre déjà cette période (du '
                    . $overlap->start_date->format('d/m/Y') . ' au ' . $overlap->end_date->format('d/m/Y') . ').']);
            }
        }

        $leaveRequest->update($data + ['reviewed_by' => auth()->id()]);

        return back()->with('success', 'Congé de ' . ($leaveRequest->employee?->full_name ?: 'l’employé') . ' '
            . ($data['status'] === 'approved' ? 'validé' : 'refusé') . '.');
    }

    public function leaveCalendar(Request $request)
    {
        $companyId = auth()->user()->entreprise_id;
        try {
            $month = Carbon::parse($request->input('mois', now()->format('Y-m')) . '-01')->startOfMonth();
        } catch (\Throwable) {
            $month = now()->startOfMonth();
        }
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $leaves = LeaveRequest::where('entreprise_id', $companyId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)
            ->with(['employee:id,full_name,department', 'leaveType:id,name'])
            ->orderBy('start_date')->get();

        return $this->page('rh-leave-calendar', [
            'title' => 'Calendrier des congés',
            'subtitle' => 'Congés validés, employé par employé, sur le mois choisi.',
            'month' => $month,
            'leaves' => $leaves,
            'pending' => LeaveRequest::where('entreprise_id', $companyId)->where('status', 'pending')
                ->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)->count(),
        ]);
    }

    public function payroll(Request $request)
    {
        $companyId = auth()->user()->entreprise_id;
        $month = (int) $request->integer('month', (int) now()->subMonth()->format('m'));
        $year = (int) $request->integer('year', (int) now()->subMonth()->format('Y'));
        $periodError = null;
        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            $periodError = 'Le mois choisi est invalide : affichage du mois précédent.';
            $month = (int) now()->subMonth()->format('m');
            $year = (int) now()->subMonth()->format('Y');
        }
        $employeeId = $request->input('employee_id');
        $payrolls = Payroll::where('entreprise_id', $companyId)->with('employee:id,full_name,matricule,department,email')
            ->where('month', $month)->where('year', $year)
            ->when($employeeId, fn ($query) => $query->where('employee_id', $employeeId))
            ->get()->sortBy(fn ($payroll) => $payroll->employee?->full_name)->values();
        $employees = Employee::where('entreprise_id', $companyId)->where('status', 'active')->orderBy('full_name')->get(['id', 'full_name', 'matricule']);

        return $this->page('rh-payroll', [
            'title' => 'Bulletins de paie',
            'subtitle' => 'Bulletins du mois, montants versés et envoi aux employés.',
            'month' => $month, 'year' => $year, 'periodError' => $periodError,
            'employeeId' => $employeeId, 'employees' => $employees, 'payrolls' => $payrolls,
            // Employés en poste qui n'ont pas encore de bulletin sur le mois.
            'missing' => $employees->whereNotIn('id', $payrolls->pluck('employee_id'))->values(),
        ]);
    }

    public function payrollBook(Request $request)
    {
        $period = $request->input('period', 'current_month');
        $today = now()->startOfDay();

        $ranges = [
            'today' => [$today->copy(), $today->copy()],
            'this_week' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
            'last_week' => [$today->copy()->subWeek()->startOfWeek(), $today->copy()->subWeek()->endOfWeek()],
            'current_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'last_month' => [$today->copy()->subMonth()->startOfMonth(), $today->copy()->subMonth()->endOfMonth()],
            'last_3_months' => [$today->copy()->subMonths(2)->startOfMonth(), $today->copy()->endOfMonth()],
            'last_6_months' => [$today->copy()->subMonths(5)->startOfMonth(), $today->copy()->endOfMonth()],
            'last_12_months' => [$today->copy()->subMonths(11)->startOfMonth(), $today->copy()->endOfMonth()],
            'this_year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            'last_year' => [$today->copy()->subYear()->startOfYear(), $today->copy()->subYear()->endOfYear()],
        ];

        $periodError = null;
        if ($period === 'custom') {
            try {
                $from = Carbon::parse($request->input('from', $today->copy()->startOfMonth()->toDateString()))->startOfDay();
                $to = Carbon::parse($request->input('to', $today->toDateString()))->endOfDay();
            } catch (\Throwable) {
                $from = $to = null;
            }
            if (! $from || $from->gt($to)) {
                $periodError = 'La période choisie est invalide : affichage du mois en cours.';
                [$from, $to] = $ranges['current_month'];
            }
        } else {
            [$from, $to] = $ranges[$period] ?? $ranges['current_month'];
        }

        $payrolls = Payroll::where('entreprise_id', auth()->user()->entreprise_id)
            ->with('employee:id,full_name,matricule,department')
            ->orderBy('year')->orderBy('month')->get()
            ->filter(function (Payroll $payroll) use ($from, $to) {
                $payrollStart = Carbon::create($payroll->year, $payroll->month, 1)->startOfDay();

                return $payrollStart->lte($to) && $payrollStart->copy()->endOfMonth()->gte($from);
            })
            ->values();

        return $this->page('rh-payroll-book', [
            'title' => 'Livre de paie',
            'subtitle' => 'Registre des bulletins, salaire par salaire, sur la période choisie.',
            'period' => $period, 'from' => $from, 'to' => $to, 'periodError' => $periodError,
            'payrolls' => $payrolls,
            'byMonth' => $payrolls->groupBy(fn ($payroll) => sprintf('%04d-%02d', $payroll->year, $payroll->month))
                ->map(fn ($items, $key) => [
                    'label' => ucfirst(Carbon::createFromFormat('Y-m-d', $key . '-01')->translatedFormat('F Y')),
                    'month' => (int) $items->first()->month,
                    'year' => (int) $items->first()->year,
                    'count' => $items->count(),
                    'gross' => round((float) $items->sum('gross_salary'), 2),
                    'net' => round((float) $items->sum('net_salary'), 2),
                    'charges' => round((float) $items->sum('employer_charges'), 2),
                ])->sortKeysDesc()->values(),
        ]);
    }

    /**
     * QR de pointage affiché à l'entrée.
     *
     * Le code reste le même d'une visite à l'autre : le régénérer à chaque
     * affichage invalidait le QR imprimé ou laissé sur un écran.
     */
    public function displayQr()
    {
        $companyId = auth()->user()->entreprise_id;
        $token = QrToken::where('entreprise_id', $companyId)->where('is_used', false)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('id')->first() ?? $this->newQrToken($companyId);

        return $this->page('rh-qr-display', [
            'title' => 'Pointage par QR Code',
            'subtitle' => 'Code à présenter aux employés pour enregistrer leur arrivée et leur départ.',
            'token' => $token,
            'scanUrl' => route('attendance.qr.scan', ['token' => $token->token]),
            'todayCount' => StaffAttendanceQr::where('entreprise_id', $companyId)->whereDate('attendance_date', now()->toDateString())->count(),
        ]);
    }

    /** Remplace le code : l'ancien ne permet plus de pointer. */
    public function renewQr()
    {
        $companyId = auth()->user()->entreprise_id;
        QrToken::where('entreprise_id', $companyId)->where('is_used', false)->update(['is_used' => true, 'used_at' => now()]);
        $this->newQrToken($companyId);

        return redirect()->route('admin.rh.qr.display')->with('success', 'Nouveau code généré : l’ancien ne permet plus de pointer.');
    }

    private function newQrToken(int $companyId): QrToken
    {
        return QrToken::create([
            'entreprise_id' => $companyId,
            'token' => Str::random(64),
            'expires_at' => null,
        ]);
    }

    public function todayAttendance()
    {
        $companyId = auth()->user()->entreprise_id;
        $today = now()->toDateString();
        $attendances = StaffAttendanceQr::with('employee:id,full_name,matricule,department,position')
            ->where('entreprise_id', $companyId)
            ->whereDate('attendance_date', $today)
            ->orderByDesc('arrival_time')->get();
        // Absents : employés en poste qui n'ont pas pointé, congés validés mis à part.
        $onLeave = LeaveRequest::where('entreprise_id', $companyId)->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)
            ->pluck('employee_id');
        $expected = Employee::where('entreprise_id', $companyId)->where('status', 'active')
            ->orderBy('full_name')->get(['id', 'full_name', 'matricule', 'department']);
        $missing = $expected->whereNotIn('id', $attendances->pluck('employee_id'))
            ->reject(fn ($employee) => $onLeave->contains($employee->id))->values();

        return $this->page('rh-attendance-today', [
            'title' => 'Présences du jour',
            'subtitle' => 'Pointages QR du ' . now()->format('d/m/Y') . ', arrivées, départs et absents.',
            'attendances' => $attendances,
            'missing' => $missing,
            'onLeave' => $expected->whereIn('id', $onLeave)->values(),
            'expected' => $expected->count(),
        ]);
    }

    public function attendanceReport(Request $request)
    {
        $user = auth()->user();
        $companyId = $user->entreprise_id;
        $settings = $user->entreprise?->settings ?? [];
        $schedule = array_merge([
            'qr_attendance_start_time' => '08:00',
            'qr_attendance_break_start' => '12:00',
            'qr_attendance_break_end' => '13:00',
            'qr_attendance_end_time' => '17:00',
            'qr_attendance_regular_hours' => 173.33,
        ], data_get($settings, 'attendance_schedule', []));

        // Seul le formulaire des horaires envoie un POST : la recherche, elle,
        // passe par l'URL. Avant, chercher déclenchait la validation des
        // horaires et la recherche n'aboutissait jamais.
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'start_time' => ['required', 'date_format:H:i'],
                'break_start' => ['required', 'date_format:H:i', 'after:start_time'],
                'break_end' => ['required', 'date_format:H:i', 'after:break_start'],
                'end_time' => ['required', 'date_format:H:i', 'after:break_end'],
                'regular_hours' => ['required', 'numeric', 'min:0', 'max:744'],
            ], [
                'start_time.required' => 'Indiquez l’heure de début de journée.',
                'break_start.after' => 'La pause commence après le début de la journée.',
                'break_end.after' => 'La reprise suit le début de la pause.',
                'end_time.after' => 'La fin de journée suit la reprise.',
                'regular_hours.*' => 'Indiquez le nombre d’heures mensuelles de référence.',
            ]);
            $schedule = [
                'qr_attendance_start_time' => $validated['start_time'],
                'qr_attendance_break_start' => $validated['break_start'],
                'qr_attendance_break_end' => $validated['break_end'],
                'qr_attendance_end_time' => $validated['end_time'],
                'qr_attendance_regular_hours' => (float) $validated['regular_hours'],
            ];
            $settings['attendance_schedule'] = $schedule;
            $user->entreprise->update(['settings' => $settings]);

            return redirect()->route('admin.rh.attendance.report', $request->only(['from', 'to', 'employee_id']))
                ->with('success', 'Horaires de travail enregistrés : ' . substr($schedule['qr_attendance_start_time'], 0, 5) . ' à ' . substr($schedule['qr_attendance_end_time'], 0, 5) . '.');
        }

        $periodError = null;
        try {
            $from = Carbon::parse($request->input('from', now()->startOfMonth()->toDateString()))->startOfDay();
            $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();
        } catch (\Throwable) {
            $from = $to = null;
        }
        if (! $from || $from->gt($to)) {
            $periodError = 'La période choisie est invalide : la date de début doit précéder la date de fin. Affichage du mois en cours.';
            $from = now()->startOfMonth();
            $to = now()->endOfDay();
        }

        $employeeId = $request->input('employee_id');
        $attendances = StaffAttendanceQr::with('employee:id,full_name,matricule,department,phone')
            ->where('entreprise_id', $companyId)
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
            ->when($employeeId, fn ($query) => $query->where('employee_id', $employeeId))
            ->orderByDesc('attendance_date')->orderByDesc('arrival_time')->get();

        // Heures attendues : la journée de travail du paramétrage, pause déduite,
        // comptée pour chaque jour réellement pointé.
        $dailySeconds = max(0, Carbon::parse($schedule['qr_attendance_end_time'])->diffInSeconds(Carbon::parse($schedule['qr_attendance_start_time']))
            - Carbon::parse($schedule['qr_attendance_break_end'])->diffInSeconds(Carbon::parse($schedule['qr_attendance_break_start'])));
        $lateAfter = Carbon::parse($schedule['qr_attendance_start_time'])->format('H:i:s');

        $summary = ['records' => $attendances->count(), 'arrivals' => $attendances->whereNotNull('arrival_time')->count(),
            'departures' => $attendances->whereNotNull('departure_time')->count(),
            'incomplete' => $attendances->whereNull('departure_time')->count(),
            'late' => 0, 'worked_seconds' => 0, 'expected_seconds' => 0];
        $employeeTotals = [];
        foreach ($attendances as $attendance) {
            $key = $attendance->employee_id;
            $employeeTotals[$key] ??= ['employee' => $attendance->employee, 'days' => 0, 'late' => 0, 'worked_seconds' => 0, 'expected_seconds' => 0];
            $employeeTotals[$key]['days']++;
            $employeeTotals[$key]['expected_seconds'] += $dailySeconds;
            $summary['expected_seconds'] += $dailySeconds;
            if ($attendance->arrival_time && $attendance->arrival_time > $lateAfter) {
                $employeeTotals[$key]['late']++;
                $summary['late']++;
            }
            if ($attendance->arrival_time && $attendance->departure_time) {
                $date = $attendance->attendance_date->format('Y-m-d');
                $arrival = Carbon::parse($date . ' ' . $attendance->arrival_time);
                $departure = Carbon::parse($date . ' ' . $attendance->departure_time);
                $seconds = max(0, $departure->diffInSeconds($arrival));
                $breakStart = Carbon::parse($date . ' ' . $schedule['qr_attendance_break_start']);
                $breakEnd = Carbon::parse($date . ' ' . $schedule['qr_attendance_break_end']);
                // Pause déduite seulement pour la part réellement couverte par la présence.
                $overlap = $arrival->lt($breakEnd) && $departure->gt($breakStart)
                    ? max(0, min($departure->timestamp, $breakEnd->timestamp) - max($arrival->timestamp, $breakStart->timestamp))
                    : 0;
                $seconds = max(0, $seconds - $overlap);
                $employeeTotals[$key]['worked_seconds'] += $seconds;
                $summary['worked_seconds'] += $seconds;
            }
        }
        foreach ($employeeTotals as &$total) {
            $total['gap_seconds'] = $total['worked_seconds'] - $total['expected_seconds'];
        }
        unset($total);

        return $this->page('rh-attendance-report', [
            'title' => 'Rapport de présence',
            'subtitle' => 'Pointages QR de la période, heures travaillées et écarts sur l’horaire de l’entreprise.',
            'from' => $from, 'to' => $to, 'periodError' => $periodError,
            'attendances' => $attendances,
            'employees' => Employee::where('entreprise_id', $companyId)->where('status', 'active')->orderBy('full_name')->get(['id', 'full_name', 'matricule']),
            'employeeId' => $employeeId,
            'schedule' => $schedule,
            'dailyHours' => $dailySeconds / 3600,
            'summary' => $summary,
            'employeeTotals' => collect($employeeTotals)->sortByDesc('worked_seconds')->values(),
        ]);
    }

    public function createPayroll()
    {
        $entrepriseId = auth()->user()->entreprise_id;
        return $this->page('rh-payroll-form', [
            'title' => 'Générer un bulletin',
            'employees' => Employee::where('status', 'active')->orderBy('full_name')->get(),
            'cashAccounts' => CashAccount::where('entreprise_id', $entrepriseId)->where('is_active', true)->orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('entreprise_id', $entrepriseId)->orderBy('name')->get(),
            'month' => (int) now()->subMonth()->format('m'),
            'year' => (int) now()->format('Y'),
        ]);
    }

    public function generatePayroll(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'sursalary' => ['nullable', 'numeric', 'min:0'],
            'seniority_bonus' => ['nullable', 'numeric', 'min:0'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0'],
            'responsibility_bonus' => ['nullable', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'performance_bonus' => ['nullable', 'numeric', 'min:0'],
            'risk_bonus' => ['nullable', 'numeric', 'min:0'],
            'attendance_bonus' => ['nullable', 'numeric', 'min:0'],
            'gratification' => ['nullable', 'numeric', 'min:0'],
            'leave_pay' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'other_deductions' => ['nullable', 'numeric', 'min:0'],
            'part_igr' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'children_count' => ['nullable', 'integer', 'min:0'],
            'overtime_hours' => ['nullable', 'numeric', 'min:0'],
            'payment_mode' => ['required', 'in:cash,bank,transfer'],
            'cash_account_id' => ['required_if:payment_mode,cash', 'nullable', 'integer'],
            'bank_account_id' => ['required_if:payment_mode,bank,transfer', 'nullable', 'integer'],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'indemnities' => ['nullable', 'numeric', 'min:0'],
            'income_tax' => ['nullable', 'numeric', 'min:0'],
            'cmu' => ['nullable', 'numeric', 'min:0'],
        ]);
        $entrepriseId = auth()->user()->entreprise_id;
        // Refus renvoyés comme erreurs de saisie : le formulaire se rouvre avec le motif.
        $fail = fn (string $field, string $message) => throw \Illuminate\Validation\ValidationException::withMessages([$field => $message]);
        $employee = Employee::where('entreprise_id', $entrepriseId)->whereKey($data['employee_id'])->where('status', 'active')->first();
        if (! $employee) {
            $fail('employee_id', 'Choisissez un employé en poste : un contrat clôturé ne donne plus lieu à bulletin.');
        }
        $paymentMode = $data['payment_mode'] ?? $employee->payment_mode ?? 'transfer';
        if (! in_array($paymentMode, ['cash', 'bank', 'transfer'], true)) {
            $fail('payment_mode', 'Choisissez le mode de paiement du salaire.');
        }
        $cashAccount = $paymentMode === 'cash'
            ? CashAccount::where('entreprise_id', $entrepriseId)->where('is_active', true)->find($data['cash_account_id'] ?? 0)
            : null;
        if ($paymentMode === 'cash' && ! $cashAccount) {
            $fail('cash_account_id', 'Choisissez la caisse qui paie le salaire.');
        }
        $bankAccount = in_array($paymentMode, ['bank', 'transfer'], true)
            ? BankAccount::where('entreprise_id', $entrepriseId)->find($data['bank_account_id'] ?? 0)
            : null;
        if ($paymentMode !== 'cash' && ! $bankAccount) {
            $fail('bank_account_id', 'Choisissez le compte bancaire qui paie le salaire.');
        }
        $base = (float) ($data['monthly_salary'] ?? $employee->monthly_salary);
        $value = fn (string $key, string $fallback = ''): float => (float) (
            array_key_exists($fallback ?: $key, $data)
                ? ($data[$fallback ?: $key] ?? 0)
                : ($employee->{$key} ?? 0)
        );
        $sursalary = $value('sursalary');
        $transport = $value('transport_allowance');
        $responsibility = $value('responsibility_bonus');
        $bonus = $value('bonus');
        $performance = $value('performance_bonus');
        $risk = $value('risk_bonus');
        $attendance = $value('attendance_bonus');
        $gratification = $value('gratification');
        $leavePay = $value('leave_pay');
        $indemnities = $value('indemnities');
        $seniority = $value('seniority_bonus') ?: $this->seniorityBonus($base, $employee->hire_date, $data['year'], $data['month']);
        $partIgr = (float) ($data['part_igr'] ?? $employee->part_igr ?? 1);
        $children = (int) ($data['children_count'] ?? $employee->children_count ?? 0);
        $allowances = $sursalary + $transport + $responsibility + $bonus + $performance + $risk + $attendance + $gratification + $leavePay + $indemnities + $seniority;
        $otherDeductions = $value('other_deductions', 'deductions');
        $gross = $base + $allowances;
        $taxableTransport = max($transport - 30000, 0);
        $taxableSeniority = max($seniority - ($gross * 0.10), 0);
        $taxablePerformance = max($performance - ($gross * 0.10), 0);
        $taxableResponsibility = max($responsibility - ($gross * 0.10), 0);
        $taxableRisk = max($risk - ($gross * 0.10), 0);
        $taxableAttendance = max($attendance - ($gross * 0.10), 0);
        $taxableOther = 0;
        $fiscalGross = $base + $sursalary + $taxableTransport + $taxableSeniority + $taxablePerformance + $taxableResponsibility + $taxableRisk + $taxableAttendance + $taxableOther;
        $socialGross = $gross - $transport;
        $cnpsBase = min($socialGross, 3375000);
        $cnps = $cnpsBase * 0.063;
        $tax = array_key_exists('income_tax', $data)
            ? (float) $data['income_tax']
            : ((float) $employee->income_tax ?: max($this->calculateIncomeTax($fiscalGross) - $this->igrReduction($partIgr), 0));
        $cmu = array_key_exists('cmu', $data)
            ? (float) $data['cmu']
            : ((float) $employee->cmu ?: max($children, 1) * 500);
        $cnpsEmployer = $cnpsBase * 0.077;
        $workAccident = $base * 0.04;
        $familyBenefits = $base * 0.0575;
        $fdfpApprenticeship = $fiscalGross * 0.004;
        $fdfpTraining = $fiscalGross * 0.012;
        $employeeDeductions = $cnps + $tax + $cmu + $otherDeductions;
        $employerCharges = ($fiscalGross * 0.012) + $cnpsEmployer + $workAccident + $familyBenefits + $fdfpApprenticeship + $fdfpTraining + $cmu;
        $net = max($gross - $employeeDeductions, 0);

        $payroll = DB::transaction(function () use ($data, $employee, $entrepriseId, $paymentMode, $cashAccount, $bankAccount, $partIgr, $children, $base, $sursalary, $allowances, $transport, $responsibility, $bonus, $performance, $risk, $attendance, $gratification, $leavePay, $indemnities, $seniority, $otherDeductions, $gross, $fiscalGross, $socialGross, $cnps, $tax, $net, $cmu, $cnpsEmployer, $workAccident, $familyBenefits, $fdfpApprenticeship, $fdfpTraining, $employeeDeductions, $employerCharges) {
            $cashAccount = $cashAccount
                ? CashAccount::where('entreprise_id', $entrepriseId)->whereKey($cashAccount->id)->lockForUpdate()->firstOrFail()
                : null;
            $bankAccount = $bankAccount
                ? BankAccount::where('entreprise_id', $entrepriseId)->whereKey($bankAccount->id)->lockForUpdate()->firstOrFail()
                : null;
            $payroll = Payroll::updateOrCreate(
                ['employee_id' => $employee->id, 'month' => $data['month'], 'year' => $data['year']],
                [
                'entreprise_id' => $entrepriseId,
                'part_igr' => $partIgr,
                'children_count' => $children,
                'overtime_hours' => (float) ($data['overtime_hours'] ?? 0),
                'payment_mode' => $paymentMode,
                'base_salary' => $base,
                'sursalary' => $sursalary,
                'allowances' => $allowances,
                'transport_allowance' => $transport,
                'responsibility_bonus' => $responsibility,
                'bonus' => $bonus,
                'performance_bonus' => $performance,
                'risk_bonus' => $risk,
                'attendance_bonus' => $attendance,
                'gratification' => $gratification,
                'leave_pay' => $leavePay,
                'indemnities' => $indemnities,
                'seniority_bonus' => $seniority,
                'deductions' => $otherDeductions,
                'gross_salary' => $gross,
                'fiscal_gross' => $fiscalGross,
                'social_gross' => $socialGross,
                'cnps_employee' => $cnps,
                'income_tax' => $tax,
                'net_salary' => $net,
                'cmu' => $cmu,
                'cnps_employer' => $cnpsEmployer,
                'work_accident' => $workAccident,
                'family_benefits' => $familyBenefits,
                'fdfp_apprenticeship' => $fdfpApprenticeship,
                'fdfp_training' => $fdfpTraining,
                'total_employee_deductions' => $employeeDeductions,
                'total_employer_deductions' => $employerCharges,
                'employer_charges' => $employerCharges,
                ]
            );

            // Sortie d'argent du bulletin. Un bulletin regénéré remplaçait le
            // montant du bulletin sans toucher au mouvement déjà passé : la
            // caisse ou la banque restait sur l'ancien net. L'ancien mouvement
            // est donc repris avant d'enregistrer le nouveau.
            $reference = 'PAY-' . $payroll->id;
            $previousCash = CashMovement::where('entreprise_id', $entrepriseId)->where('reference', $reference)->first();
            if ($previousCash) {
                CashAccount::where('entreprise_id', $entrepriseId)->whereKey($previousCash->cash_account_id)
                    ->increment('balance', (float) $previousCash->amount);
                $previousCash->delete();
            }
            $previousBank = BankTransaction::where('entreprise_id', $entrepriseId)->where('label', $reference)->first();
            if ($previousBank) {
                BankAccount::where('entreprise_id', $entrepriseId)->whereKey($previousBank->bank_account_id)
                    ->increment('current_balance', (float) $previousBank->amount);
                $previousBank->delete();
            }
            if ($paymentMode === 'cash') {
                CashMovement::create([
                    'entreprise_id' => $entrepriseId, 'cash_account_id' => $cashAccount->id,
                    'movement_type' => 'exit', 'label' => 'Paiement salaire - ' . $employee->full_name,
                    'amount' => $net, 'currency' => company_currency()['code'], 'payment_mode' => 'cash',
                    'reference' => $reference, 'description' => 'Bulletin ' . $data['month'] . '/' . $data['year'],
                    'movement_date' => now()->toDateString(),
                ]);
                $cashAccount->refresh()->decrement('balance', $net);
            } else {
                BankTransaction::create([
                    'entreprise_id' => $entrepriseId, 'bank_account_id' => $bankAccount->id,
                    'transaction_type' => 'debit', 'is_transfer' => false, 'label' => $reference,
                    'amount' => $net, 'description' => 'Paiement salaire - ' . $employee->full_name,
                    'transaction_date' => now()->toDateString(),
                ]);
                $bankAccount->refresh()->decrement('current_balance', $net);
            }

            return $payroll;
        });

        return redirect()->route('admin.rh.payroll.show', $payroll)->with('success', 'Bulletin de ' . $employee->full_name . ' pour '
            . mb_strtolower(Carbon::create($data['year'], $data['month'], 1)->translatedFormat('F Y')) . ' généré : net à payer ' . money($net) . '.');
    }

    private function calculateIncomeTax(float $gross): float
    {
        $remaining = $gross;
        $tax = 0;
        foreach ([[75000, 0], [240000, 0.16], [800000, 0.21], [2400000, 0.24], [8000000, 0.28], [INF, 0.32]] as $index => [$limit, $rate]) {
            $previous = $index === 0 ? 0 : [[75000, 0], [240000, 0.16], [800000, 0.21], [2400000, 0.24], [8000000, 0.28]][$index - 1][0];
            $taxable = min($remaining, $limit - $previous);
            if ($taxable > 0) $tax += $taxable * $rate;
            $remaining -= max($taxable, 0);
            if ($remaining <= 0) break;
        }
        return round($tax, 2);
    }

    private function igrReduction(float $parts): float
    {
        return match (true) {
            $parts >= 5 => 44000,
            $parts >= 4.5 => 38500,
            $parts >= 4 => 33000,
            $parts >= 3.5 => 27500,
            $parts >= 3 => 22000,
            $parts >= 2.5 => 16500,
            $parts >= 2 => 11000,
            $parts >= 1.5 => 5500,
            default => 0,
        };
    }

    private function seniorityBonus(float $base, $hireDate, int $year, int $month): float
    {
        if (!$hireDate) return 0;
        $years = Carbon::parse($hireDate)->diffInYears(Carbon::create($year, $month)->endOfMonth());
        $rate = match (true) {
            $years > 15 => 0.20,
            $years >= 11 => 0.15,
            $years >= 6 => 0.10,
            $years >= 3 => 0.05,
            default => 0,
        };
        return $base * $rate;
    }

    public function showPayroll(Payroll $payroll)
    {
        $this->authorizePayrollAccess($payroll);
        $payroll->load('employee.entreprise');

        return $this->page('rh-payroll-show', [
            'title' => 'Bulletin de paie',
            'subtitle' => $payroll->employee?->full_name . ' · ' . ucfirst(Carbon::create($payroll->year, $payroll->month, 1)->translatedFormat('F Y')),
            'payroll' => $payroll,
            'isAdmin' => auth()->user()->hasRole('admin'),
        ]);
    }

    public function payrollPdf(Payroll $payroll)
    {
        $this->authorizePayrollAccess($payroll);
        $payroll->load('employee.entreprise');
        return Pdf::loadView('admin.rh-payroll-print', compact('payroll'))->setPaper('a4', 'portrait')->download(
            'bulletin-' . $payroll->employee->matricule . '-' . $payroll->month . '-' . $payroll->year . '.pdf'
        );
    }

    public function emailPayroll(Payroll $payroll)
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);
        $payroll->load('employee.entreprise');
        abort_unless(filter_var($payroll->employee->email, FILTER_VALIDATE_EMAIL), 422, 'L’employé ne possède pas une adresse email valide.');
        $pdf = Pdf::loadView('admin.rh-payroll-print', compact('payroll'))->setPaper('a4', 'portrait')->output();
        Mail::send('emails.payroll', compact('payroll'), function ($message) use ($payroll, $pdf) {
            $message->to($payroll->employee->email, $payroll->employee->full_name)
                ->subject('Votre bulletin de paie - ' . $payroll->month . '/' . $payroll->year)
                ->attachData($pdf, 'bulletin-' . $payroll->employee->matricule . '.pdf', ['mime' => 'application/pdf']);
        });
        $payroll->update(['sent_at' => now()]);
        return back()->with('success', 'Bulletin envoyé à ' . $payroll->employee->email . '.');
    }

    public function emailPayrollBulk(Request $request)
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);
        $data = $request->validate([
            'payroll_ids' => ['required', 'array', 'min:1'],
            'payroll_ids.*' => ['integer', 'distinct', 'exists:payrolls,id'],
        ]);
        $payrolls = Payroll::with('employee')
            ->whereIn('id', $data['payroll_ids'])
            ->get();
        abort_unless($payrolls->count() === count($data['payroll_ids']), 403);

        $invalid = $payrolls->filter(fn ($payroll) => !filter_var($payroll->employee?->email, FILTER_VALIDATE_EMAIL));
        if ($invalid->isNotEmpty()) {
            return back()->withErrors([
                'payroll_ids' => 'Adresse email invalide pour : ' . $invalid->pluck('employee.full_name')->join(', ') . '.',
            ]);
        }

        foreach ($payrolls as $payroll) {
            $payroll->load('employee.entreprise');
            $pdf = Pdf::loadView('admin.rh-payroll-print', compact('payroll'))->setPaper('a4', 'portrait')->output();
            Mail::send('emails.payroll', compact('payroll'), function ($message) use ($payroll, $pdf) {
                $message->to($payroll->employee->email, $payroll->employee->full_name)
                    ->subject('Votre bulletin de paie - ' . $payroll->month . '/' . $payroll->year)
                    ->attachData($pdf, 'bulletin-' . $payroll->employee->matricule . '.pdf', ['mime' => 'application/pdf']);
            });
            $payroll->update(['sent_at' => now()]);
        }

        return back()->with('success', $payrolls->count() . ' bulletin(s) envoyé(s) par email.');
    }

    private function authorizePayrollAccess(Payroll $payroll): void
    {
        $user = auth()->user();
        if (!$user->hasRole('admin')) {
            abort_unless($payroll->employee?->user_id === $user->id, 403);
        }
    }

    public function salaryCategories()
    {
        $companyId = auth()->user()->entreprise_id;
        $categories = SalaryCategory::where('entreprise_id', $companyId)->orderBy('name')->get();
        // Employés rattachés à chaque catégorie : la suppression en dépend.
        $employees = Employee::where('entreprise_id', $companyId)->whereNotNull('salary_category')
            ->get(['id', 'full_name', 'salary_category', 'monthly_salary', 'status']);

        return $this->page('rh-salary-categories', [
            'title' => 'Catégories salariales',
            'subtitle' => 'Niveaux de rémunération de référence et employés rattachés.',
            'module' => $this->modules()['categories-salariales'],
            'categories' => $categories,
            'usage' => $employees->groupBy(fn ($employee) => mb_strtolower(trim((string) $employee->salary_category)))
                ->map(fn ($items) => ['count' => $items->count(), 'salary' => (float) $items->avg('monthly_salary')]),
        ]);
    }

    private function salaryCategoryRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** Deux catégories de même nom rendraient le rattachement des employés ambigu. */
    private function ensureCategoryNameIsFree(string $name, ?int $ignoreId = null): void
    {
        $taken = SalaryCategory::where('entreprise_id', auth()->user()->entreprise_id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn ($query) => $query->where('id', '<>', $ignoreId))
            ->exists();
        if ($taken) {
            throw \Illuminate\Validation\ValidationException::withMessages(['name' => 'Une catégorie salariale porte déjà ce nom.']);
        }
    }

    public function storeSalaryCategory(Request $request)
    {
        $data = $request->validate($this->salaryCategoryRules(), [
            'name.required' => 'Donnez un nom à la catégorie (ex. 1A).',
            'amount.*' => 'Le montant de référence doit être un nombre positif.',
        ]);
        $this->ensureCategoryNameIsFree($data['name']);
        SalaryCategory::create($data + ['entreprise_id' => auth()->user()->entreprise_id, 'is_active' => true]);

        return back()->with('success', 'Catégorie « ' . $data['name'] . ' » ajoutée.');
    }

    public function editSalaryCategory(SalaryCategory $salaryCategory)
    {
        abort_unless($salaryCategory->entreprise_id === auth()->user()->entreprise_id, 403);

        // La modification se fait dans une fenêtre sur la liste : on y renvoie.
        return redirect()->route('admin.rh.salaryCategories', ['categorie' => $salaryCategory->id]);
    }

    public function updateSalaryCategory(Request $request, SalaryCategory $salaryCategory)
    {
        abort_unless($salaryCategory->entreprise_id === auth()->user()->entreprise_id, 403);
        $data = $request->validate($this->salaryCategoryRules(), [
            'name.required' => 'Donnez un nom à la catégorie (ex. 1A).',
            'amount.*' => 'Le montant de référence doit être un nombre positif.',
        ]);
        $this->ensureCategoryNameIsFree($data['name'], $salaryCategory->id);
        $previousName = $salaryCategory->name;
        $salaryCategory->update($data + ['is_active' => $request->boolean('is_active')]);
        // Les fiches employés portent le nom de la catégorie : il les suit.
        if ($previousName !== $data['name']) {
            Employee::where('entreprise_id', $salaryCategory->entreprise_id)
                ->where('salary_category', $previousName)->update(['salary_category' => $data['name']]);
        }

        return back()->with('success', 'Catégorie « ' . $data['name'] . ' » mise à jour.');
    }

    public function destroySalaryCategory(SalaryCategory $salaryCategory)
    {
        abort_unless($salaryCategory->entreprise_id === auth()->user()->entreprise_id, 403);
        // Supprimer retirait en silence la catégorie de toutes les fiches employés.
        $used = Employee::where('entreprise_id', $salaryCategory->entreprise_id)
            ->where('salary_category', $salaryCategory->name)->count();
        if ($used > 0) {
            return back()->withErrors(['category' => $used . ' employé(s) sont rattachés à la catégorie « ' . $salaryCategory->name
                . ' » : désactivez-la, ou changez d’abord leur catégorie.']);
        }
        $salaryCategory->delete();

        return back()->with('success', 'Catégorie « ' . $salaryCategory->name . ' » supprimée.');
    }

    public function permissionRequests()
    {
        $user = auth()->user();
        $companyId = $user->entreprise_id;
        $isAdmin = $user->hasRole('admin');
        $employee = Employee::where('user_id', $user->id)->first();

        return $this->page('rh-permission-requests', [
            'title' => 'Demandes de permission',
            'subtitle' => $isAdmin ? 'Absences courtes demandées par les employés : validation et suivi.' : 'Vos demandes de permission et leur suivi.',
            'requests' => PermissionRequest::where('entreprise_id', $companyId)
                ->with(['employee:id,full_name,department', 'reviewer:id,name'])
                ->when(! $isAdmin, fn ($query) => $query->where('employee_id', $employee?->id))
                ->orderByDesc('start_date')->orderByDesc('id')->get(),
            'employees' => $isAdmin
                ? Employee::where('entreprise_id', $companyId)->where('status', 'active')->orderBy('full_name')->get(['id', 'full_name', 'department'])
                : collect(),
            'types' => PermissionRequest::types(),
            'isAdmin' => $isAdmin,
            'employee' => $employee,
        ]);
    }

    public function storePermissionRequest(Request $request)
    {
        $user = auth()->user();
        $companyId = $user->entreprise_id;
        $isAdmin = $user->hasRole('admin');
        $rules = [
            'type' => ['required', 'string', \Illuminate\Validation\Rule::in(array_keys(PermissionRequest::types()))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
        if ($isAdmin) {
            $rules['employee_id'] = ['required', 'integer'];
        }
        $data = $request->validate($rules, [
            'employee_id.required' => 'Choisissez l’employé concerné.',
            'type.*' => 'Choisissez le motif de la permission.',
            'start_date.required' => 'Indiquez le premier jour d’absence.',
            'end_date.after_or_equal' => 'Le dernier jour doit suivre le premier.',
            'end_date.required' => 'Indiquez le dernier jour d’absence.',
            'reason.required' => 'Expliquez la raison de la permission.',
        ]);

        $employee = $isAdmin
            ? Employee::where('entreprise_id', $companyId)->where('status', 'active')->find($data['employee_id'])
            : Employee::where('user_id', $user->id)->first();
        $fail = fn (string $field, string $message) => throw \Illuminate\Validation\ValidationException::withMessages([$field => $message]);
        if (! $employee) {
            $fail($isAdmin ? 'employee_id' : 'type', $isAdmin
                ? 'Choisissez un employé actif de l’entreprise.'
                : 'Votre compte n’est relié à aucune fiche employé : demandez à l’administration d’enregistrer votre permission.');
        }

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        $overlap = PermissionRequest::where('entreprise_id', $companyId)
            ->where('employee_id', $employee->id)->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)->first();
        if ($overlap) {
            $fail('start_date', $employee->full_name . ' a déjà une permission sur cette période (du '
                . $overlap->start_date->format('d/m/Y') . ' au ' . $overlap->end_date->format('d/m/Y') . ').');
        }

        PermissionRequest::create([
            'entreprise_id' => $companyId,
            'employee_id' => $employee->id,
            'type' => $data['type'],
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'reason' => $data['reason'],
            'status' => $isAdmin ? 'approved' : 'pending',
            'reviewed_by' => $isAdmin ? $user->id : null,
            'review_comment' => $isAdmin ? 'Permission enregistrée par l’administration.' : null,
        ]);

        return back()->with('success', $isAdmin
            ? 'Permission de ' . $employee->full_name . ' enregistrée et validée : du ' . $start->format('d/m/Y') . ' au ' . $end->format('d/m/Y') . '.'
            : 'Demande de permission envoyée pour le ' . $start->format('d/m/Y') . '.');
    }

    public function reviewPermissionRequest(Request $request, PermissionRequest $permissionRequest)
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);
        abort_unless($permissionRequest->entreprise_id === auth()->user()->entreprise_id, 403);
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'review_comment' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($permissionRequest->status !== 'pending') {
            return back()->withErrors(['request' => 'Cette demande a déjà été traitée ('
                . ($permissionRequest->status === 'approved' ? 'acceptée' : 'refusée') . ').']);
        }

        $permissionRequest->update($data + ['reviewed_by' => auth()->id()]);

        return back()->with('success', 'Permission de ' . ($permissionRequest->employee?->full_name ?: 'l’employé') . ' '
            . ($data['status'] === 'approved' ? 'acceptée' : 'refusée') . '.');
    }
}
