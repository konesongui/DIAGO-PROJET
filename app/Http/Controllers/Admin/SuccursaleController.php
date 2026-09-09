<?php

namespace App\Http\Controllers\Admin;

use App\Models\Succursale;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SuccursaleController extends AdminController
{
    public function index()
    {
        $this->ensureModuleEnabled();
        $query = Succursale::with('users')->where('entreprise_id', auth()->user()->entreprise_id);
        if (!auth()->user()->isEntrepriseAdmin()) {
            $query->whereKey(auth()->user()->succursale_id);
        }
        $succursales = $query
            ->latest()
            ->get();

        return $this->page('succursales', [
            'title' => 'Succursales',
            'succursales' => $succursales,
            'canManageAll' => auth()->user()->isEntrepriseAdmin(),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureModuleEnabled();
        abort_unless(auth()->user()->isEntrepriseAdmin(), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $exists = Succursale::where('entreprise_id', auth()->user()->entreprise_id)
            ->where('code', $validated['code'])
            ->exists();
        if ($exists) {
            return back()->withInput()->withErrors(['code' => 'Ce code est déjà utilisé dans cette entreprise.']);
        }

        $adminRole = Role::where('name', 'admin')->firstOrFail();
        DB::transaction(function () use ($validated, $adminRole) {
            $succursale = Succursale::create([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'entreprise_id' => auth()->user()->entreprise_id,
                'is_active' => true,
            ]);
            User::create([
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'password' => Hash::make($validated['admin_password']),
                'entreprise_id' => auth()->user()->entreprise_id,
                'succursale_id' => $succursale->id,
                'role_id' => $adminRole->id,
                'is_active' => true,
            ]);
        });

        return back()->with('success', 'Succursale créée avec succès.');
    }

    public function update(Request $request, Succursale $succursale)
    {
        $this->ensureModuleEnabled();
        $this->ensureTenant($succursale);
        $this->ensurePrimaryAdmin();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $exists = Succursale::where('entreprise_id', auth()->user()->entreprise_id)
            ->where('code', $validated['code'])
            ->where('id', '!=', $succursale->id)
            ->exists();
        if ($exists) {
            return back()->withInput()->withErrors(['code' => 'Ce code est déjà utilisé dans cette entreprise.']);
        }

        $succursale->update($validated);

        return back()->with('success', 'Succursale modifiée avec succès.');
    }

    public function toggle(Succursale $succursale)
    {
        $this->ensureModuleEnabled();
        $this->ensureTenant($succursale);
        $this->ensurePrimaryAdmin();
        $succursale->update(['is_active' => !$succursale->is_active]);

        return back()->with('success', 'Statut de la succursale mis à jour.');
    }

    public function destroy(Succursale $succursale)
    {
        $this->ensureModuleEnabled();
        $this->ensureTenant($succursale);
        $this->ensurePrimaryAdmin();
        $succursale->delete();

        return back()->with('success', 'Succursale supprimée.');
    }

    private function ensureTenant(Succursale $succursale): void
    {
        abort_unless($succursale->entreprise_id === auth()->user()->entreprise_id, 404);
    }

    private function ensureModuleEnabled(): void
    {
        abort_unless((bool) data_get(auth()->user()->entreprise?->settings ?? [], 'enabled_rubriques.succursales', false), 403);
    }

    private function ensurePrimaryAdmin(): void
    {
        abort_unless(auth()->user()->isEntrepriseAdmin(), 403);
    }
}
