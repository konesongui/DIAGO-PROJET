<?php

namespace App\Http\Controllers\Admin;

use App\Models\Entreprise;
use App\Models\User;
use App\Services\EntrepriseProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EntrepriseController extends AdminController
{
    public function __construct(private EntrepriseProvisioner $provisioner)
    {
        parent::__construct();
    }

    /** Tables qui retiennent une entreprise : tant qu'elles portent des lignes, la fiche ne se supprime pas. */
    private const BUSINESS_TABLES = [
        'users' => 'compte(s) utilisateur',
        'commercial_clients' => 'client(s)',
        'commercial_invoices' => 'facture(s)',
        'employees' => 'employé(s)',
        'cash_accounts' => 'caisse(s)',
        'bank_accounts' => 'compte(s) bancaire(s)',
    ];

    public function index()
    {
        $entreprises = Entreprise::withCount('users')->latest()->get();

        return $this->page('entreprises.index', [
            'title' => 'Entreprises & filiales',
            'subtitle' => 'Supervisez l’ensemble des entités juridiques gérées sous ce compte DIAGO.',
            'entreprises' => $entreprises,
        ]);
    }

    public function create()
    {
        return $this->page('entreprises.form', [
            'title' => 'Nouvelle entreprise',
            'subtitle' => 'La fiche, son compte administrateur et les rubriques ouvertes.',
            'entreprise' => null,
            'rubriques' => $this->provisioner->rubriques(),
            'enabled' => array_fill_keys(array_keys($this->provisioner->rubriques()), true),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $entreprise = $this->provisioner->create($validated + [
            'admin_name' => $validated['admin_name'],
            'admin_email' => $validated['admin_email'],
            'admin_password' => $validated['admin_password'],
            'rubriques' => $request->input('rubriques', []),
        ], auth()->id());

        return redirect()->route('admin.entreprises.index')
            ->with('success', 'Entreprise « ' . $entreprise->name . ' » créée avec son compte administrateur ' . $validated['admin_email'] . '.');
    }

    public function edit(Entreprise $entreprise)
    {
        return $this->page('entreprises.form', [
            'title' => 'Modifier une entreprise',
            'subtitle' => $entreprise->name,
            'entreprise' => $entreprise,
            'rubriques' => $this->provisioner->rubriques(),
            'enabled' => array_merge(
                array_fill_keys(array_keys($this->provisioner->rubriques()), false),
                (array) data_get($entreprise->settings, 'enabled_rubriques', [])
            ),
        ]);
    }

    public function update(Request $request, Entreprise $entreprise)
    {
        $validated = $this->validated($request, $entreprise);

        $settings = $entreprise->settings ?? [];
        $settings['name'] = $validated['name'];
        $settings['activity'] = $validated['activity'] ?? null;
        $settings['email'] = $validated['email'] ?? null;
        $settings['phone'] = $validated['phone'] ?? null;
        $settings['address'] = $validated['address'] ?? null;
        $settings['city'] = $validated['city'] ?? null;
        $settings['subscription_expires_at'] = $validated['subscription_expires_at'] ?? null;
        $settings['enabled_rubriques'] = $this->provisioner->normalizeRubriques($request->input('rubriques', []));

        $entreprise->update([
            'name' => $validated['name'],
            'slug' => $this->provisioner->slugFor($validated['name'], $validated['slug'] ?? null),
            'settings' => $settings,
        ]);

        return redirect()->route('admin.entreprises.index')
            ->with('success', 'Entreprise « ' . $entreprise->name . ' » mise à jour.');
    }

    /** L'activation suit sur les comptes : un compte d'entreprise fermée ne doit pas se connecter. */
    public function toggle(Entreprise $entreprise)
    {
        if ($entreprise->id === auth()->user()->entreprise_id) {
            return back()->with('error', 'Vous ne pouvez pas désactiver l’entreprise à laquelle votre compte est rattaché.');
        }

        $entreprise->update(['is_active' => ! $entreprise->is_active]);
        User::where('entreprise_id', $entreprise->id)->update(['is_active' => $entreprise->is_active]);

        return back()->with('success', 'Entreprise « ' . $entreprise->name . ' » '
            . ($entreprise->is_active ? 'réactivée' : 'désactivée') . ' : ses comptes suivent.');
    }

    public function destroy(Entreprise $entreprise)
    {
        if ($entreprise->id === auth()->user()->entreprise_id) {
            return back()->with('error', 'Vous ne pouvez pas supprimer l’entreprise à laquelle votre compte est rattaché.');
        }

        // Supprimer une entreprise efface en cascade toutes ses données : on ne le permet que sur une fiche vide.
        $holdings = [];
        foreach (self::BUSINESS_TABLES as $table => $label) {
            $count = DB::table($table)->where('entreprise_id', $entreprise->id)->count();
            if ($count > 0) {
                $holdings[] = $count . ' ' . $label;
            }
        }

        if ($holdings) {
            return back()->with('error', 'Impossible de supprimer « ' . $entreprise->name . ' » : la fiche porte ' . implode(', ', $holdings)
                . '. Désactivez-la pour en fermer l’accès sans rien effacer.');
        }

        $name = $entreprise->name;
        $entreprise->delete();

        return back()->with('success', 'Entreprise « ' . $name . ' » supprimée.');
    }

    private function validated(Request $request, ?Entreprise $entreprise = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash'],
            'activity' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'subscription_expires_at' => ['required', 'date'],
            'rubriques' => ['required', 'array', 'min:1'],
        ];

        if (! $entreprise) {
            $rules['subscription_expires_at'][] = 'after_or_equal:today';
            $rules['admin_name'] = ['required', 'string', 'max:255'];
            $rules['admin_email'] = ['required', 'email', 'max:255', 'unique:users,email'];
            $rules['admin_password'] = ['required', 'string', 'min:8', 'confirmed'];
        }

        $validator = validator($request->all(), $rules, [
            'rubriques.required' => 'Ouvrez au moins une rubrique : sans elle, l’entreprise n’a aucun menu.',
            'rubriques.min' => 'Ouvrez au moins une rubrique : sans elle, l’entreprise n’a aucun menu.',
        ], [
            'name' => 'raison sociale',
            'address' => 'siège social',
            'subscription_expires_at' => 'échéance de l’abonnement',
            'admin_name' => 'nom de l’administrateur',
            'admin_email' => 'e-mail de l’administrateur',
            'admin_password' => 'mot de passe',
        ]);

        // Le slug identifie l'entreprise dans les adresses : il reste unique.
        $validator->after(function ($validator) use ($request, $entreprise) {
            $name = (string) $request->input('name');
            if ($name === '') {
                return;
            }
            $slug = $this->provisioner->slugFor($name, $request->input('slug'));
            $taken = Entreprise::where('slug', $slug)
                ->when($entreprise, fn ($query) => $query->where('id', '!=', $entreprise->id))
                ->exists();
            if ($taken) {
                $validator->errors()->add('slug', 'L’identifiant « ' . $slug . ' » est déjà utilisé par une autre entreprise.');
            }
        });

        $validated = $validator->validate();

        return $validated;
    }
}
