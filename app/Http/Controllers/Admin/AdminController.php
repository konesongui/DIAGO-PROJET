<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            $this->applyEnterpriseMailSettings();
            $module = $this->permissionModuleForRoute($request->route()?->getName());

            if ($module !== null) {
                $ability = match ($request->method()) {
                    'GET', 'HEAD' => 'view',
                    'DELETE' => 'delete',
                    default => 'edit',
                };

                $this->authorizeModule($module, $ability);
            }

            return $next($request);
        });
    }

    protected function page(string $view, array $data = [])
    {
        $data = $this->translatePageMetadata($data);

        return view('admin.' . $view, $data);
    }

    private function translatePageMetadata(array $data): array
    {
        $translatableKeys = ['title', 'subtitle', 'description', 'heading', 'label'];

        foreach ($data as $key => $value) {
            if (in_array($key, $translatableKeys, true) && is_string($value)) {
                $data[$key] = __($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->translatePageMetadata($value);
            }
        }

        return $data;
    }

    protected function authorizeModule(string $module, string $ability = 'view'): void
    {
        abort_unless(auth()->user()->hasPermission($module, $ability), 403);
    }

    protected function applyEnterpriseMailSettings(): void
    {
        $settings = auth()->user()->entreprise?->settings['email'] ?? [];
        if (!is_array($settings)) {
            return;
        }
        if (empty($settings['host'])) {
            return;
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', $settings['host']);
        Config::set('mail.mailers.smtp.port', (int) ($settings['port'] ?? 587));
        Config::set('mail.mailers.smtp.encryption', $settings['encryption'] ?: null);
        Config::set('mail.mailers.smtp.username', $settings['username'] ?? null);
        Config::set('mail.mailers.smtp.password', $settings['password'] ?? null);
        Config::set('mail.from.address', $settings['from_address'] ?? '');
        Config::set('mail.from.name', $settings['from_name'] ?? config('app.name'));
    }

    private function permissionModuleForRoute(?string $routeName): ?string
    {
        if ($routeName === null || !str_starts_with($routeName, 'admin.')) {
            return null;
        }

        return match (true) {
            str_starts_with($routeName, 'admin.users') => 'users',
            str_starts_with($routeName, 'admin.entreprises') => 'entreprises',
            str_starts_with($routeName, 'admin.settings') => 'settings',
            $routeName === 'admin.demorequests' => 'demo_requests',
            str_starts_with($routeName, 'admin.commercial.clients') => 'clients',
            str_starts_with($routeName, 'admin.commercial.suppliers') => 'suppliers',
            str_starts_with($routeName, 'admin.commercial.services') => 'services',
            str_starts_with($routeName, 'admin.commercial.quotes') => 'quotes',
            str_starts_with($routeName, 'admin.commercial.proforma') => 'proformas',
            str_starts_with($routeName, 'admin.commercial.invoices') => 'invoices',
            str_starts_with($routeName, 'admin.commercial.deliveries') => 'deliveries',
            str_starts_with($routeName, 'admin.commercial.stock-entries') => 'stock_entries',
            str_starts_with($routeName, 'admin.commercial.stock-exits') => 'stock_exits',
            str_starts_with($routeName, 'admin.commercial.inventory') => 'inventory',
            str_starts_with($routeName, 'admin.commercial.pos') => 'pos',
            str_starts_with($routeName, 'admin.commercial.objectives') => 'objectives',
            str_starts_with($routeName, 'admin.commercial') => 'commercial',
            str_starts_with($routeName, 'admin.comptabilite.caisses') => 'cash',
            str_starts_with($routeName, 'admin.comptabilite.banques') => 'banks',
            str_starts_with($routeName, 'admin.comptabilite.rapports') => 'accounting_reports',
            str_starts_with($routeName, 'admin.comptabilite.rapport_financier') => 'financial_report',
            str_starts_with($routeName, 'admin.comptabilite.transfers') => 'transfers',
            str_starts_with($routeName, 'admin.comptabilite.expenseCategories') => 'expense_categories',
            str_starts_with($routeName, 'admin.comptabilite.fixedAssets') => 'fixed_assets',
            str_starts_with($routeName, 'admin.comptabilite.supplierInvoices') => 'supplier_invoices',
            str_starts_with($routeName, 'admin.comptabilite') => 'accounting',
            str_starts_with($routeName, 'admin.rh.employees') => 'employees',
            str_starts_with($routeName, 'admin.rh.payroll') => 'payslips',
            str_starts_with($routeName, 'admin.rh.payrollBook') => 'payroll_book',
            str_starts_with($routeName, 'admin.rh.qr') => 'attendance_qr',
            str_starts_with($routeName, 'admin.rh.attendance.today') => 'attendance_today',
            str_starts_with($routeName, 'admin.rh.attendance.report') => 'attendance_reports',
            str_starts_with($routeName, 'admin.rh.salaryCategories') => 'salary_categories',
            str_starts_with($routeName, 'admin.rh.leaveTypes') => 'leave_settings',
            str_starts_with($routeName, 'admin.rh.leaves') => 'leaves',
            str_starts_with($routeName, 'admin.rh.leaveCalendar') => 'leave_calendar',
            str_starts_with($routeName, 'admin.rh.permissions') => 'permission_requests',
            str_starts_with($routeName, 'admin.rh') => 'hr',
            str_starts_with($routeName, 'admin.administration') => 'administration',
            str_starts_with($routeName, 'admin.succursales') => 'succursales',
            str_starts_with($routeName, 'admin.departments') => 'departments',
            str_starts_with($routeName, 'admin.designations') => 'designations',
            $routeName === 'admin.dashboard' => 'dashboard',
            default => null,
        };
    }
}
