<?php

namespace App\Http\Controllers\Admin;

use App\Models\Role;
use App\Models\Succursale;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SuccursaleController extends AdminController
{
    public function index()
    {
        $this->ensureModuleEnabled();
        $query = Succursale::with('users:id,succursale_id,name,email,is_active')
            ->where('entreprise_id', auth()->user()->entreprise_id);

        if (! auth()->user()->isEntrepriseAdmin()) {
            $query->whereKey(auth()->user()->succursale_id);
        }

        return $this->page('succursales', [
            'title' => 'Succursales',
            'subtitle' => 'Établissements de l’entreprise, leurs comptes et leur état.',
            'succursales' => $query->orderBy('name')->get(),
            'canManageAll' => auth()->user()->isEntrepriseAdmin(),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureModuleEnabled();
        $this->ensurePrimaryAdmin();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [], [
            'name' => 'nom',
            'admin_name' => 'nom du responsable',
            'admin_email' => 'e-mail du responsable',
            'admin_password' => 'mot de passe',
        ]);

        if ($this->codeTaken($validated['code'])) {
            return back()->withInput()->withErrors(['code' => 'Le code « ' . $validated['code'] . ' » est déjà utilisé par une autre succursale.']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrateur']);
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

        return back()->with('success', 'Succursale « ' . $validated['name'] . ' » créée avec le compte ' . $validated['admin_email'] . '.');
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
        ], [], ['name' => 'nom']);

        if ($this->codeTaken($validated['code'], $succursale)) {
            return back()->withInput()->withErrors(['code' => 'Le code « ' . $validated['code'] . ' » est déjà utilisé par une autre succursale.']);
        }

        $succursale->update($validated);

        return back()->with('success', 'Succursale « ' . $succursale->name . ' » modifiée.');
    }

    /** L'état suit sur les comptes rattachés : fermer une succursale doit fermer ses accès. */
    public function toggle(Succursale $succursale)
    {
        $this->ensureModuleEnabled();
        $this->ensureTenant($succursale);
        $this->ensurePrimaryAdmin();
        $succursale->update(['is_active' => ! $succursale->is_active]);
        User::where('succursale_id', $succursale->id)->update(['is_active' => $succursale->is_active]);

        return back()->with('success', 'Succursale « ' . $succursale->name . ' » '
            . ($succursale->is_active ? 'rouverte' : 'fermée') . ' : ses comptes suivent.');
    }

    public function destroy(Succursale $succursale)
    {
        $this->ensureModuleEnabled();
        $this->ensureTenant($succursale);
        $this->ensurePrimaryAdmin();

        // Sans ce garde-fou, la suppression détache les comptes de la succursale :
        // leur rôle « admin » sans succursale en ferait des administrateurs de l'entreprise entière.
        $accounts = User::where('succursale_id', $succursale->id)->count();
        if ($accounts > 0) {
            return back()->with('error', 'Impossible de supprimer « ' . $succursale->name . ' » : ' . $accounts
                . ' compte(s) y sont rattachés et deviendraient administrateurs de toute l’entreprise. Fermez la succursale, ou déplacez ses comptes.');
        }

        $name = $succursale->name;
        $succursale->delete();

        return back()->with('success', 'Succursale « ' . $name . ' » supprimée.');
    }

    /** Le code identifie la succursale : « ab » et « AB » sont le même code. */
    private function codeTaken(string $code, ?Succursale $current = null): bool
    {
        return Succursale::where('entreprise_id', auth()->user()->entreprise_id)
            ->whereRaw('LOWER(code) = ?', [mb_strtolower(trim($code))])
            ->when($current, fn ($query) => $query->where('id', '!=', $current->id))
            ->exists();
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
