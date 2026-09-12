<?php

namespace App\Services;

use App\Models\Entreprise;
use App\Models\Role;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Création d'une entreprise complète : la fiche, son compte administrateur,
 * les rubriques ouvertes, les taux de taxe et le plan comptable.
 *
 * Une entreprise créée sans compte administrateur, sans rubrique ouverte, sans
 * taux de taxe ni plan comptable est inutilisable : personne ne peut s'y
 * connecter et aucune facture ne peut être établie. Le même chemin sert à la
 * console super-admin et à l'écran Entreprises.
 */
class EntrepriseProvisioner
{
    /** Rubriques proposées à l'ouverture d'une entreprise. */
    public function rubriques(): array
    {
        return [
            'pilotage' => ['label' => 'Pilotage', 'description' => 'Tableau de bord et rapports de pilotage.', 'icon' => 'bi-bar-chart'],
            'commercial' => ['label' => 'Commercial', 'description' => 'Clients, ventes, stocks et point de vente.', 'icon' => 'bi-cart'],
            'comptabilite' => ['label' => 'Comptabilité', 'description' => 'Caisses, banques et rapports comptables.', 'icon' => 'bi-credit-card'],
            'rh' => ['label' => 'RH & Paie', 'description' => 'Employés, services, fonctions et paie.', 'icon' => 'bi-people'],
            'administration' => ['label' => 'Administration', 'description' => 'Visiteurs, appels, courriers, réunions et documents.', 'icon' => 'bi-folder'],
            'succursales' => ['label' => 'Succursales', 'description' => 'Établissements rattachés à l’entreprise.', 'icon' => 'bi-shop'],
        ];
    }

    /** Toutes les rubriques du catalogue, cochées ou non. */
    public function normalizeRubriques(array $rubriques): array
    {
        $enabled = [];
        foreach (array_keys($this->rubriques()) as $key) {
            $enabled[$key] = ! empty($rubriques[$key]);
        }

        return $enabled;
    }

    public function slugFor(string $name, ?string $slug = null): string
    {
        return $slug ? Str::slug($slug) : Str::slug($name);
    }

    /**
     * @param array{name:string,slug?:?string,activity?:?string,email?:?string,phone?:?string,address?:?string,city?:?string,trade_name?:?string,currency?:?string,subscription_expires_at?:?string,rubriques?:array,ai_assistant_enabled?:bool,database_name?:?string,admin_name:string,admin_email:string,admin_password:string} $data
     */
    public function create(array $data, ?int $createdBy = null): Entreprise
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrateur']);
        $currency = $data['currency'] ?? 'XOF';

        return DB::transaction(function () use ($data, $adminRole, $createdBy, $currency) {
            $entreprise = Entreprise::create([
                'name' => $data['name'],
                'slug' => $this->slugFor($data['name'], $data['slug'] ?? null),
                'database_name' => $data['database_name'] ?? null,
                'is_active' => true,
                'created_by' => $createdBy,
                'settings' => array_filter([
                    'locale' => 'fr',
                    'currency' => $currency,
                    'currency_symbol' => $currency === 'EUR' ? '€' : 'FCFA',
                    // La raison sociale sert aussi d'en-tête des documents imprimés.
                    'name' => $data['name'],
                    'trade_name' => $data['trade_name'] ?? null,
                    'activity' => $data['activity'] ?? null,
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'address' => $data['address'] ?? null,
                    'city' => $data['city'] ?? null,
                    'subscription_expires_at' => $data['subscription_expires_at'] ?? null,
                    'enabled_rubriques' => $this->normalizeRubriques($data['rubriques'] ?? []),
                    'ai_assistant_enabled' => ! empty($data['ai_assistant_enabled']),
                ], fn ($value) => $value !== null),
            ]);

            User::create([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => Hash::make($data['admin_password']),
                'entreprise_id' => $entreprise->id,
                'role_id' => $adminRole->id,
                'is_active' => true,
            ]);

            // Sans taux de taxe ni plan comptable, aucune facture ni écriture n'est possible.
            foreach (app(TaxService::class)->defaultRatesFor($currency) as $rate) {
                TaxRate::withoutGlobalScope('entreprise')->create($rate + ['entreprise_id' => $entreprise->id]);
            }
            app(LedgerService::class)->ensureChartOfAccounts($entreprise->id);

            return $entreprise;
        });
    }
}
