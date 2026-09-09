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
            'tableau-rh' => ['title' => 'Tableau RH', 'description' => 'Vue d’ensemble du personnel, des congés et de la paie.', 'icon' => '📊', 'url' => route('admin.rh')],
            'personnel' => ['title' => 'Liste du personnel', 'description' => 'Fiches et informations des employés.', 'icon' => '👥'],
            'qr-code' => ['title' => 'Afficher QR Code', 'description' => 'QR pour pointage à l’entrée.', 'icon' => '▣'],
            'presences' => ['title' => 'Présences du jour', 'description' => 'Suivi des présences et absences du jour.', 'icon' => '✅'],
            'rapport-presence' => ['title' => 'Rapport Présence', 'description' => 'Rapports et statistiques QR.', 'icon' => '📈'],
            'bulletins-paie' => ['title' => 'Bulletin de paie', 'description' => 'Gestion des paies et bulletins.', 'icon' => '🧾'],
            'livre-paie' => ['title' => 'Livre de paie', 'description' => 'Registre des paies de l’entreprise.', 'icon' => '📚'],
            'categories-salariales' => ['title' => 'Catégorie salariale', 'description' => 'Gestion des catégories salariales.', 'icon' => '🏷️'],
            'parametrage-conges' => ['title' => 'Paramétrage de congé', 'description' => 'Configurez les règles de congés.', 'icon' => '⚙️'],
            'conges' => ['title' => 'Liste des congés', 'description' => 'Validation des demandes de congés.', 'icon' => '🗓️'],
            'calendrier-conges' => ['title' => 'Calendrier des congés', 'description' => 'Visualisation globale des congés.', 'icon' => '📅'],
            'permissions' => ['title' => 'Demande de permission', 'description' => 'Gérez les demandes de permission.', 'icon' => '📝'],
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

        return $this->page('rh-module', [
            'title' => $definition['title'],
            'module' => $definition,
            'modules' => $this->modules(),
            'employees' => Employee::latest('hire_date')->get(),
            'salaryCategories' => SalaryCategory::orderBy('name')->get(),
        ]);
    }

    public function tableau(Request $request)
    {
        $companyId = auth()->user()->entreprise_id;
        $from = Carbon::parse($request->input('date_debut', now()->startOfYear()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('date_fin', now()->toDateString()))->endOfDay();
        abort_unless($from->lte($to), 422, 'La période sélectionnée est invalide.');
        $inPeriod = fn ($date) => $date && Carbon::parse($date)->between($from, $to);
        $employees = Employee::where('entreprise_id', $companyId)->get()->filter(fn ($item) => $inPeriod($item->created_at))->values();
        $totalEmployees = $employees->count();
        $activeEmployees = $employees->where('status', 'active')->count();
        $onLeave = $employees->where('status', 'on_leave')->count();
        $inactiveEmployees = max($totalEmployees - $activeEmployees - $onLeave, 0);
        $leaveRequests = LeaveRequest::where('entreprise_id', $companyId)->get()->filter(fn ($item) => $inPeriod($item->start_date))->values();
        $pendingLeaveRequests = $leaveRequests->where('status', 'pending')->count();
        $approvedLeaveRequests = $leaveRequests->where('status', 'approved')->count();
        $totalLeaveRequests = $leaveRequests->count();
        $totalLeaveTypes = LeaveType::where('entreprise_id', $companyId)->count();
        $permissions = PermissionRequest::where('entreprise_id', $companyId)->get()->filter(fn ($item) => $inPeriod($item->start_date))->values();
        $totalPermissions = $permissions->count();
        $pendingPermissions = $permissions->where('status', 'pending')->count();
        $payrolls = Payroll::where('entreprise_id', $companyId)->get()->filter(fn ($item) => Carbon::create($item->year, $item->month, 1)->between($from->copy()->startOfMonth(), $to->copy()->endOfMonth()))->values();
        $totalPayrolls = $payrolls->count();
        $totalSalaryCategories = SalaryCategory::where('entreprise_id', $companyId)->count();
        $totalQrTokens = QrToken::where('entreprise_id', $companyId)->count();
        $attendance = StaffAttendanceQr::where('entreprise_id', $companyId)->get()->filter(fn ($item) => $inPeriod($item->attendance_date))->values();
        $totalAttendanceRecords = $attendance->count();
        $departmentsCount = Employee::where('entreprise_id', $companyId)
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct('department')
            ->count('department');
        $departmentStats = $employees
            ->groupBy(function ($employee) {
                return trim((string) $employee->department) ?: 'Non affecté';
            })
            ->map->count()
            ->sortDesc();
        $leaveStats = $leaveRequests
            ->groupBy(fn ($leave) => ucfirst((string) ($leave->status ?: 'inconnu')))
            ->map->count();
        $genderStats = $employees
            ->groupBy(function ($employee) {
                $gender = strtolower(trim((string) $employee->gender));
                return in_array($gender, ['f', 'female', 'féminin', 'femme'], true) ? 'Femmes'
                    : (in_array($gender, ['m', 'male', 'masculin', 'homme'], true) ? 'Hommes' : 'Non renseigné');
            })
            ->map->count();
        $monthlyPayrollTotal = $payrolls->sum('net_salary');
        $recentPayrolls = $payrolls->sortByDesc(fn ($item) => sprintf('%04d%02d', $item->year, $item->month))->take(8);
        $detailLists = [
            'employees' => $employees->sortBy('full_name'),
            'active' => $employees->where('status', 'active')->sortBy('full_name'),
            'on_leave' => $employees->where('status', 'on_leave')->sortBy('full_name'),
            'pending_leaves' => $leaveRequests->where('status', 'pending')->load('employee'),
            'payrolls' => $payrolls->load('employee'),
            'leaves' => $leaveRequests->load(['employee', 'leaveType']),
            'leave_types' => LeaveType::where('entreprise_id', $companyId)->orderBy('name')->get(),
            'permissions' => $permissions->load('employee'),
            'salary_categories' => SalaryCategory::where('entreprise_id', $companyId)->orderBy('name')->get(),
            'attendance' => $attendance->load('employee'),
            'qr_tokens' => QrToken::where('entreprise_id', $companyId)->get()->filter(fn ($item) => $inPeriod($item->created_at))->load('employee'),
            'departments' => $employees->whereNotNull('department')->where('department', '!=', '')->sortBy(['department', 'full_name']),
        ];

        return $this->page('rh-tableau', [
            'title' => 'Tableau RH',
            'totalEmployees' => $totalEmployees,
            'activeEmployees' => $activeEmployees,
            'onLeave' => $onLeave,
            'inactiveEmployees' => $inactiveEmployees,
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
            'detailLists' => $detailLists,
            'monthlyPayrollTotal' => $monthlyPayrollTotal,
            'recentPayrolls' => $recentPayrolls,
            'periodFrom' => $from->toDateString(),
            'periodTo' => $to->toDateString(),
        ]);
    }

    public function leaveTypes() {
        return $this->page('rh-leave-types', ['title'=>'Paramétrage des congés', 'types'=>LeaveType::orderBy('name')->get()]);
    }
    public function storeLeaveType(Request $request) {
        $data = $request->validate(['name'=>['required','string','max:100'],'days'=>['required','integer','min:1','max:365'],'description'=>['nullable','string']]);
        LeaveType::create($data + ['entreprise_id'=>auth()->user()->entreprise_id,'is_active'=>true]);
        return back()->with('success','Type de congé créé et appliqué à tous les employés.');
    }
    public function updateLeaveType(Request $request, LeaveType $leaveType) {
        abort_unless($leaveType->entreprise_id === auth()->user()->entreprise_id,403);
        $data = $request->validate(['name'=>['required','string','max:100'],'days'=>['required','integer','min:1','max:365'],'description'=>['nullable','string']]);
        $leaveType->update($data + ['is_active'=>$request->boolean('is_active')]);
        return back()->with('success','Paramétrage du congé mis à jour.');
    }
    public function destroyLeaveType(LeaveType $leaveType) {
        abort_unless($leaveType->entreprise_id === auth()->user()->entreprise_id,403);
        if ($leaveType->requests()->exists()) return back()->withErrors(['type'=>'Ce congé est utilisé par des demandes. Désactivez-le plutôt.']);
        $leaveType->delete(); return back()->with('success','Type de congé supprimé.');
    }
    public function leaveRequests() {
        $user=auth()->user(); $employee=Employee::where('user_id',$user->id)->first();
        $query=LeaveRequest::with(['employee','leaveType'])->latest();
        if (!$user->hasRole('admin')) $query->where('employee_id',$employee?->id);
        return $this->page('rh-leave-requests',['title'=>'Liste des congés','requests'=>$query->get(),'types'=>LeaveType::where('is_active',true)->get(),'isAdmin'=>$user->hasRole('admin')]);
    }
    public function storeLeaveRequest(Request $request) {
        $employee=Employee::where('user_id',auth()->id())->firstOrFail();
        $data=$request->validate(['leave_type_id'=>['required','exists:leave_types,id'],'start_date'=>['required','date'],'end_date'=>['required','date','after_or_equal:start_date'],'reason'=>['nullable','string','max:2000']]);
        $type=LeaveType::where('id',$data['leave_type_id'])->where('entreprise_id',auth()->user()->entreprise_id)->where('is_active',true)->firstOrFail();
        $days=Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date']))+1;
        if($days>$type->days) return back()->withErrors(['end_date'=>"Ce congé est limité à {$type->days} jour(s)."])->withInput();
        LeaveRequest::create($data+['entreprise_id'=>auth()->user()->entreprise_id,'employee_id'=>$employee->id,'days'=>$days,'status'=>'pending']);
        return back()->with('success','Demande de congé envoyée.');
    }
    public function reviewLeaveRequest(Request $request, LeaveRequest $leaveRequest) {
        abort_unless(auth()->user()->hasRole('admin'),403); abort_unless($leaveRequest->entreprise_id===auth()->user()->entreprise_id,403);
        $data=$request->validate(['status'=>['required','in:approved,rejected'],'review_comment'=>['nullable','string','max:2000']]);
        $leaveRequest->update($data+['reviewed_by'=>auth()->id()]); return back()->with('success','Demande de congé traitée.');
    }
    public function leaveCalendar() {
        $leaves=LeaveRequest::with('employee')->where('status','approved')->orderBy('start_date')->get();
        return $this->page('rh-leave-calendar',['title'=>'Calendrier des congés','leaves'=>$leaves]);
    }

    public function payroll(Request $request)
    {
        $month = (int) $request->integer('month', (int) now()->subMonth()->format('m'));
        $year = (int) $request->integer('year', (int) now()->format('Y'));
        abort_unless($month >= 1 && $month <= 12 && $year >= 2000 && $year <= 2100, 422);
        $employeeId = $request->input('employee_id');
        $employees = Employee::where('status', 'active')->orderBy('full_name')->get();
        $payrolls = Payroll::with('employee')
            ->where('month', $month)->where('year', $year)
            ->when($employeeId, fn ($query) => $query->where('employee_id', $employeeId))
            ->latest()->get();

        return $this->page('rh-payroll', compact('month', 'year', 'employeeId', 'employees', 'payrolls'));
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

        if ($period === 'custom') {
            $from = Carbon::parse($request->input('from', $today->copy()->startOfMonth()->toDateString()))->startOfDay();
            $to = Carbon::parse($request->input('to', $today->toDateString()))->endOfDay();
            abort_unless($from->lte($to), 422, 'La période sélectionnée est invalide.');
        } else {
            [$from, $to] = $ranges[$period] ?? $ranges['current_month'];
        }

        $payrolls = Payroll::with('employee')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->filter(function (Payroll $payroll) use ($from, $to) {
                $payrollStart = Carbon::create($payroll->year, $payroll->month, 1)->startOfDay();
                $payrollEnd = $payrollStart->copy()->endOfMonth();
                return $payrollStart->lte($to) && $payrollEnd->gte($from);
            })
            ->values();

        return $this->page('rh-payroll-book', compact('period', 'from', 'to', 'payrolls'));
    }

    public function displayQr()
    {
        QrToken::where('entreprise_id', auth()->user()->entreprise_id)
            ->where('is_used', false)
            ->update(['is_used' => true, 'used_at' => now()]);

        $token = QrToken::create([
            'entreprise_id' => auth()->user()->entreprise_id,
            'token' => Str::random(64),
            'expires_at' => null,
        ]);

        return $this->page('rh-qr-display', [
            'title' => 'Pointage par QR Code',
            'token' => $token,
            'scanUrl' => route('attendance.qr.scan', ['token' => $token->token]),
        ]);
    }

    public function todayAttendance()
    {
        $today = now()->toDateString();
        $query = StaffAttendanceQr::with('employee')
            ->where('entreprise_id', auth()->user()->entreprise_id)
            ->whereDate('attendance_date', $today);
        $attendances = $query->orderByDesc('arrival_time')->get();
        $stats = [
            'total' => $attendances->count(),
            'arrivals' => $attendances->count(),
            'departures' => $attendances->whereNotNull('departure_time')->count(),
            'incomplete' => $attendances->whereNull('departure_time')->count(),
        ];

        return $this->page('rh-attendance-today', compact('attendances', 'stats'));
    }

    public function attendanceReport(Request $request)
    {
        $user = auth()->user();
        $settings = $user->entreprise?->settings ?? [];
        $schedule = array_merge([
            'qr_attendance_start_time' => '08:00',
            'qr_attendance_break_start' => '12:00',
            'qr_attendance_break_end' => '13:00',
            'qr_attendance_end_time' => '17:00',
            'qr_attendance_regular_hours' => 173.33,
        ], data_get($settings, 'attendance_schedule', []));
        $from = Carbon::parse($request->input('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();
        abort_unless($from->lte($to), 422, 'La période sélectionnée est invalide.');

        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'start_time' => ['required', 'date_format:H:i'],
                'break_start' => ['required', 'date_format:H:i'],
                'break_end' => ['required', 'date_format:H:i', 'after:break_start'],
                'end_time' => ['required', 'date_format:H:i', 'after:break_end'],
                'regular_hours' => ['required', 'numeric', 'min:0', 'max:744'],
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
        }

        $employeeId = $request->input('employee_id');
        $attendances = StaffAttendanceQr::with('employee')
            ->where('entreprise_id', auth()->user()->entreprise_id)
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
            ->when($employeeId, fn ($query) => $query->where('employee_id', $employeeId))
            ->orderByDesc('attendance_date')
            ->orderByDesc('arrival_time')
            ->get();

        $employees = Employee::where('status', 'active')->orderBy('full_name')->get();
        $summary = [
            'records' => $attendances->count(),
            'arrivals' => $attendances->whereNotNull('arrival_time')->count(),
            'departures' => $attendances->whereNotNull('departure_time')->count(),
            'incomplete' => $attendances->whereNull('departure_time')->count(),
            'verified' => $attendances->whereIn('verification_status', ['verified', 'reference_created'])->count(),
            'total_seconds' => 0,
        ];
        $employeeTotals = [];
        foreach ($attendances as $attendance) {
            $key = $attendance->employee_id;
            $employeeTotals[$key] ??= ['employee' => $attendance->employee, 'count' => 0, 'actual_seconds' => 0, 'regular_seconds' => ((float) $schedule['qr_attendance_regular_hours']) * 3600];
            $employeeTotals[$key]['count']++;
            if ($attendance->arrival_time && $attendance->departure_time) {
                $arrival = Carbon::parse($attendance->attendance_date->format('Y-m-d') . ' ' . $attendance->arrival_time);
                $departure = Carbon::parse($attendance->attendance_date->format('Y-m-d') . ' ' . $attendance->departure_time);
                $seconds = max(0, $departure->diffInSeconds($arrival));
                $breakStart = Carbon::parse($attendance->attendance_date->format('Y-m-d') . ' ' . $schedule['qr_attendance_break_start']);
                $breakEnd = Carbon::parse($attendance->attendance_date->format('Y-m-d') . ' ' . $schedule['qr_attendance_break_end']);
                $overlap = $arrival->lt($breakEnd) && $departure->gt($breakStart)
                    ? max(0, min($departure->timestamp, $breakEnd->timestamp) - max($arrival->timestamp, $breakStart->timestamp))
                    : 0;
                $seconds = max(0, $seconds - $overlap);
                $employeeTotals[$key]['actual_seconds'] += $seconds;
                $summary['total_seconds'] += $seconds;
            }
        }
        foreach ($employeeTotals as &$total) {
            $total['gap_seconds'] = $total['actual_seconds'] - $total['regular_seconds'];
        }
        unset($total);

        return $this->page('rh-attendance-report', compact('from', 'to', 'attendances', 'employees', 'employeeId', 'schedule', 'summary', 'employeeTotals'));
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
        $employee = Employee::where('entreprise_id', $entrepriseId)->whereKey($data['employee_id'])->where('status', 'active')->firstOrFail();
        $paymentMode = $data['payment_mode'] ?? $employee->payment_mode ?? 'transfer';
        abort_unless(in_array($paymentMode, ['cash', 'bank', 'transfer'], true), 422, 'Mode de paiement invalide.');
        $cashAccount = $paymentMode === 'cash'
            ? CashAccount::where('entreprise_id', $entrepriseId)->where('is_active', true)->findOrFail($data['cash_account_id'] ?? 0)
            : null;
        $bankAccount = in_array($paymentMode, ['bank', 'transfer'], true)
            ? BankAccount::where('entreprise_id', $entrepriseId)->findOrFail($data['bank_account_id'] ?? 0)
            : null;
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

            $reference = 'PAY-' . $payroll->id;
            if ($paymentMode === 'cash') {
                if (!CashMovement::where('entreprise_id', $entrepriseId)->where('reference', $reference)->exists()) {
                    CashMovement::create([
                        'entreprise_id' => $entrepriseId, 'cash_account_id' => $cashAccount->id,
                        'movement_type' => 'exit', 'label' => 'Paiement salaire - ' . $employee->full_name,
                        'amount' => $net, 'currency' => 'XOF', 'payment_mode' => 'cash',
                        'reference' => $reference, 'description' => 'Bulletin ' . $data['month'] . '/' . $data['year'],
                        'movement_date' => now()->toDateString(),
                    ]);
                    $cashAccount->decrement('balance', $net);
                }
            } elseif (!BankTransaction::where('entreprise_id', $entrepriseId)->where('label', $reference)->exists()) {
                BankTransaction::create([
                    'entreprise_id' => $entrepriseId, 'bank_account_id' => $bankAccount->id,
                    'transaction_type' => 'debit', 'is_transfer' => false, 'label' => $reference,
                    'amount' => $net, 'description' => 'Paiement salaire - ' . $employee->full_name,
                    'transaction_date' => now()->toDateString(),
                ]);
                $bankAccount->decrement('current_balance', $net);
            }

            return $payroll;
        });

        return redirect()->route('admin.rh.payroll.show', $payroll)->with('success', 'Bulletin généré avec succès.');
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
        return view('admin.rh-payroll-show', compact('payroll'));
    }

    public function payrollPdf(Payroll $payroll)
    {
        $this->authorizePayrollAccess($payroll);
        $payroll->load('employee.entreprise');
        return Pdf::loadView('admin.rh-payroll-show', compact('payroll'))->setPaper('a4', 'portrait')->download(
            'bulletin-' . $payroll->employee->matricule . '-' . $payroll->month . '-' . $payroll->year . '.pdf'
        );
    }

    public function emailPayroll(Payroll $payroll)
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);
        $payroll->load('employee.entreprise');
        abort_unless(filter_var($payroll->employee->email, FILTER_VALIDATE_EMAIL), 422, 'L’employé ne possède pas une adresse email valide.');
        $pdf = Pdf::loadView('admin.rh-payroll-show', compact('payroll'))->setPaper('a4', 'portrait')->output();
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
            $pdf = Pdf::loadView('admin.rh-payroll-show', compact('payroll'))->setPaper('a4', 'portrait')->output();
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
        return $this->page('rh-salary-categories', [
            'title' => 'Catégories salariales',
            'module' => $this->modules()['categories-salariales'],
            'categories' => SalaryCategory::orderBy('name')->get(),
        ]);
    }

    public function storeSalaryCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);
        SalaryCategory::create($data + ['entreprise_id' => auth()->user()->entreprise_id, 'is_active' => true]);
        return back()->with('success', 'Catégorie salariale ajoutée.');
    }

    public function editSalaryCategory(SalaryCategory $salaryCategory)
    {
        abort_unless($salaryCategory->entreprise_id === auth()->user()->entreprise_id, 403);

        return $this->page('rh-salary-categories', [
            'title' => 'Modifier une catégorie salariale',
            'module' => $this->modules()['categories-salariales'],
            'categories' => SalaryCategory::orderBy('name')->get(),
            'editingCategory' => $salaryCategory,
        ]);
    }

    public function updateSalaryCategory(Request $request, SalaryCategory $salaryCategory)
    {
        abort_unless($salaryCategory->entreprise_id === auth()->user()->entreprise_id, 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);
        $salaryCategory->update($data + ['is_active' => $request->boolean('is_active')]);
        return back()->with('success', 'Catégorie salariale mise à jour.');
    }

    public function destroySalaryCategory(SalaryCategory $salaryCategory)
    {
        abort_unless($salaryCategory->entreprise_id === auth()->user()->entreprise_id, 403);
        Employee::where('salary_category', $salaryCategory->name)->update(['salary_category' => null]);
        $salaryCategory->delete();
        return back()->with('success', 'Catégorie salariale supprimée.');
    }

    public function permissionRequests()
    {
        $user = auth()->user();
        $employee = Employee::where('user_id', $user->id)->first();
        $requests = $user->hasRole('admin')
            ? PermissionRequest::with('employee')->latest()->get()
            : PermissionRequest::where('employee_id', $employee?->id)->latest()->get();

        return $this->page('rh-permission-requests', [
            'title' => 'Demandes de permission',
            'requests' => $requests,
            'isAdmin' => $user->hasRole('admin'),
        ]);
    }

    public function storePermissionRequest(Request $request)
    {
        $user = auth()->user();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();
        $data = $request->validate([
            'type' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        PermissionRequest::create($data + [
            'entreprise_id' => $user->entreprise_id,
            'employee_id' => $employee->id,
            'status' => 'pending',
        ]);
        return back()->with('success', 'Demande de permission envoyée.');
    }

    public function reviewPermissionRequest(Request $request, PermissionRequest $permissionRequest)
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);
        abort_unless($permissionRequest->entreprise_id === auth()->user()->entreprise_id, 403);
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'review_comment' => ['nullable', 'string', 'max:2000'],
        ]);
        $permissionRequest->update($data + ['reviewed_by' => auth()->id()]);
        return back()->with('success', 'Demande traitée.');
    }
}
