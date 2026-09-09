<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TenantContext
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $sessionLocale = session('locale');
        $locale = $sessionLocale
            ?: data_get($user?->entreprise?->settings ?? [], 'locale', 'fr');
        app()->setLocale(in_array($locale, ['fr', 'en'], true) ? $locale : 'fr');
        $pendingPermissions = 0;
        $pendingLeaves = 0;
        $stockAlerts = collect();
        if ($user?->entreprise_id) {
            $employee = \App\Models\Employee::where('user_id', $user->id)->first();
            $pendingPermissionsQuery = \App\Models\PermissionRequest::where('entreprise_id', $user->entreprise_id)->where('status', 'pending');
            $pendingLeavesQuery = \App\Models\LeaveRequest::where('entreprise_id', $user->entreprise_id)->where('status', 'pending');
            if (!$user->hasRole('admin')) {
                $pendingPermissionsQuery->where('employee_id', $employee?->id);
                $pendingLeavesQuery->where('employee_id', $employee?->id);
            }
            $pendingPermissions = $pendingPermissionsQuery->count();
            $pendingLeaves = $pendingLeavesQuery->count();
            $entries = \App\Models\StockEntryLine::whereHas('stockEntry', fn ($query) => $query->where('entreprise_id', $user->entreprise_id))->get();
            $exits = \App\Models\StockExitLine::whereHas('entryLine.stockEntry', fn ($query) => $query->where('entreprise_id', $user->entreprise_id))->get();
            $stockTotals = [];
            foreach ($entries as $line) {
                $key = strtolower($line->designation . '|' . ($line->article ?: '') . '|' . ($line->unit ?: ''));
                $stockTotals[$key] ??= ['name' => $line->designation, 'article' => $line->article ?: '', 'unit' => $line->unit ?: '', 'quantity' => 0];
                $stockTotals[$key]['quantity'] += (float) $line->quantity;
            }
            foreach ($exits as $line) {
                $key = strtolower($line->designation . '|' . ($line->article ?: '') . '|' . ($line->unit ?: ''));
                if (isset($stockTotals[$key])) {
                    $stockTotals[$key]['quantity'] -= (float) $line->quantity;
                }
            }
            $stockAlerts = collect($stockTotals)->filter(fn ($item) => $item['quantity'] <= 5)->values();
        }
        view()->share([
            'pendingPermissions' => $pendingPermissions,
            'pendingLeaves' => $pendingLeaves,
            'stockAlerts' => $stockAlerts,
            'notificationCount' => $pendingPermissions + $pendingLeaves + $stockAlerts->count(),
        ]);

        $tenantId = $request->session()->get('tenant_id')
            ?? $request->query('tenant_id')
            ?? auth()->id();

        if ($tenantId) {
            $request->session()->put('tenant_id', $tenantId);
            view()->share('currentTenantId', $tenantId);
        }

        return $next($request);
    }
}
