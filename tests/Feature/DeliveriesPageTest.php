<?php

namespace Tests\Feature;

use App\Models\CommercialClient;
use App\Models\CommercialDelivery;
use App\Models\CommercialInvoice;
use App\Models\CommercialOrder;
use App\Models\CommercialQuote;
use App\Models\Entreprise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Bons de livraison : état réel de chaque livraison et refus lisibles.
 */
class DeliveriesPageTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private function makeDelivery(Entreprise $entreprise, array $lines, string $status = 'pending_validation', string $type = 'partial', bool $invoiced = false): CommercialDelivery
    {
        $client = CommercialClient::withoutGlobalScope('entreprise')->firstOrCreate(
            ['entreprise_id' => $entreprise->id, 'name' => 'Orange Côte d’Ivoire'], ['phone' => '01', 'email' => 'achats@orange.ci']
        );
        $base = ['entreprise_id' => $entreprise->id, 'client_name' => $client->name, 'lines' => $lines];
        $quote = CommercialQuote::withoutGlobalScope('entreprise')->create($base + ['client_id' => $client->id, 'quote_date' => now()->toDateString(), 'total_ht' => 500000, 'total_ttc' => 590000, 'status' => 'validated']);
        $order = CommercialOrder::withoutGlobalScope('entreprise')->create($base + ['quote_id' => $quote->id, 'customer_order_code' => 'BC-' . $quote->id, 'total_ttc' => 590000, 'status' => 'pending_delivery']);
        $delivery = CommercialDelivery::withoutGlobalScope('entreprise')->create($base + ['order_id' => $order->id, 'delivery_type' => $type, 'status' => $status]);
        if ($invoiced) {
            CommercialInvoice::withoutGlobalScope('entreprise')->create([
                'entreprise_id' => $entreprise->id, 'delivery_id' => $delivery->id, 'client_name' => $client->name,
                'amount' => 590000, 'total_ht' => 500000, 'paid_amount' => 0, 'status' => 'unpaid',
            ]);
        }

        return $delivery;
    }

    public function test_chaque_livraison_affiche_son_etat_reel(): void
    {
        $alpha = $this->makeCompany('alpha');
        $service = [['item_type' => 'service', 'item_name' => 'Audit', 'quantity' => 1, 'unit_price' => 500000]];
        $this->makeDelivery($alpha, $service);
        $invoiced = $this->makeDelivery($alpha, $service, 'validated', 'complete', true);
        $this->makeDelivery($alpha, $service, 'validated', 'partial', false);

        $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.commercial.module', 'livraisons'))
            ->assertOk()
            ->assertSee('Bons de livraison')
            ->assertSee('À livrer (1)')
            ->assertSee('Facturées (1)')
            ->assertSee('Partielles (1)')
            ->assertSee(route('admin.commercial.invoices.print', $invoiced->invoice), false)
            ->assertSee('Partielle, à facturer')
            ->assertSee('Facturer');
    }

    public function test_un_article_sans_stock_est_refuse_avec_un_message_lisible(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $delivery = $this->makeDelivery($alpha, [['item_type' => 'article', 'item_name' => 'Imprimante laser', 'quantity' => 1, 'unit_price' => 500000]]);

        $this->actingAs($admin)->from(route('admin.commercial.module', 'livraisons'))
            ->post(route('admin.commercial.deliveries.validate', $delivery), ['delivery_type' => 'complete'])
            ->assertRedirect(route('admin.commercial.module', 'livraisons'))
            ->assertSessionHasErrors('delivery');

        $this->assertSame('pending_validation', $delivery->fresh()->status);
        $this->assertFalse(CommercialInvoice::withoutGlobalScope('entreprise')->where('delivery_id', $delivery->id)->exists());
        $this->actingAs($admin)->get(route('admin.commercial.module', 'livraisons'))->assertSee('Stock insuffisant ou article introuvable : Imprimante laser');
    }

    public function test_la_livraison_partielle_n_est_plus_proposee(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $delivery = $this->makeDelivery($alpha, [['item_type' => 'service', 'item_name' => 'Audit', 'quantity' => 1, 'unit_price' => 500000]]);

        $this->actingAs($admin)->get(route('admin.commercial.module', 'livraisons'))
            ->assertDontSee('value="partial"', false)
            ->assertDontSee('Partielles (');
        $this->actingAs($admin)->post(route('admin.commercial.deliveries.validate', $delivery), ['delivery_type' => 'partial'])
            ->assertSessionHasErrors('delivery_type');
        $this->assertSame('pending_validation', $delivery->fresh()->status);
    }

    public function test_une_ancienne_livraison_partielle_se_termine_sans_ressortir_le_stock(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $delivery = $this->makeDelivery($alpha, [['item_type' => 'article', 'item_name' => 'Classeurs', 'quantity' => 20, 'unit_price' => 1500]], 'validated', 'partial');
        // Le stock est déjà sorti lors de la validation partielle.
        \App\Models\StockExit::withoutGlobalScope('entreprise')->create(['entreprise_id' => $alpha->id, 'delivery_id' => $delivery->id, 'exit_date' => now()->toDateString(), 'total_value' => 30000]);

        $this->actingAs($admin)->get(route('admin.commercial.module', 'livraisons'))->assertSee('Facturer');
        $this->actingAs($admin)->post(route('admin.commercial.deliveries.validate', $delivery), ['delivery_type' => 'complete'])->assertSessionHasNoErrors();

        $this->assertSame('complete', $delivery->fresh()->delivery_type);
        $this->assertSame('delivered', $delivery->fresh()->order->status);
        $this->assertTrue(CommercialInvoice::withoutGlobalScope('entreprise')->where('delivery_id', $delivery->id)->exists());
        $this->assertSame(1, \App\Models\StockExit::withoutGlobalScope('entreprise')->where('delivery_id', $delivery->id)->count(), 'Aucune nouvelle sortie de stock.');

        // Une livraison terminée ne se valide plus.
        $this->actingAs($admin)->post(route('admin.commercial.deliveries.validate', $delivery), ['delivery_type' => 'complete'])->assertSessionHasErrors('delivery');
        $this->assertSame(1, CommercialInvoice::withoutGlobalScope('entreprise')->where('delivery_id', $delivery->id)->count());
    }
}
