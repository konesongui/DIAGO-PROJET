<?php

namespace Tests\Feature;

use App\Models\CommercialClient;
use App\Models\CommercialInvoice;
use App\Models\CommercialOrder;
use App\Models\CommercialQuote;
use App\Models\Entreprise;
use App\Services\DocumentNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Bons de commande : nés de la validation d'un devis, suivis jusqu'à la
 * livraison et à la facture, et imprimables.
 */
class OrdersPageTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-12 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Devis en attente, avec des prestations (non stockées). */
    private function quote(Entreprise $entreprise, string $clientName, array $lines, string $subject = 'Mission d’audit'): CommercialQuote
    {
        $client = CommercialClient::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'name' => $clientName, 'responsible_name' => 'M. Kouamé',
            'phone' => '+225 27 20 20 10 10', 'email' => 'achats@' . uniqid() . '.ci', 'city' => 'Abidjan',
            'tax_id' => '8801234 B', 'address' => 'Plateau, Abidjan',
        ]);
        $ht = collect($lines)->sum(fn ($line) => $line['quantity'] * $line['unit_price']);

        return CommercialQuote::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'client_id' => $client->id, 'client_name' => $clientName,
            'reference' => app(DocumentNumberService::class)->next($entreprise->id, 'quote'),
            'quote_date' => '2026-08-20', 'due_date' => '2026-10-20', 'subject' => $subject,
            'payment_method' => 'Virement', 'payment_terms' => '30 jours net à réception de facture',
            'delivery_terms' => 'Sous 15 jours ouvrés', 'delivery_location' => 'Plateau, Abidjan',
            'lines' => $lines, 'total_ht' => $ht, 'total_discount' => 0, 'net_ht' => $ht,
            'tax_rate' => 18, 'tax_regime' => 'standard', 'tax_amount' => round($ht * 0.18, 2), 'total_ttc' => round($ht * 1.18, 2),
            'currency' => 'XOF', 'status' => 'pending_validation',
        ]);
    }

    private function service(string $name, float $quantity, float $price): array
    {
        return ['item_type' => 'service', 'item_name' => $name, 'quantity' => $quantity, 'unit' => 'forfait', 'unit_price' => $price];
    }

    public function test_la_validation_d_un_devis_cree_un_bon_de_commande_numerote(): void
    {
        $alpha = $this->makeCompany('alpha');
        $this->actingAs($this->makeAdmin($alpha));
        $quote = $this->quote($alpha, 'Société Générale CI', [$this->service('Audit des comptes annuels', 1, 4500000)]);

        $this->post(route('admin.commercial.quotes.validate', $quote), ['customer_order_code' => 'BC-CLIENT-118'])
            ->assertSessionHasNoErrors();

        $order = CommercialOrder::withoutGlobalScope('entreprise')->firstOrFail();
        // L'ancienne commande ne portait aucun numéro : seule la référence du client existait.
        $this->assertSame('BC-20260912-0001', $order->reference);
        $this->assertSame('BC-CLIENT-118', $order->customer_order_code);
        $this->assertSame(4500000.0, (float) $order->total_ht);

        $this->get(route('admin.commercial.module', 'commandes'))
            ->assertOk()
            ->assertSee('Bons de commande')
            ->assertSeeInOrder(['BC-20260912-0001', 'Société Générale CI', 'réf. client BC-CLIENT-118', $quote->reference, money(5310000), 'À livrer'])
            ->assertSee(money(4500000));
    }

    public function test_l_etat_suit_la_livraison_puis_la_facture(): void
    {
        $alpha = $this->makeCompany('alpha');
        $this->actingAs($this->makeAdmin($alpha));
        $quote = $this->quote($alpha, 'Orange Côte d’Ivoire', [$this->service('Accompagnement fiscal', 2, 1250000)]);
        $this->post(route('admin.commercial.quotes.validate', $quote), ['customer_order_code' => 'OCI-4471'])->assertSessionHasNoErrors();
        $order = CommercialOrder::withoutGlobalScope('entreprise')->firstOrFail();

        $this->get(route('admin.commercial.module', 'commandes'))->assertOk()->assertSee('À livrer (1)');

        $this->post(route('admin.commercial.deliveries.validate', $order->delivery), ['delivery_type' => 'complete'])
            ->assertSessionHasNoErrors();

        $this->get(route('admin.commercial.module', 'commandes'))
            ->assertOk()
            ->assertSee('Facturées (1)')
            ->assertSee('Facturée')
            ->assertDontSee('À livrer (1)');

        $invoice = CommercialInvoice::withoutGlobalScope('entreprise')->firstOrFail();
        $invoice->update(['paid_amount' => $invoice->amount, 'status' => 'paid']);

        $this->get(route('admin.commercial.module', 'commandes'))
            ->assertOk()
            ->assertSee('Facturée et payée')
            ->assertSee('Payées (1)');
    }

    public function test_le_bon_de_commande_imprimable_reprend_le_client_les_lignes_et_les_totaux(): void
    {
        $alpha = $this->makeCompany('alpha');
        $this->actingAs($this->makeAdmin($alpha));
        $quote = $this->quote($alpha, 'Cabinet Kouassi & Associés', [
            $this->service('Audit des comptes annuels', 1, 2000000),
            $this->service('Formation des équipes comptables', 2, 350000),
        ], 'Mission annuelle 2026');
        $this->post(route('admin.commercial.quotes.validate', $quote), ['customer_order_code' => 'CK-09'])->assertSessionHasNoErrors();
        $order = CommercialOrder::withoutGlobalScope('entreprise')->firstOrFail();

        $this->get(route('admin.commercial.orders.print', $order))
            ->assertOk()
            ->assertSee('BON DE COMMANDE')
            ->assertSeeInOrder([
                'N° BC-20260912-0001', 'Cabinet Kouassi & Associés', 'Plateau, Abidjan', 'Mission annuelle 2026',
                $quote->reference, 'CK-09', 'Audit des comptes annuels', 'Formation des équipes comptables',
                money(2700000), 'TVA (18 %)', money(486000), money(3186000),
            ])
            ->assertSee('Sous 15 jours ouvrés');
    }

    public function test_les_commandes_d_une_autre_entreprise_restent_inaccessibles(): void
    {
        $beta = $this->makeCompany('beta');
        $this->actingAs($this->makeAdmin($beta));
        $quote = $this->quote($beta, 'Client confidentiel beta', [$this->service('Mission confidentielle beta', 1, 9000000)]);
        $this->post(route('admin.commercial.quotes.validate', $quote), ['customer_order_code' => 'BETA-1'])->assertSessionHasNoErrors();
        $order = CommercialOrder::withoutGlobalScope('entreprise')->firstOrFail();

        $this->actingAs($this->makeAdmin($this->makeCompany('alpha')));
        $this->get(route('admin.commercial.module', 'commandes'))
            ->assertOk()
            ->assertDontSee('Client confidentiel beta')
            ->assertSee('Aucun bon de commande');
        $this->get(route('admin.commercial.orders.print', $order))->assertNotFound();
    }
}
