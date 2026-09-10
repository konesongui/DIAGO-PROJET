<?php

namespace Database\Seeders;

use App\Models\Entreprise;
use App\Models\TaxRate;
use App\Services\LedgerService;
use App\Services\TaxService;
use Illuminate\Database\Seeder;

class TaxRateSeeder extends Seeder
{
    public function run(): void
    {
        $taxService = app(TaxService::class);

        $ledger = app(LedgerService::class);

        Entreprise::query()->each(function (Entreprise $entreprise) use ($taxService, $ledger) {
            // Chaque entreprise recoit son plan comptable en meme temps que ses taux.
            $ledger->ensureChartOfAccounts($entreprise->id);

            $currency = $taxService->currencyFor($entreprise);

            foreach ($taxService->defaultRatesFor($currency) as $rate) {
                TaxRate::withoutGlobalScope('entreprise')->updateOrCreate(
                    ['entreprise_id' => $entreprise->id, 'name' => $rate['name']],
                    $rate + ['entreprise_id' => $entreprise->id]
                );
            }
        });
    }
}
