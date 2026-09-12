<?php

namespace Tests\Feature;

use App\Models\CommercialClient;
use App\Models\CommercialDelivery;
use App\Models\CommercialInvoice;
use App\Models\CommercialQuote;
use App\Models\CommercialService;
use App\Models\StockExit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Éditeur de devis : type et unité des lignes conservés, saisie reprise après
 * une erreur, et livraison d'un devis de services sans passer par le stock.
 */
class QuoteEditorTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private function makeClient($entreprise): CommercialClient
    {
        return CommercialClient::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'name' => 'Société Générale CI', 'phone' => '0102030405', 'email' => 'achats@sgci.ci',
        ]);
    }

    public function test_le_type_et_l_unite_des_lignes_sont_conserves(): void
    {
        $alpha = $this->makeCompany('alpha');
        $client = $this->makeClient($alpha);

        $this->actingAs($this->makeAdmin($alpha))->post(route('admin.commercial.quotes.store'), [
            'client_id' => $client->id, 'quote_date' => now()->toDateString(), 'total_discount' => 0,
            'lines' => [
                ['item_type' => 'service', 'item_name' => 'Audit', 'unit' => 'forfait', 'quantity' => 1, 'unit_price' => 1000000],
                ['item_type' => 'article', 'item_name' => 'Classeurs', 'unit' => 'pièce', 'quantity' => 10, 'unit_price' => 1500],
            ],
        ])->assertSessionHasNoErrors();

        $quote = CommercialQuote::withoutGlobalScope('entreprise')->where('entreprise_id', $alpha->id)->firstOrFail();
        $this->assertSame(['service', 'article'], array_column($quote->lines, 'item_type'));
        $this->assertSame(['forfait', 'pièce'], array_column($quote->lines, 'unit'));
        $this->assertSame(1015000.0, (float) $quote->total_ht);
    }

    public function test_apres_une_erreur_les_lignes_saisies_sont_reprises(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);

        $this->actingAs($admin)->from(route('admin.commercial.quotes.create'))->post(route('admin.commercial.quotes.store'), [
            'client_id' => '', 'quote_date' => now()->toDateString(), 'subject' => 'Proposition de formation',
            'lines' => [['item_type' => 'service', 'item_name' => 'Formation comptable', 'unit' => 'jour', 'quantity' => 3, 'unit_price' => 350000]],
        ])->assertSessionHasErrors('client_id');

        $this->actingAs($admin)->get(route('admin.commercial.quotes.create'))
            ->assertOk()
            ->assertSee('Proposition de formation')
            ->assertSee('Formation comptable');
    }

    public function test_un_devis_de_services_se_livre_et_se_facture_sans_stock(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $client = $this->makeClient($alpha);
        CommercialService::withoutGlobalScope('entreprise')->create(['entreprise_id' => $alpha->id, 'name' => 'Accompagnement fiscal', 'price' => 500000, 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.commercial.quotes.store'), [
            'client_id' => $client->id, 'quote_date' => now()->toDateString(),
            'lines' => [['item_type' => 'service', 'item_name' => 'Audit', 'unit' => 'forfait', 'quantity' => 1, 'unit_price' => 1000000]],
        ]);
        $quote = CommercialQuote::withoutGlobalScope('entreprise')->where('entreprise_id', $alpha->id)->firstOrFail();
        // Ligne ancienne, enregistrée sans type : reconnue par le catalogue des services.
        $quote->update(['lines' => array_merge($quote->lines, [['item_name' => 'Accompagnement fiscal', 'quantity' => 2, 'unit_price' => 500000]])]);

        $this->actingAs($admin)->post(route('admin.commercial.quotes.validate', $quote), ['customer_order_code' => 'BC-118'])->assertSessionHasNoErrors();
        $delivery = CommercialDelivery::withoutGlobalScope('entreprise')->where('entreprise_id', $alpha->id)->firstOrFail();

        $this->actingAs($admin)->post(route('admin.commercial.deliveries.validate', $delivery), ['delivery_type' => 'complete'])->assertSessionHasNoErrors();

        $this->assertSame('validated', $delivery->fresh()->status);
        $this->assertFalse(StockExit::withoutGlobalScope('entreprise')->where('delivery_id', $delivery->id)->exists(), 'Aucune sortie de stock pour des services.');
        $this->assertTrue(CommercialInvoice::withoutGlobalScope('entreprise')->where('delivery_id', $delivery->id)->exists(), 'La livraison complète crée la facture.');
    }

    public function test_un_article_sans_stock_bloque_toujours_la_livraison(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $client = $this->makeClient($alpha);

        $this->actingAs($admin)->post(route('admin.commercial.quotes.store'), [
            'client_id' => $client->id, 'quote_date' => now()->toDateString(),
            'lines' => [['item_type' => 'article', 'item_name' => 'Imprimante laser', 'quantity' => 1, 'unit_price' => 250000]],
        ]);
        $quote = CommercialQuote::withoutGlobalScope('entreprise')->where('entreprise_id', $alpha->id)->firstOrFail();
        $this->actingAs($admin)->post(route('admin.commercial.quotes.validate', $quote), ['customer_order_code' => 'BC-119']);
        $delivery = CommercialDelivery::withoutGlobalScope('entreprise')->where('entreprise_id', $alpha->id)->firstOrFail();

        $this->actingAs($admin)->post(route('admin.commercial.deliveries.validate', $delivery), ['delivery_type' => 'complete'])->assertSessionHasErrors('delivery');
        $this->assertNotSame('validated', $delivery->fresh()->status);
    }
}
