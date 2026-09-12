<?php

namespace Tests\Feature;

use App\Models\CommercialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Catalogue des services : forfaits de la facture personnalisée gérés depuis
 * l'écran, code stable, désactivation.
 */
class ServicesPageTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    public function test_un_forfait_cree_depuis_l_ecran_recoit_un_code_et_apparait_sur_la_facture_personnalisee(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);

        $this->actingAs($admin)
            ->post(route('admin.commercial.services.store'), ['name' => 'Conception de couverture', 'price' => 30000, 'is_global' => 1])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)
            ->post(route('admin.commercial.services.store'), ['name' => 'Conception de couverture', 'price' => 45000, 'is_global' => 1]);
        $this->actingAs($admin)
            ->post(route('admin.commercial.services.store'), ['name' => 'Audit', 'price' => 900000, 'is_global' => 0]);

        $codes = CommercialService::withoutGlobalScope('entreprise')->where('entreprise_id', $alpha->id)->orderBy('id')->pluck('code')->all();
        $this->assertSame(['conception_de_couverture', 'conception_de_couverture_2', null], array_slice($codes, -3), 'Code unique pour les forfaits, aucun pour un service simple.');

        $catalog = $this->actingAs($admin)->get(route('admin.commercial.custom-invoice.create'))->assertOk()->viewData('globalServiceCatalog');
        $this->assertSame(30000.0, $catalog['conception_de_couverture']['price']);
        $this->assertArrayNotHasKey('audit', $catalog);
    }

    public function test_le_code_d_un_forfait_ne_change_plus_et_un_service_inactif_sort_du_catalogue(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $service = CommercialService::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'code' => 'isbn', 'name' => 'Numéro ISBN', 'price' => 15000, 'is_active' => true, 'is_global' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.commercial.services.update', $service), ['name' => 'Numéro ISBN international', 'price' => 20000, 'is_global' => 1, 'is_active' => 0])
            ->assertSessionHasNoErrors();

        $service->refresh();
        $this->assertSame('isbn', $service->code, 'Les factures émises référencent ce code.');
        $this->assertFalse($service->is_active);
        $catalog = $this->actingAs($admin)->get(route('admin.commercial.custom-invoice.create'))->viewData('globalServiceCatalog');
        $this->assertArrayNotHasKey('isbn', $catalog);
    }

    public function test_la_liste_affiche_le_prix_dans_la_devise_de_l_entreprise(): void
    {
        $alpha = $this->makeCompany('alpha');
        CommercialService::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'name' => 'Accompagnement fiscal', 'price' => 450000, 'unit' => 'mois', 'is_active' => true,
        ]);

        $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.commercial.module', 'services'))
            ->assertOk()
            ->assertSee('Accompagnement fiscal')
            ->assertSee(money(450000))
            ->assertDontSee('450 000 XOF');
    }

    public function test_en_caisse_le_prix_ttc_retombe_sur_le_prix_ht_du_catalogue(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $service = CommercialService::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'name' => 'Audit', 'price' => 100000, 'is_active' => true,
        ]);
        $cash = \App\Models\CashAccount::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'name' => 'Caisse', 'balance' => 0, 'is_active' => true,
        ]);

        // L'écran propose le prix TTC (100 000 HT + 18 %) ; le serveur en extrait la taxe.
        $this->actingAs($admin)->get(route('admin.commercial.pos.create'))->assertOk()->assertSee('data-price-ht="100000"', false);
        $this->actingAs($admin)->post(route('admin.commercial.pos.store'), [
            'lines' => [['service_id' => $service->id, 'item_name' => 'Audit', 'quantity' => 1, 'unit_price' => 118000]],
            'payment_method' => 'cash', 'cash_account_id' => $cash->id, 'paid_amount' => 118000,
        ])->assertSessionHasNoErrors();

        $sale = \App\Models\PosSale::withoutGlobalScope('entreprise')->where('entreprise_id', $alpha->id)->firstOrFail();
        $this->assertSame(118000.0, (float) $sale->total);
        $this->assertSame(100000.0, (float) $sale->total_ht);
        $this->assertSame(18000.0, (float) $sale->tax_amount);
    }
}
