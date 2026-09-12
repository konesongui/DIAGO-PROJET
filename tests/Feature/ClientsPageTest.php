<?php

namespace Tests\Feature;

use App\Models\CommercialClient;
use App\Models\CommercialDelivery;
use App\Models\CommercialInvoice;
use App\Models\CommercialOrder;
use App\Models\CommercialQuote;
use App\Models\Entreprise;
use App\Models\PosSale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Portefeuille clients : achats cumulés, solde impayé, fiche et saisie.
 */
class ClientsPageTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private function makeClient(Entreprise $entreprise, string $name, ?string $ncc = null): CommercialClient
    {
        return CommercialClient::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'name' => $name, 'phone' => '0102030405', 'email' => 'contact@client.ci', 'tax_id' => $ncc,
        ]);
    }

    /** Facture de vente rattachée au client par son devis d'origine. */
    private function makeInvoice(CommercialClient $client, float $amount, float $paid, string $status, string $date): CommercialInvoice
    {
        $lines = [['item_name' => 'Prestation', 'quantity' => 1, 'unit_price' => $amount]];
        $base = ['entreprise_id' => $client->entreprise_id, 'client_name' => $client->name, 'lines' => $lines];
        $quote = CommercialQuote::withoutGlobalScope('entreprise')->create($base + ['client_id' => $client->id, 'quote_date' => $date, 'total_ht' => $amount, 'total_ttc' => $amount, 'status' => 'validated']);
        $order = CommercialOrder::withoutGlobalScope('entreprise')->create($base + ['quote_id' => $quote->id, 'total_ttc' => $amount, 'status' => 'delivered']);
        $delivery = CommercialDelivery::withoutGlobalScope('entreprise')->create($base + ['order_id' => $order->id, 'delivery_type' => 'complete', 'status' => 'validated']);

        return CommercialInvoice::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $client->entreprise_id, 'delivery_id' => $delivery->id, 'client_name' => $client->name,
            'amount' => $amount, 'total_ht' => $amount, 'paid_amount' => $paid, 'status' => $status, 'issued_at' => $date,
        ]);
    }

    public function test_les_cumuls_excluent_les_factures_annulees_et_comptent_les_ventes_comptoir(): void
    {
        $alpha = $this->makeCompany('alpha');
        $client = $this->makeClient($alpha, 'Orange Côte d’Ivoire', '9876543 B');
        $this->makeInvoice($client, 1000000, 400000, 'partially_paid', '2026-08-14');
        $this->makeInvoice($client, 500000, 500000, 'paid', '2026-06-01');
        $this->makeInvoice($client, 900000, 0, 'cancelled', '2026-09-01');
        PosSale::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'client_id' => $client->id, 'client_name' => $client->name, 'lines' => [],
            'total' => 150000, 'paid_amount' => 150000, 'change_amount' => 0, 'payment_method' => 'cash', 'status' => 'completed',
        ])->forceFill(['created_at' => '2026-05-02 10:00:00'])->save();

        $response = $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.commercial.module', 'clients'))
            ->assertOk()
            ->assertSee('Orange Côte d’Ivoire')
            ->assertSee('NCC 9876543 B');

        $activity = $response->viewData('activity')[$client->id];
        $this->assertSame(1650000.0, $activity['purchases'], 'Factures non annulées et vente comptoir.');
        $this->assertSame(600000.0, $activity['due']);
        $this->assertSame(1, $activity['pos_count']);
        $this->assertCount(3, $activity['invoices'], 'La fiche liste aussi la facture annulée.');
        $this->assertSame('2026-08-14', substr($activity['last'], 0, 10), 'La facture annulée ne compte pas comme dernier achat.');
    }

    public function test_les_champs_facultatifs_ne_sont_pas_obligatoires(): void
    {
        $alpha = $this->makeCompany('alpha');
        $client = $this->makeClient($alpha, 'SOTRA Abidjan', '4561237 C');
        $admin = $this->makeAdmin($alpha);

        $html = $this->actingAs($admin)->get(route('admin.commercial.module', 'clients'))->getContent();
        $this->assertDoesNotMatchRegularExpression('/name="city"[^>]*required/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="tax_id"[^>]*required/', $html);

        $this->actingAs($admin)
            ->put(route('admin.commercial.clients.update', $client), ['name' => 'SOTRA', 'phone' => '0102030405', 'email' => 'achats@sotra.ci', 'city' => '', 'tax_id' => '', 'tax_regime' => '', 'address' => ''])
            ->assertSessionHasNoErrors();
        $this->assertSame('SOTRA', $client->fresh()->name);
        $this->assertNull($client->fresh()->tax_id);
    }

    public function test_une_entreprise_ne_voit_pas_les_clients_d_une_autre(): void
    {
        $alpha = $this->makeCompany('alpha');
        $beta = $this->makeCompany('beta');
        $this->makeClient($beta, 'Client de Beta');

        $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.commercial.module', 'clients'))
            ->assertOk()
            ->assertDontSee('Client de Beta')
            ->assertSee('Aucun client enregistré');
    }
}
