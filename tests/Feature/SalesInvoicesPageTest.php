<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\CommercialDelivery;
use App\Models\CommercialInvoice;
use App\Models\CommercialOrder;
use App\Models\CommercialQuote;
use App\Models\Entreprise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Liste des factures de ventes : indicateurs, statuts et encaissement depuis
 * la fenêtre de paiement.
 */
class SalesInvoicesPageTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    /** Facture issue d'une livraison complète (devis, commande, livraison). */
    private function makeInvoice(Entreprise $entreprise, float $amount, float $paid, string $status): CommercialInvoice
    {
        $lines = [['item_name' => 'Prestation', 'quantity' => 1, 'unit_price' => $amount]];
        $quote = CommercialQuote::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'client_name' => 'Client test', 'quote_date' => now()->toDateString(),
            'total_ht' => $amount, 'total_ttc' => $amount, 'lines' => $lines, 'status' => 'accepted',
        ]);
        $order = CommercialOrder::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'quote_id' => $quote->id, 'client_name' => 'Client test',
            'lines' => $lines, 'total_ttc' => $amount, 'status' => 'delivered',
        ]);
        $delivery = CommercialDelivery::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'order_id' => $order->id, 'client_name' => 'Client test',
            'lines' => $lines, 'delivery_type' => 'complete', 'status' => 'delivered',
        ]);

        return CommercialInvoice::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'delivery_id' => $delivery->id, 'client_name' => 'Client test',
            'amount' => $amount, 'total_ht' => $amount, 'paid_amount' => $paid, 'status' => $status, 'issued_at' => now(),
        ]);
    }

    public function test_la_liste_vide_affiche_un_etat_vide(): void
    {
        $alpha = $this->makeCompany('alpha');

        $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.commercial.module', 'factures'))
            ->assertOk()
            ->assertSee('Aucune facture de vente');
    }

    public function test_les_indicateurs_ignorent_les_factures_annulees(): void
    {
        $alpha = $this->makeCompany('alpha');
        $this->makeInvoice($alpha, 1000000, 1000000, 'paid');
        $this->makeInvoice($alpha, 500000, 200000, 'partially_paid');
        $this->makeInvoice($alpha, 300000, 0, 'unpaid');
        $this->makeInvoice($alpha, 900000, 0, 'cancelled');

        $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.commercial.module', 'factures'))
            ->assertOk()
            ->assertSee(money(1800000))   // facturé : 1 000 000 + 500 000 + 300 000
            ->assertSee(money(1200000))   // encaissé : 1 000 000 + 200 000
            ->assertSee(money(600000))    // reste : 300 000 + 300 000
            ->assertDontSee(money(2700000))
            ->assertSee('Impayées (1)')
            ->assertSee('Annulées (1)')
            ->assertSee('data-status="unpaid"', false);
    }

    public function test_un_paiement_partiel_met_a_jour_la_facture(): void
    {
        $alpha = $this->makeCompany('alpha');
        $invoice = $this->makeInvoice($alpha, 500000, 0, 'unpaid');
        $cash = CashAccount::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'name' => 'Caisse principale', 'balance' => 0, 'is_active' => true,
        ]);

        $this->actingAs($this->makeAdmin($alpha))
            ->post(route('admin.commercial.invoices.payment', $invoice), [
                'payment_invoice_id' => $invoice->id, 'paid_amount' => 200000, 'payment_method' => 'cash', 'cash_account_id' => $cash->id,
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame(200000.0, (float) $invoice->paid_amount);
        $this->assertSame('partially_paid', $invoice->status);
        $this->assertSame(200000.0, (float) $cash->fresh()->balance);
    }
}
