<?php

namespace Tests\Feature;

use App\Models\FixedAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Registre des immobilisations : amortissement linéaire, modification et
 * cloisonnement par entreprise.
 */
class FixedAssetTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private function makeAsset(int $entrepriseId, array $attributes = []): FixedAsset
    {
        return FixedAsset::withoutGlobalScope('entreprise')->create($attributes + [
            'entreprise_id' => $entrepriseId,
            'name' => 'Véhicule de livraison',
            'asset_category' => 'Véhicule',
            'acquisition_date' => now()->subYears(2)->toDateString(),
            'acquisition_value' => 1200000,
            'residual_value' => 0,
            'useful_life_years' => 4,
            'status' => 'active',
        ]);
    }

    public function test_le_registre_vide_affiche_un_etat_vide(): void
    {
        $alpha = $this->makeCompany('alpha');

        $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.comptabilite.fixedAssets'))
            ->assertOk()
            ->assertSee('Aucune immobilisation enregistrée');
    }

    public function test_l_amortissement_suit_les_mois_ecoules(): void
    {
        $alpha = $this->makeCompany('alpha');
        $this->makeAsset($alpha->id);

        $response = $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.comptabilite.fixedAssets'))
            ->assertOk()
            ->assertSee('Véhicule de livraison')
            ->assertSee('50 % amorti');

        $asset = $response->viewData('assets')->first();
        $this->assertEqualsWithDelta(600000, $asset->depreciation_amount, 0.01, 'Deux ans sur quatre : moitié amortie.');
        $this->assertEqualsWithDelta(600000, $response->viewData('summary')['net'], 0.01);
    }

    public function test_une_acquisition_future_n_est_pas_amortie(): void
    {
        $alpha = $this->makeCompany('alpha');
        $this->makeAsset($alpha->id, ['acquisition_date' => now()->addYear()->toDateString()]);

        $asset = $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.comptabilite.fixedAssets'))
            ->viewData('assets')->first();

        $this->assertSame(0.0, (float) $asset->depreciation_amount);
        $this->assertSame(1200000.0, (float) $asset->net_value);
    }

    public function test_une_immobilisation_peut_etre_modifiee(): void
    {
        $alpha = $this->makeCompany('alpha');
        $asset = $this->makeAsset($alpha->id);

        $this->actingAs($this->makeAdmin($alpha))
            ->put(route('admin.comptabilite.fixedAssets.update', $asset), [
                'name' => 'Camionnette', 'asset_category' => 'Véhicule', 'acquisition_date' => $asset->acquisition_date->toDateString(),
                'acquisition_value' => 1500000, 'residual_value' => 100000, 'useful_life_years' => 5, 'status' => 'sold',
            ])
            ->assertRedirect(route('admin.comptabilite.fixedAssets'));

        $asset->refresh();
        $this->assertSame('Camionnette', $asset->name);
        $this->assertSame('sold', $asset->status);
        $this->assertSame(1500000.0, (float) $asset->acquisition_value);
    }

    public function test_une_entreprise_ne_modifie_pas_les_immobilisations_d_une_autre(): void
    {
        $alpha = $this->makeCompany('alpha');
        $beta = $this->makeCompany('beta');
        $asset = $this->makeAsset($beta->id);

        $response = $this->actingAs($this->makeAdmin($alpha))
            ->put(route('admin.comptabilite.fixedAssets.update', $asset), [
                'name' => 'Détourné', 'asset_category' => 'Autre', 'acquisition_date' => now()->toDateString(),
                'acquisition_value' => 1, 'useful_life_years' => 1, 'status' => 'active',
            ]);

        $this->assertContains($response->status(), [403, 404]);
        $this->assertSame('Véhicule de livraison', FixedAsset::withoutGlobalScope('entreprise')->find($asset->id)->name);
    }
}
