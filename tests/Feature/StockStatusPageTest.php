<?php

namespace Tests\Feature;

use App\Models\CommercialSupplier;
use App\Models\Entreprise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * État du stock : disponible et valeur par article, lots avec leur
 * fournisseur, sorties regroupées, état (en stock, faible, rupture).
 */
class StockStatusPageTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private function receive(Entreprise $entreprise, string $supplierName, string $date, array $lines, ?string $reference = null): void
    {
        $supplier = CommercialSupplier::withoutGlobalScope('entreprise')->firstOrCreate(
            ['entreprise_id' => $entreprise->id, 'name' => $supplierName],
            ['responsible_name' => 'R', 'tax_id' => uniqid(), 'phone' => '01', 'email' => 'f@f.ci', 'address' => 'Abidjan'],
        );
        $this->post(route('admin.commercial.stock-entries.store'), ['supplier_id' => $supplier->id, 'supplier_reference' => $reference, 'entry_date' => $date, 'lines' => $lines])
            ->assertSessionHasNoErrors();
    }

    private function details($response): array
    {
        preg_match('#<script type="application/json" id="articleDetails">(.*?)</script>#s', $response->getContent(), $match);

        return json_decode($match[1] ?? '', true);
    }

    public function test_chaque_article_montre_son_disponible_sa_valeur_ses_lots_et_ses_fournisseurs(): void
    {
        $alpha = $this->makeCompany('alpha');
        $this->actingAs($this->makeAdmin($alpha));
        $ramette = ['designation' => 'Ramette papier A4', 'article' => 'Papeterie', 'unit' => 'ramette'];
        $this->receive($alpha, 'Ivoire Bureau', '2026-08-01', [$ramette + ['quantity' => 50, 'purchase_price' => 2800, 'profit_per_unit' => 700]], 'BL-IB-2207');
        $this->receive($alpha, 'MacStore Abidjan', '2026-08-20', [$ramette + ['quantity' => 30, 'purchase_price' => 2900, 'profit_per_unit' => 600]]);
        $this->post(route('admin.commercial.stock-exits.store'), ['exit_date' => '2026-09-01', 'reason' => 'internal_use',
            'lines' => [['article_key' => 'ramette papier a4|papeterie|ramette', 'quantity' => 60]]])->assertSessionHasNoErrors();

        $response = $this->get(route('admin.commercial.module', 'etat-stock'))->assertOk();
        // Il reste 20 ramettes du second lot, valorisées à son prix (2 900) : 58 000.
        // L'ancien écran affichait les quantités comme des montants (« 80,000 FCFA »).
        $response->assertSeeInOrder(['Ramette papier A4', 'Ivoire Bureau', 'et 1 autre(s)', '80 / 60', '20 ramette', 'En stock', money(58000), money(3500)])
            ->assertDontSee('80,000');

        $item = $this->details($response)['ramette papier a4|papeterie|ramette'];
        $this->assertSame(money(12000), $item['profit']);
        $this->assertSame(['MacStore Abidjan', 'Ivoire Bureau'], array_column($item['lots'], 'supplier'));
        $this->assertSame('BL BL-IB-2207', $item['lots'][1]['reference']);
        // Une sortie qui a entamé deux lots n'apparaît qu'une fois, avec sa quantité totale.
        $this->assertSame([['date' => '01/09/2026', 'destination' => 'Usage interne', 'quantity' => '60']], $item['exits']);
    }

    public function test_les_articles_faibles_et_en_rupture_sont_signales(): void
    {
        $alpha = $this->makeCompany('alpha');
        $this->actingAs($this->makeAdmin($alpha));
        $this->receive($alpha, 'MacStore Abidjan', '2026-08-20', [
            ['designation' => 'Registre du personnel', 'unit' => 'pièce', 'quantity' => 5, 'purchase_price' => 15000],
            ['designation' => 'Chemise cartonnée', 'unit' => 'paquet', 'quantity' => 4, 'purchase_price' => 4500],
            ['designation' => 'Clé USB 32 Go', 'unit' => 'pièce', 'quantity' => 25, 'purchase_price' => 3500],
        ]);
        $this->post(route('admin.commercial.stock-exits.store'), ['exit_date' => '2026-09-01', 'reason' => 'sale',
            'lines' => [['article_key' => 'chemise cartonnée||paquet', 'quantity' => 4]]])->assertSessionHasNoErrors();

        $this->get(route('admin.commercial.module', 'etat-stock'))
            ->assertOk()
            ->assertSee('En stock (1)')
            ->assertSee('Stock faible (1)')
            ->assertSee('En rupture (1)')
            ->assertSee('1 en rupture, 1 à 5 ou moins');
    }

    public function test_le_stock_d_une_autre_entreprise_reste_invisible(): void
    {
        $beta = $this->makeCompany('beta');
        $this->actingAs($this->makeAdmin($beta));
        $this->receive($beta, 'Fournisseur beta', '2026-08-01', [['designation' => 'Article confidentiel beta', 'quantity' => 3, 'purchase_price' => 1000]]);

        $this->actingAs($this->makeAdmin($this->makeCompany('alpha')))
            ->get(route('admin.commercial.module', 'etat-stock'))
            ->assertOk()
            ->assertDontSee('Article confidentiel beta')
            ->assertSee('Aucun article en stock');
    }
}
