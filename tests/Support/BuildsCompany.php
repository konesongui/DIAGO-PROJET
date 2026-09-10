<?php

namespace Tests\Support;

use App\Models\Entreprise;
use App\Models\LedgerAccount;
use App\Models\Role;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\TaxService;
use Illuminate\Support\Facades\Hash;

/**
 * Fabrique une entreprise complete pour les tests : taux de taxe, plan
 * comptable et utilisateur administrateur.
 */
trait BuildsCompany
{
    protected function makeCompany(string $slug = 'test-co', string $currency = 'XOF'): Entreprise
    {
        $entreprise = Entreprise::create([
            'name' => 'Société ' . $slug,
            'slug' => $slug,
            'is_active' => true,
            'settings' => ['locale' => 'fr', 'currency' => $currency, 'currency_symbol' => $currency === 'EUR' ? '€' : 'FCFA'],
        ]);

        $taxes = app(TaxService::class);

        foreach ($taxes->defaultRatesFor($currency) as $rate) {
            TaxRate::withoutGlobalScope('entreprise')->create($rate + ['entreprise_id' => $entreprise->id]);
        }

        app(LedgerService::class)->ensureChartOfAccounts($entreprise->id);

        return $entreprise;
    }

    protected function makeAdmin(Entreprise $entreprise): User
    {
        $role = Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrateur']);

        return User::create([
            'name' => 'Admin ' . $entreprise->slug,
            'email' => 'admin@' . $entreprise->slug . '.test',
            'password' => Hash::make('password'),
            'entreprise_id' => $entreprise->id,
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    /** Taux d'une entreprise, retrouve par son code. */
    protected function rate(Entreprise $entreprise, string $code): TaxRate
    {
        return TaxRate::withoutGlobalScope('entreprise')
            ->where('entreprise_id', $entreprise->id)->where('code', $code)->firstOrFail();
    }

    protected function ledgerAccount(Entreprise $entreprise, string $role): LedgerAccount
    {
        return LedgerAccount::withoutGlobalScope('entreprise')
            ->where('entreprise_id', $entreprise->id)->where('role', $role)->firstOrFail();
    }
}
