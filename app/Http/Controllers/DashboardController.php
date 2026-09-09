<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $tenantId = $request->session()->get('tenant_id') ?? auth()->user()?->entreprise_id;

        return view('dashboard', [
            'tenantId' => $tenantId,
            'user' => auth()->user(),
            'title' => 'Diagoma - Tableau de bord',
        ]);
    }
}
