<?php

namespace App\Http\Controllers\Admin;

use App\Models\BankTransaction;
use App\Models\CashMovement;
use App\Models\CommercialInvoice;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\SupplierInvoice;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends AdminController
{
    public function index(Request $request)
    {
        $from = Carbon::parse($request->input('date_debut', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('date_fin', now()->endOfMonth()->toDateString()))->endOfDay();
        abort_unless($from->lte($to), 422, 'La période sélectionnée est invalide.');

        $tenant = auth()->user()->entreprise_id;

        $cashEntries = CashMovement::where('entreprise_id', $tenant)->where('movement_type', 'entry')->whereBetween('movement_date', [$from->toDateString(), $to->toDateString()])->get();
        $cashExits = CashMovement::with('expenseCategory')->where('entreprise_id', $tenant)->where('movement_type', 'exit')->whereBetween('movement_date', [$from->toDateString(), $to->toDateString()])->get();
        $bankCredits = BankTransaction::where('entreprise_id', $tenant)->where('transaction_type', 'credit')->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])->get();
        $bankDebits = BankTransaction::where('entreprise_id', $tenant)->where('transaction_type', 'debit')->where('is_transfer', false)->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])->get();
        $invoices = CommercialInvoice::where('entreprise_id', $tenant)->whereBetween('created_at', [$from, $to])->get();
        $suppliers = SupplierInvoice::where('entreprise_id', $tenant)->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()])->get();
        $employees = Employee::where('entreprise_id', $tenant)->where('status', 'active')->get();
        $payrolls = Payroll::where('entreprise_id', $tenant)->whereBetween('year', [$from->year, $to->year])->get();

        $totalRevenue = (float) $cashEntries->sum('amount') + (float) $bankCredits->sum('amount');
        $cashExitTotal = (float) $cashExits->sum('amount');
        $currentBalance = (float) \App\Models\CashAccount::where('entreprise_id', $tenant)->sum('balance') + (float) \App\Models\BankAccount::where('entreprise_id', $tenant)->sum('current_balance');

        $monthLabels = [];
        $revenueSeries = [];
        $expenseSeries = [];
        $cursor = $from->copy()->startOfMonth();
        while ($cursor->lte($to)) {
            $key = $cursor->format('Y-m');
            $monthLabels[] = $cursor->translatedFormat('M Y');
            $revenueSeries[] = (float) $cashEntries->filter(fn ($item) => $item->movement_date?->format('Y-m') === $key)->sum('amount') + (float) $bankCredits->filter(fn ($item) => $item->transaction_date?->format('Y-m') === $key)->sum('amount');
            $expenseSeries[] = (float) $cashExits->filter(fn ($item) => $item->movement_date?->format('Y-m') === $key)->sum('amount') + (float) $bankDebits->filter(fn ($item) => $item->transaction_date?->format('Y-m') === $key)->sum('amount') + (float) $suppliers->filter(fn ($item) => $item->invoice_date?->format('Y-m') === $key)->sum('total_amount');
            $cursor->addMonth();
        }

        $ages = $employees->filter(fn ($employee) => $employee->birth_date)->map(fn ($employee) => $employee->birth_date->age)->sort()->values();
        $medianAge = $ages->count() ? $ages[(int) floor(($ages->count() - 1) / 2)] : 0;
        $averageSeniority = $employees->filter(fn ($employee) => $employee->hire_date)->avg(fn ($employee) => $employee->hire_date->diffInMonths(now()) / 12) ?: 0;
        $netAverage = (float) $payrolls->avg('net_salary') ?: (float) $employees->avg('monthly_salary');
        $ageBands = $employees->filter(fn ($employee) => $employee->birth_date)->groupBy(function ($employee) {
            $age = $employee->birth_date->age;
            return $age < 25 ? '< 25 ans' : ($age < 35 ? '25-34 ans' : ($age < 45 ? '35-44 ans' : '45 ans et +'));
        })->map->count();
        $monthlyExits = $employees->filter(fn ($employee) => $employee->contract_end_date && $employee->contract_end_date->year === $from->year)->groupBy(fn ($employee) => $employee->contract_end_date->month)->map->count();
        $monthlySalary = $payrolls->groupBy(fn ($payroll) => sprintf('%04d-%02d', $payroll->year, $payroll->month))->map(fn ($items) => (float) $items->avg('net_salary'));
        $leaveMonthly = LeaveRequest::where('entreprise_id', $tenant)->whereBetween('start_date', [$from->toDateString(), $to->toDateString()])->get()->groupBy(fn ($leave) => $leave->start_date->month)->map->count();

        $expenseCategories = $cashExits
            ->groupBy(fn ($movement) => $movement->expenseCategory?->name ?: 'Sans catégorie')
            ->map(fn ($items) => (float) $items->sum('amount'));

        $bankExpenses = (float) $bankDebits->sum('amount');
        $supplierExpenses = (float) $suppliers->sum('total_amount');
        if ($bankExpenses > 0) {
            $expenseCategories->put('Dépenses bancaires', $bankExpenses);
        }
        if ($supplierExpenses > 0) {
            $expenseCategories->put('Achats fournisseurs', $supplierExpenses);
        }

        // Indicateurs de tête comparés à la période précédente de même durée.
        [$previousFrom, $previousTo, $isMonthly] = $this->previousPeriod($from, $to);
        $current = $this->periodTotals($tenant, $from, $to);
        $previous = $this->periodTotals($tenant, $previousFrom, $previousTo);
        $net = $current['revenue'] - $current['expenses'];
        $variation = fn (float $now, float $before) => $before != 0.0 ? ($now - $before) / abs($before) * 100 : null;
        $overview = [
            'revenue' => $current['revenue'],
            'revenue_trend' => $variation($current['revenue'], $previous['revenue']),
            'expenses' => $current['expenses'],
            'expenses_trend' => $variation($current['expenses'], $previous['expenses']),
            'net' => $net,
            'net_trend' => $variation($net, $previous['revenue'] - $previous['expenses']),
            'margin' => $current['revenue'] > 0 ? $net / $current['revenue'] * 100 : null,
            'receivables' => $current['receivables'],
            'receivables_trend' => $variation($current['receivables'], $previous['receivables']),
            'comparison' => $isMonthly ? __('vs last month') : __('vs previous period'),
        ];

        // Évolution des recettes de janvier au mois de fin de période.
        $yearStart = $to->copy()->startOfYear();
        $yearEntries = CashMovement::where('entreprise_id', $tenant)->where('movement_type', 'entry')->whereBetween('movement_date', [$yearStart->toDateString(), $to->toDateString()])->get(['movement_date', 'amount']);
        $yearCredits = BankTransaction::where('entreprise_id', $tenant)->where('transaction_type', 'credit')->whereBetween('transaction_date', [$yearStart->toDateString(), $to->toDateString()])->get(['transaction_date', 'amount']);
        $revenueTrend = ['labels' => [], 'values' => []];
        for ($month = $yearStart->copy(); $month->lte($to); $month->addMonth()) {
            $key = $month->format('Y-m');
            $revenueTrend['labels'][] = ucfirst($month->translatedFormat('M'));
            $revenueTrend['values'][] = (float) $yearEntries->filter(fn ($item) => $item->movement_date?->format('Y-m') === $key)->sum('amount')
                + (float) $yearCredits->filter(fn ($item) => $item->transaction_date?->format('Y-m') === $key)->sum('amount');
        }
        $revenueTrend['range'] = ucfirst($yearStart->translatedFormat('M')) . ' - ' . $to->translatedFormat('M Y');

        // Répartition des dépenses : cinq premiers postes, le reste regroupé.
        $expenseTotal = (float) $expenseCategories->sum();
        $sortedExpenses = $expenseCategories->sortDesc();
        $expenseBreakdown = $sortedExpenses->take(5);
        if ($sortedExpenses->count() > 5) {
            $expenseBreakdown->put(__('Others'), (float) $sortedExpenses->slice(5)->sum());
        }
        $expenseBreakdown = $expenseBreakdown->map(fn ($amount, $label) => [
            'label' => $label,
            'amount' => $amount,
            'percent' => $expenseTotal > 0 ? $amount / $expenseTotal * 100 : 0,
        ])->values();

        return $this->page('dashboard', [
            'overview' => $overview,
            'revenueTrend' => $revenueTrend,
            'expenseBreakdown' => $expenseBreakdown,
            'title' => __('Dashboard'),
            'filters' => ['date_debut' => $from->toDateString(), 'date_fin' => $to->toDateString()],
            'treasury' => ['revenue' => $totalRevenue, 'cash_exits' => $cashExitTotal, 'balance' => $currentBalance, 'transactions' => $cashEntries->count() + $cashExits->count() + $bankCredits->count() + $bankDebits->count()],
            'accounting' => [
                'expenses' => array_sum($expenseSeries),
                'revenue_series' => $revenueSeries,
                'expense_series' => $expenseSeries,
                'expense_categories' => $expenseCategories,
            ],
            'monthLabels' => $monthLabels,
            'commercial' => ['sales' => (float) $invoices->sum('amount'), 'invoices' => $invoices->count(), 'receivables' => max((float) $invoices->sum('amount') - (float) $invoices->sum('paid_amount'), 0), 'purchases' => (float) $suppliers->sum('total_amount'), 'supplier_invoices' => $suppliers->count()],
            'hr' => ['count' => $employees->count(), 'median_age' => $medianAge, 'seniority' => $averageSeniority, 'net_average' => $netAverage, 'age_bands' => $ageBands, 'turnover' => $monthlyExits, 'salary' => $monthlySalary, 'contracts' => $employees->groupBy('contract_type')->map->count(), 'nationalities' => $employees->groupBy('nationality')->map->count(), 'categories' => $employees->groupBy('salary_category')->map->count(), 'departments' => $employees->groupBy('department')->map(fn ($items) => (float) $payrolls->whereIn('employee_id', $items->pluck('id'))->avg('net_salary')), 'leave' => $leaveMonthly],
        ]);
    }

    /**
     * Période précédente de même durée. Une période couvrant des mois entiers
     * est comparée aux mêmes nombres de mois entiers qui la précèdent.
     *
     * @return array{0: Carbon, 1: Carbon, 2: bool} début, fin, comparaison au mois précédent
     */
    private function previousPeriod(Carbon $from, Carbon $to): array
    {
        $wholeMonths = $from->isSameDay($from->copy()->startOfMonth()) && $to->isSameDay($to->copy()->endOfMonth());
        if ($wholeMonths) {
            $months = $from->diffInMonths($to->copy()->addDay()) ?: 1;
            $previousFrom = $from->copy()->subMonthsNoOverflow($months)->startOfMonth();

            return [$previousFrom, $previousFrom->copy()->addMonthsNoOverflow($months - 1)->endOfMonth(), $months === 1];
        }

        $previousTo = $from->copy()->subDay()->endOfDay();

        return [$previousTo->copy()->subDays($from->diffInDays($to))->startOfDay(), $previousTo, false];
    }

    /**
     * Recettes, dépenses et créances d'une période, selon les mêmes règles que
     * le reste du tableau de bord.
     *
     * @return array{revenue: float, expenses: float, receivables: float}
     */
    private function periodTotals(?int $tenant, Carbon $from, Carbon $to): array
    {
        $dates = [$from->toDateString(), $to->toDateString()];
        $cash = CashMovement::where('entreprise_id', $tenant)->whereBetween('movement_date', $dates);
        $bank = BankTransaction::where('entreprise_id', $tenant)->whereBetween('transaction_date', $dates);
        $invoices = CommercialInvoice::where('entreprise_id', $tenant)->whereBetween('created_at', [$from, $to]);

        return [
            'revenue' => (float) (clone $cash)->where('movement_type', 'entry')->sum('amount')
                + (float) (clone $bank)->where('transaction_type', 'credit')->sum('amount'),
            'expenses' => (float) (clone $cash)->where('movement_type', 'exit')->sum('amount')
                + (float) (clone $bank)->where('transaction_type', 'debit')->where('is_transfer', false)->sum('amount')
                + (float) SupplierInvoice::where('entreprise_id', $tenant)->whereBetween('invoice_date', $dates)->sum('total_amount'),
            'receivables' => max((float) (clone $invoices)->sum('amount') - (float) (clone $invoices)->sum('paid_amount'), 0),
        ];
    }
}
