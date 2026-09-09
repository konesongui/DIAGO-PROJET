<?php

namespace App\Http\Controllers\Admin;

use App\Models\Entreprise;
use App\Models\Role;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends AdminController
{
    public function index()
    {
        $this->authorizeUsers('view');
        $users = User::with(['role', 'entreprise'])
            ->where('entreprise_id', auth()->user()->entreprise_id)
            ->latest()
            ->get();

        return $this->page('users.index', [
            'title' => 'Utilisateurs',
            'users' => $users,
            'permissionModules' => $this->permissionModules(),
        ]);
    }

    public function updatePermissions(Request $request, User $user)
    {
        $this->authorizeUsers('edit');
        abort_unless($user->entreprise_id === auth()->user()->entreprise_id, 403);

        $modules = collect($this->permissionModules())->flatMap(fn ($rubrique) => array_keys($rubrique['modules']))->all();
        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['array'],
            'permissions.*.*' => ['boolean'],
        ]);
        $permissions = [];
        foreach ($modules as $module) {
            $permissions[$module] = [
                'view' => !empty($validated['permissions'][$module]['view']),
                'edit' => !empty($validated['permissions'][$module]['edit']),
                'delete' => !empty($validated['permissions'][$module]['delete']),
            ];
        }
        $user->update(['permissions' => $permissions]);

        return back()->with('success', 'Permissions mises à jour.');
    }

    public function toggleStatus(User $user)
    {
        $this->authorizeUsers('edit');
        abort_unless($user->entreprise_id === auth()->user()->entreprise_id, 403);

        if (auth()->id() === $user->id) {
            return back()->with('error', 'Vous ne pouvez pas désactiver votre propre compte.');
        }

        $user->update(['is_active' => !$user->is_active]);

        return back()->with('success', $user->is_active
            ? 'Le compte utilisateur a été activé.'
            : 'Le compte utilisateur a été désactivé.');
    }

    private function permissionModules(): array
    {
        return [
            'pilotage' => ['label' => 'Pilotage', 'modules' => ['dashboard' => 'Tableau de bord', 'reports' => 'Rapports']],
            'commercial' => [
                'label' => 'Commercial',
                'modules' => [
                    'commercial' => 'Vue commerciale',
                    'clients' => 'Clients',
                    'suppliers' => 'Fournisseurs',
                    'services' => 'Services',
                    'stock' => 'Stocks',
                    'quotes' => 'Devis',
                    'proformas' => 'Proformas',
                    'invoices' => 'Factures',
                    'deliveries' => 'Livraisons',
                    'stock_entries' => 'Entrées de stock',
                    'stock_exits' => 'Sorties de stock',
                    'inventory' => 'Inventaire',
                    'pos' => 'Point de vente',
                    'objectives' => 'Objectifs commerciaux',
                ],
            ],
            'comptabilite' => [
                'label' => 'Comptabilité',
                'modules' => [
                    'accounting' => 'Vue comptabilité',
                    'cash' => 'Caisses',
                    'banks' => 'Banques',
                    'accounting_reports' => 'Rapports comptables',
                    'financial_report' => 'Rapport financier',
                    'transfers' => 'Transferts',
                    'expense_categories' => 'Catégories de dépenses',
                    'fixed_assets' => 'Immobilisations',
                    'supplier_invoices' => 'Factures fournisseurs',
                ],
            ],
            'rh' => [
                'label' => 'RH & Paie',
                'modules' => [
                    'hr' => 'Vue RH & Paie',
                    'employees' => 'Liste du personnel',
                    'attendance_qr' => 'QR Code de pointage',
                    'attendance_today' => 'Présences du jour',
                    'attendance_reports' => 'Rapports de présence',
                    'payslips' => 'Bulletins de paie',
                    'payroll_book' => 'Livre de paie',
                    'salary_categories' => 'Catégories salariales',
                    'leave_settings' => 'Paramétrage des congés',
                    'leaves' => 'Liste des congés',
                    'leave_calendar' => 'Calendrier des congés',
                    'permission_requests' => 'Demandes de permission',
                    'departments' => 'Services',
                    'designations' => 'Fonctions',
                ],
            ],
            'administration' => [
                'label' => 'Administration',
                'modules' => [
                    'users' => 'Utilisateurs',
                    'entreprises' => 'Entreprises',
                    'settings' => 'Paramètres',
                    'demo_requests' => 'Demandes de démo',
                    'visiteurs' => 'Gestion des visiteurs',
                    'appels' => 'Gestion des appels',
                    'courriers' => 'Gestion des courriers',
                    'reunions' => 'Gestion des réunions',
                    'documents' => 'Gestion des documents',
                ],
            ],
        ];
    }

    public function create()
    {
        $this->authorizeUsers('edit');
        return $this->page('users.form', [
            'title' => 'Créer un utilisateur',
            'user' => null,
            'roles' => Role::all(),
            'entreprises' => Entreprise::all(),
            'isOwnProfile' => false,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $user->id],
            'address' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'phone' => ['nullable', 'string', 'max:50'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:30'],
            'birth_date' => ['nullable', 'date'],
            'marital_status' => ['nullable', 'string', 'max:50'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'experience' => ['nullable', 'string'],
            'remark' => ['nullable', 'string'],
            'permanent_address' => ['nullable', 'string', 'max:255'],
            'account_title' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:100'],
            'ifsc_code' => ['nullable', 'string', 'max:100'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'twitter_url' => ['nullable', 'url', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'matricule' => ['nullable', 'string', 'max:100'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'services' => ['nullable', 'string', 'max:255'],
            'manager' => ['nullable', 'string', 'max:255'],
            'cnps' => ['nullable', 'string', 'max:100'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'contract_type' => ['nullable', 'string', 'max:100'],
            'workstation' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'registered_at' => ['nullable', 'date'],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'address' => $validated['address'] ?? null,
        ]);
        $profileFields = ['phone', 'emergency_contact', 'gender', 'birth_date', 'marital_status', 'father_name', 'mother_name', 'qualification', 'experience', 'remark', 'permanent_address', 'account_title', 'bank_name', 'bank_branch', 'bank_account_number', 'ifsc_code', 'facebook_url', 'twitter_url', 'linkedin_url', 'instagram_url', 'matricule', 'job_title', 'services', 'manager', 'cnps', 'base_salary', 'contract_type', 'workstation', 'location', 'registered_at'];
        $user->fill($request->only($profileFields));
        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $user->avatar_path = $request->file('avatar')->store('profile-photos', 'public');
        }
        $user->save();

        return back()->with('success', 'Informations du profil enregistrées.');
    }

    public function profile()
    {
        $user = auth()->user()->load('role');
        $employee = Employee::where('user_id', $user->id)->first();
        return $this->page('users.profile', [
            'title' => 'Mon profil',
            'user' => $user,
            'employee' => $employee,
            'payrolls' => $employee
                ? $employee->payrolls()->latest('year')->latest('month')->get()
                : collect(),
        ]);
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);
        if (!empty($validated['password'])) {
            $request->user()->update(['password' => Hash::make($validated['password'])]);
            return back()->with('success', 'Mot de passe modifié.');
        }
        return back()->with('success', 'Aucun changement de mot de passe.');
    }

    public function store(Request $request)
    {
        $this->authorizeUsers('edit');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role_id' => ['required', 'exists:roles,id'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'],
            'entreprise_id' => auth()->user()->entreprise_id,
        ]);

        return redirect()->route('admin.users.index')->with('success', 'Utilisateur créé avec succès.');
    }

    public function edit(User $user)
    {
        $this->authorizeUsers('edit');
        abort_unless($user->entreprise_id === auth()->user()->entreprise_id, 403);
        if (auth()->id() === $user->id) {
            return redirect()->route('admin.profile');
        }
        return $this->page('users.form', [
            'title' => 'Modifier un utilisateur',
            'user' => $user,
            'roles' => Role::all(),
            'entreprises' => Entreprise::all(),
            'isOwnProfile' => auth()->id() === $user->id,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeUsers('edit');
        abort_unless($user->entreprise_id === auth()->user()->entreprise_id, 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role_id' => $validated['role_id'],
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $user->avatar_path = $request->file('avatar')->store('profile-photos', 'public');
        }

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()->route('admin.users.index')->with('success', 'Utilisateur mis à jour.');
    }

    public function destroy(User $user)
    {
        $this->authorizeUsers('delete');
        abort_unless($user->entreprise_id === auth()->user()->entreprise_id, 403);
        if (auth()->id() === $user->id) {
            return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        }

        $user->delete();

        return back()->with('success', 'Utilisateur supprimé.');
    }

    private function authorizeUsers(string $ability): void
    {
        abort_unless(auth()->user()->hasPermission('users', $ability), 403);
    }
}
