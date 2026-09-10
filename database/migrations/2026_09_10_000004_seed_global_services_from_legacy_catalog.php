<?php

use App\Models\CommercialService;
use App\Models\Entreprise;
use Illuminate\Database\Migrations\Migration;

/**
 * Reprend en base le catalogue qui etait ecrit dans le controleur, pour que
 * les entreprises qui l'utilisaient gardent leurs prestations. Elles peuvent
 * desormais les modifier, les desactiver ou les remplacer.
 */
return new class extends Migration
{
    private array $catalog = [
        ['code' => 'mise_en_page', 'name' => 'Mise en page professionnelle', 'price' => 50000],
        ['code' => 'conception_couverture', 'name' => 'Conception de couverture', 'price' => 30000],
        ['code' => 'correction_orthographe', 'name' => 'Correction (orthographe et grammaire)', 'price' => 20000],
        ['code' => 'isbn', 'name' => 'Numéro ISBN', 'price' => 15000],
        ['code' => 'depot_legal', 'name' => 'Dépôt légal aux Archives nationales', 'price' => 25000],
    ];

    public function up(): void
    {
        Entreprise::query()->each(function (Entreprise $entreprise) {
            foreach ($this->catalog as $service) {
                CommercialService::withoutGlobalScope('entreprise')->updateOrCreate(
                    ['entreprise_id' => $entreprise->id, 'code' => $service['code']],
                    $service + [
                        'entreprise_id' => $entreprise->id,
                        'unit' => 'forfait',
                        'is_active' => true,
                        'is_global' => true,
                    ]
                );
            }
        });
    }

    public function down(): void
    {
        CommercialService::withoutGlobalScope('entreprise')
            ->whereIn('code', array_column($this->catalog, 'code'))->delete();
    }
};
