<?php

namespace Tests\Feature;

use App\Models\CommercialSupplier;
use App\Models\Entreprise;
use App\Models\StockEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Entrées de stock : chaque réception est rattachée au fournisseur qui l'a
 * livrée, montre ses lignes, se range à sa date et reste propre à l'entreprise.
 */
class StockEntriesPageTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private function supplier(Entreprise $entreprise, string $name = 'Ivoire Bureau'): CommercialSupplier
    {
        return CommercialSupplier::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'name' => $name, 'responsible_name' => 'M. N’Guessan', 'tax_id' => '0654321 B',
            'phone' => '0711223344', 'email' => 'contact@fournisseur.ci', 'address' => 'Marcory',
        ]);
    }

    private function receive(CommercialSupplier $supplier, string $date, array $lines, ?string $reference = null)
    {
        return $this->post(route('admin.commercial.stock-entries.store'), [
            'supplier_id' => $supplier->id, 'supplier_reference' => $reference, 'entry_date' => $date, 'lines' => $lines,
        ]);
    }

    public function test_la_liste_detaille_chaque_reception_avec_son_fournisseur_et_la_trie_par_date(): void
    {
        $alpha = $this->makeCompany('alpha');
        $supplier = $this->supplier($alpha);
        $this->actingAs($this->makeAdmin($alpha));
        $this->receive($supplier, '2026-09-01', [
            ['designation' => 'Ramette papier A4', 'article' => 'Papeterie', 'unit' => 'ramette', 'quantity' => 50, 'purchase_price' => 2800, 'profit_per_unit' => 700],
        ], 'BL-IB-2207')->assertSessionHasNoErrors();
        // Saisie après coup d'une réception plus ancienne : elle doit se ranger à sa date.
        $this->receive($supplier, '2026-08-15', [
            ['designation' => 'Cartouche d’encre noire', 'unit' => 'pièce', 'quantity' => 12, 'purchase_price' => 9500, 'profit_per_unit' => 3000],
        ])->assertSessionHasNoErrors();

        $response = $this->get(route('admin.commercial.module', 'entrees-stock'))->assertOk();
        $response->assertSeeInOrder(['01/09/2026', 'BL BL-IB-2207', 'Ivoire Bureau', 'Ramette papier A4', money(140000), money(35000), 'marge 25,0 %', '15/08/2026']);

        // L'ancienne liste n'affichait que des totaux : les articles reçus et leur fournisseur restaient invisibles.
        preg_match('#<script type="application/json" id="entryDetails">(.*?)</script>#s', $response->getContent(), $match);
        $details = json_decode($match[1] ?? '', true);
        $entry = StockEntry::withoutGlobalScope('entreprise')->where('entry_date', '2026-09-01')->firstOrFail();
        $this->assertSame('Ivoire Bureau', $details[$entry->id]['supplier']);
        $this->assertSame('Bon de livraison BL-IB-2207', $details[$entry->id]['reference']);
        $this->assertSame('Ramette papier A4', $details[$entry->id]['lines'][0]['designation']);
        $this->assertSame(money(3500), $details[$entry->id]['lines'][0]['sale']);
    }

    public function test_une_reception_exige_un_fournisseur_du_carnet_de_l_entreprise(): void
    {
        $alpha = $this->makeCompany('alpha');
        $foreign = $this->supplier($this->makeCompany('beta'), 'Fournisseur beta');
        $admin = $this->makeAdmin($alpha);
        $line = [['designation' => 'Clé USB 32 Go', 'quantity' => 10, 'purchase_price' => 3500]];

        $this->actingAs($admin)->from(route('admin.commercial.stock-entries.create'))
            ->post(route('admin.commercial.stock-entries.store'), ['entry_date' => '2026-09-11', 'lines' => $line])
            ->assertSessionHasErrors(['supplier_id' => 'Choisissez le fournisseur qui a livré la marchandise.']);
        $this->actingAs($admin)->receive($foreign, '2026-09-11', $line)->assertSessionHasErrors('supplier_id');
        $this->assertSame(0, StockEntry::withoutGlobalScope('entreprise')->count());

        // La saisie est reprise après l'erreur.
        $this->actingAs($admin)->get(route('admin.commercial.stock-entries.create'))->assertSee('USB 32 Go');
    }

    public function test_le_nom_du_fournisseur_reste_lisible_s_il_quitte_le_carnet(): void
    {
        $alpha = $this->makeCompany('alpha');
        $supplier = $this->supplier($alpha, 'MacStore Abidjan');
        $this->actingAs($this->makeAdmin($alpha));
        $this->receive($supplier, '2026-09-05', [['designation' => 'Clé USB 32 Go', 'quantity' => 25, 'purchase_price' => 3500]])->assertSessionHasNoErrors();

        $supplier->delete();

        $entry = StockEntry::withoutGlobalScope('entreprise')->firstOrFail();
        $this->assertNull($entry->supplier_id);
        $this->get(route('admin.commercial.module', 'entrees-stock'))->assertSee('MacStore Abidjan');
    }

    public function test_les_receptions_d_une_autre_entreprise_restent_invisibles(): void
    {
        $alpha = $this->makeCompany('alpha');
        $beta = $this->makeCompany('beta');
        $this->actingAs($this->makeAdmin($beta))->receive($this->supplier($beta), '2026-09-01', [
            ['designation' => 'Article confidentiel beta', 'quantity' => 1, 'purchase_price' => 1000],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->makeAdmin($alpha))->get(route('admin.commercial.module', 'entrees-stock'))
            ->assertOk()
            ->assertDontSee('Article confidentiel beta')
            ->assertSee('Aucune réception');
    }
}
