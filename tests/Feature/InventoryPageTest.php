<?php

namespace Tests\Feature;

use App\Models\CommercialSupplier;
use App\Models\Entreprise;
use App\Models\InventoryAudit;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Models\StockInventory;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Inventaire : les écarts validés corrigent le stock (manquant sorti des lots
 * les plus anciens, surplus entré au dernier prix), le théorique est recalculé
 * par le serveur et chaque inventaire reste dans l'historique.
 */
class InventoryPageTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private const RAMETTE = 'ramette papier a4|papeterie|ramette';
    private const USB = 'clé usb 32 go||pièce';

    private function stockUp(Entreprise $entreprise): void
    {
        foreach ([['Ivoire Bureau', '2026-08-01', 10, 2800], ['MacStore Abidjan', '2026-08-20', 30, 2900]] as [$name, $date, $quantity, $price]) {
            $supplier = CommercialSupplier::withoutGlobalScope('entreprise')->create([
                'entreprise_id' => $entreprise->id, 'name' => $name, 'responsible_name' => 'R', 'tax_id' => uniqid(), 'phone' => '01', 'email' => 'f@f.ci', 'address' => 'Abidjan',
            ]);
            $this->post(route('admin.commercial.stock-entries.store'), ['supplier_id' => $supplier->id, 'entry_date' => $date, 'lines' => [
                ['designation' => 'Ramette papier A4', 'article' => 'Papeterie', 'unit' => 'ramette', 'quantity' => $quantity, 'purchase_price' => $price, 'profit_per_unit' => 500],
                ['designation' => 'Clé USB 32 Go', 'unit' => 'pièce', 'quantity' => 5, 'purchase_price' => $price + 700, 'profit_per_unit' => 2000],
            ]])->assertSessionHasNoErrors();
        }
    }

    private function available(Entreprise $entreprise, string $key): float
    {
        return app(StockService::class)->status($entreprise->id)->firstWhere('key', $key)['available'];
    }

    public function test_un_inventaire_valide_corrige_le_stock_et_garde_ses_ecarts(): void
    {
        $alpha = $this->makeCompany('alpha');
        $this->actingAs($this->makeAdmin($alpha));
        $this->stockUp($alpha);

        // 40 ramettes attendues, 28 comptées ; 10 clés attendues, 11 comptées.
        // Le théorique envoyé par le navigateur (falsifié ici) n'est plus pris en compte.
        $this->post(route('admin.commercial.inventory.store'), ['inventoried_at' => '2026-09-10', 'note' => 'Fin de mois', 'items' => [
            ['key' => self::RAMETTE, 'counted' => 28, 'theoretical' => 999],
            ['key' => self::USB, 'counted' => 11],
        ]])->assertSessionHasNoErrors()->assertSessionHas('success');

        // Avant : l'inventaire s'enregistrait sans jamais corriger le stock.
        $this->assertSame(28.0, $this->available($alpha, self::RAMETTE));
        $this->assertSame(11.0, $this->available($alpha, self::USB));

        $inventory = StockInventory::withoutGlobalScope('entreprise')->firstOrFail();
        $this->assertSame(2, $inventory->counted_items);
        $this->assertSame(2, $inventory->variance_items);
        // 12 ramettes manquantes : les 10 du premier lot (2 800) puis 2 du second (2 900).
        $this->assertSame(33800.0, (float) $inventory->shortage_value);
        // Une clé en trop, au dernier prix d'achat (3 600).
        $this->assertSame(3600.0, (float) $inventory->surplus_value);
        $this->assertSame([40.0, -12.0], InventoryAudit::withoutGlobalScope('entreprise')->where('designation', 'Ramette papier A4')->get()
            ->flatMap(fn ($audit) => [(float) $audit->theoretical_quantity, (float) $audit->variance])->all());

        $exit = StockExit::withoutGlobalScope('entreprise')->where('stock_inventory_id', $inventory->id)->firstOrFail();
        $this->assertSame('Écart d’inventaire', $exit->originLabel());
        $entry = StockEntry::withoutGlobalScope('entreprise')->where('stock_inventory_id', $inventory->id)->firstOrFail();
        $this->assertNull($entry->supplier_id);

        $this->get(route('admin.commercial.module', 'inventaire'))->assertSee('Fin de mois')->assertSee('2 écart(s)');
        $this->get(route('admin.commercial.module', 'entrees-stock'))->assertSee('Inventaire');
    }

    public function test_un_inventaire_conforme_ne_touche_pas_au_stock(): void
    {
        $alpha = $this->makeCompany('alpha');
        $this->actingAs($this->makeAdmin($alpha));
        $this->stockUp($alpha);

        $this->post(route('admin.commercial.inventory.store'), ['inventoried_at' => '2026-09-10', 'items' => [
            ['key' => self::RAMETTE, 'counted' => 40], ['key' => self::USB, 'counted' => ''],
        ]])->assertSessionHas('success', 'Inventaire validé : 1 article(s) compté(s), aucun écart : le stock est conforme.');

        $this->assertSame(0, StockExit::withoutGlobalScope('entreprise')->count());
        $this->assertSame(2, StockEntry::withoutGlobalScope('entreprise')->count());
    }

    public function test_un_comptage_vide_est_refuse(): void
    {
        $alpha = $this->makeCompany('alpha');
        $this->actingAs($this->makeAdmin($alpha));
        $this->stockUp($alpha);

        $this->post(route('admin.commercial.inventory.store'), ['inventoried_at' => '2026-09-10', 'items' => [['key' => self::RAMETTE, 'counted' => '']]])
            ->assertSessionHasErrors(['items' => 'Saisissez la quantité comptée d’au moins un article.']);
        // Un article d'une autre entreprise, ou inconnu, est ignoré.
        $this->post(route('admin.commercial.inventory.store'), ['inventoried_at' => '2026-09-10', 'items' => [['key' => 'article inconnu||', 'counted' => 3]]])
            ->assertSessionHasErrors('items');
        $this->assertSame(0, StockInventory::withoutGlobalScope('entreprise')->count());
    }
}
