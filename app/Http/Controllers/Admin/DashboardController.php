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

        return $this->page('dashboard', [
            'title' => 'Pilotage Global',
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
}
