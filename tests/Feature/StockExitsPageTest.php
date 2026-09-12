<?php

namespace Tests\Feature;

use App\Models\CommercialClient;
use App\Models\CommercialDelivery;
use App\Models\CommercialOrder;
use App\Models\CommercialQuote;
use App\Models\CommercialSupplier;
use App\Models\Entreprise;
use App\Models\StockExit;
use App\Models\StockExitLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Sorties de stock : par article, du lot le plus ancien au plus récent, avec
 * un motif, et chaque quantité sortie reliée à la réception et au fournisseur.
 */
class StockExitsPageTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private const RAMETTE = 'ramette papier a4|papeterie|ramette';

    /** Deux réceptions de ramettes : 50 à 2 800 chez Ivoire Bureau, puis 30 à 2 900 chez MacStore. */
    private function stockUp(Entreprise $entreprise): void
    {
        foreach ([['Ivoire Bureau', '2026-08-01', 50, 2800], ['MacStore Abidjan', '2026-08-20', 30, 2900]] as [$name, $date, $quantity, $price]) {
            $supplier = CommercialSupplier::withoutGlobalScope('entreprise')->create([
                'entreprise_id' => $entreprise->id, 'name' => $name, 'responsible_name' => 'R', 'tax_id' => uniqid(), 'phone' => '01', 'email' => 'f@f.ci', 'address' => 'Abidjan',
            ]);
            $this->post(route('admin.commercial.stock-entries.store'), ['supplier_id' => $supplier->id, 'entry_date' => $date, 'lines' => [
                ['designation' => 'Ramette papier A4', 'article' => 'Papeterie', 'unit' => 'ramette', 'quantity' => $quantity, 'purchase_price' => $price, 'profit_per_unit' => 500],
            ]])->assertSessionHasNoErrors();
        }
    }

    public function test_une_sortie_puise_dans_les_lots_les_plus_anciens_et_garde_le_fournisseur(): void
    {
        $alpha = $this->makeCompany('alpha');
        $this->actingAs($this->makeAdmin($alpha));
        $this->stockUp($alpha);

        // L'ancien écran sortait lot par lot : 60 ramettes réparties sur deux réceptions étaient impossibles en une ligne.
        $this->post(route('admin.commercial.stock-exits.store'), ['exit_date' => '2026-09-01', 'reason' => 'internal_use', 'note' => 'Service comptable',
            'lines' => [['article_key' => self::RAMETTE, 'quantity' => 60]]])->assertSessionHasNoErrors();

        $exit = StockExit::withoutGlobalScope('entreprise')->with('lines.entryLine.stockEntry')->firstOrFail();
        $this->assertSame('internal_use', $exit->reason);
        $this->assertSame(169000.0, (float) $exit->total_value);
        $this->assertSame([[50.0, 2800.0, 'Ivoire Bureau'], [10.0, 2900.0, 'MacStore Abidjan']],
            $exit->lines->map(fn ($line) => [(float) $line->quantity, (float) $line->unit_price, $line->entryLine->stockEntry->supplier_name])->all());

        $response = $this->get(route('admin.commercial.module', 'sorties-stock'))->assertOk()->assertSee('Usage interne')->assertSee(money(169000));
        // Le détail relie chaque quantité sortie à sa réception et à son fournisseur.
        preg_match('#<script type="application/json" id="exitDetails">(.*?)</script>#s', $response->getContent(), $match);
        $lines = json_decode($match[1] ?? '', true)[$exit->id]['lines'];
        $this->assertSame(['Réception du 01/08/2026 · Ivoire Bureau', 'Réception du 20/08/2026 · MacStore Abidjan'], array_column($lines, 'lot'));
    }

    public function test_une_sortie_exige_un_motif_et_refuse_lisiblement_un_stock_insuffisant(): void
    {
        $alpha = $this->makeCompany('alpha');
        $this->actingAs($this->makeAdmin($alpha));
        $this->stockUp($alpha);

        $this->post(route('admin.commercial.stock-exits.store'), ['exit_date' => '2026-09-01', 'lines' => [['article_key' => self::RAMETTE, 'quantity' => 5]]])
            ->assertSessionHasErrors(['reason' => 'Indiquez le motif de la sortie : où part la marchandise ?']);

        // Deux lignes du même article comptent ensemble : 70 + 20 dépassent les 80 disponibles.
        // C'était une page d'erreur 422, et la saisie était perdue.
        $this->from(route('admin.commercial.stock-exits.create'))->post(route('admin.commercial.stock-exits.store'), ['exit_date' => '2026-09-01', 'reason' => 'gift',
            'lines' => [['article_key' => self::RAMETTE, 'quantity' => 70], ['article_key' => self::RAMETTE, 'quantity' => 20]]])
            ->assertRedirect(route('admin.commercial.stock-exits.create'))
            ->assertSessionHasErrors(['lines.1.quantity' => 'Stock insuffisant pour Ramette papier A4 : 10 disponible(s), 20 demandé(s).']);

        $this->assertSame(0, StockExit::withoutGlobalScope('entreprise')->count());
    }

    public function test_une_livraison_couvre_une_quantite_repartie_sur_plusieurs_receptions(): void
    {
        $alpha = $this->makeCompany('alpha');
        $this->actingAs($this->makeAdmin($alpha));
        $this->stockUp($alpha);
        $client = CommercialClient::withoutGlobalScope('entreprise')->create(['entreprise_id' => $alpha->id, 'name' => 'Orange Côte d’Ivoire']);
        $base = ['entreprise_id' => $alpha->id, 'client_name' => $client->name, 'lines' => [['item_type' => 'article', 'item_name' => 'Ramette papier A4', 'quantity' => 70, 'unit_price' => 4000]]];
        $quote = CommercialQuote::withoutGlobalScope('entreprise')->create($base + ['client_id' => $client->id, 'quote_date' => '2026-09-01', 'total_ht' => 280000, 'total_ttc' => 330400, 'status' => 'validated']);
        $order = CommercialOrder::withoutGlobalScope('entreprise')->create($base + ['quote_id' => $quote->id, 'customer_order_code' => 'BC-2026-131', 'total_ttc' => 330400, 'status' => 'pending_delivery']);
        $delivery = CommercialDelivery::withoutGlobalScope('entreprise')->create($base + ['order_id' => $order->id, 'delivery_type' => 'complete', 'status' => 'pending_validation']);

        // Avant : aucun lot ne contenait 70 ramettes à lui seul, la livraison était refusée alors que le stock suffisait.
        $this->post(route('admin.commercial.deliveries.validate', $delivery), ['delivery_type' => 'complete'])->assertSessionHasNoErrors();

        $lines = StockExitLine::withoutGlobalScope('entreprise')->orderBy('id')->get();
        $this->assertSame([50.0, 20.0], $lines->map(fn ($line) => (float) $line->quantity)->all());
        $this->get(route('admin.commercial.module', 'sorties-stock'))->assertSee('Livraison à Orange Côte d’Ivoire')->assertSee('BC BC-2026-131');
    }

    public function test_le_stock_d_une_autre_entreprise_ne_peut_pas_sortir(): void
    {
        $beta = $this->makeCompany('beta');
        $this->actingAs($this->makeAdmin($beta));
        $this->stockUp($beta);

        $alpha = $this->makeCompany('alpha');
        $this->actingAs($this->makeAdmin($alpha))->post(route('admin.commercial.stock-exits.store'), ['exit_date' => '2026-09-01', 'reason' => 'other',
            'lines' => [['article_key' => self::RAMETTE, 'quantity' => 1]]])->assertSessionHasErrors('lines.0.quantity');
        $this->assertSame(0, StockExit::withoutGlobalScope('entreprise')->count());
    }
}
