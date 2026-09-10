<?php

namespace Tests\Feature;

use App\Models\Entreprise;
use App\Models\StockEntry;
use App\Models\StockEntryLine;
use App\Models\StockExit;
use App\Models\StockExitLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Etat du stock : quantite entree moins quantite sortie.
 *
 * Un stock faux fausse la valorisation et laisse vendre ce qui n'existe pas.
 */
class StockBalanceTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private function entryLine(Entreprise $company, string $name, float $qty, float $price = 1000): StockEntryLine
    {
        $entry = StockEntry::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $company->id,
            'entry_date' => now()->toDateString(),
            'total_value' => $qty * $price,
        ]);

        // entreprise_id et les totaux ne sont pas dans les champs autorises
        // en ecriture : hors requete HTTP, ils doivent etre poses explicitement.
        $line = StockEntryLine::withoutGlobalScope('entreprise')->make([
            'stock_entry_id' => $entry->id,
            'designation' => $name, 'article' => $name, 'unit' => 'pcs',
            'quantity' => $qty, 'purchase_price' => $price,
        ]);
        $line->forceFill([
            'entreprise_id' => $company->id,
            'total_purchase' => $qty * $price,
        ])->save();

        return $line;
    }

    private function exit(Entreprise $company, StockEntryLine $line, float $qty): void
    {
        $exit = StockExit::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $company->id,
            'exit_date' => now()->toDateString(),
            'total_value' => $qty * (float) $line->purchase_price,
        ]);

        $exitLine = StockExitLine::withoutGlobalScope('entreprise')->make([
            'stock_exit_id' => $exit->id,
            'stock_entry_line_id' => $line->id,
            'designation' => $line->designation, 'article' => $line->article,
            'unit' => 'pcs', 'quantity' => $qty,
            'unit_price' => $line->purchase_price,
            'total_value' => $qty * (float) $line->purchase_price,
        ]);
        $exitLine->forceFill(['entreprise_id' => $company->id])->save();
    }

    /** Quantite restante sur une ligne d'entree. */
    private function remaining(StockEntryLine $line): float
    {
        $out = (float) DB::table('stock_exit_lines')
            ->where('stock_entry_line_id', $line->id)->sum('quantity');

        return (float) $line->quantity - $out;
    }

    public function test_le_stock_restant_est_l_entree_moins_les_sorties(): void
    {
        $company = $this->makeCompany();
        $line = $this->entryLine($company, 'Article A', 100);

        $this->exit($company, $line, 30);
        $this->exit($company, $line, 20);

        $this->assertSame(50.0, $this->remaining($line));
    }

    public function test_un_article_sans_sortie_garde_toute_sa_quantite(): void
    {
        $company = $this->makeCompany();
        $line = $this->entryLine($company, 'Article B', 75);

        $this->assertSame(75.0, $this->remaining($line));
    }

    public function test_une_sortie_totale_laisse_un_stock_nul(): void
    {
        $company = $this->makeCompany();
        $line = $this->entryLine($company, 'Article C', 40);

        $this->exit($company, $line, 40);

        $this->assertSame(0.0, $this->remaining($line));
    }

    public function test_les_sorties_de_deux_articles_ne_se_melangent_pas(): void
    {
        $company = $this->makeCompany();
        $a = $this->entryLine($company, 'Article A', 100);
        $b = $this->entryLine($company, 'Article B', 100);

        $this->exit($company, $a, 60);

        $this->assertSame(40.0, $this->remaining($a));
        $this->assertSame(100.0, $this->remaining($b));
    }

    public function test_le_stock_est_cloisonne_par_entreprise(): void
    {
        $a = $this->makeCompany('alpha');
        $b = $this->makeCompany('beta');
        $this->entryLine($a, 'Article partagé', 100);
        $this->entryLine($b, 'Article partagé', 250);

        $this->actingAs($this->makeAdmin($a));

        $lines = StockEntryLine::all();

        $this->assertCount(1, $lines);
        $this->assertSame(100.0, (float) $lines->first()->quantity);
    }

    public function test_la_valeur_du_stock_suit_le_prix_d_achat(): void
    {
        $company = $this->makeCompany();
        $line = $this->entryLine($company, 'Article D', 10, 2500);

        $this->assertSame(25000.0, (float) $line->total_purchase);

        $this->exit($company, $line, 4);

        $this->assertSame(6.0, $this->remaining($line));
        $this->assertSame(15000.0, $this->remaining($line) * (float) $line->purchase_price);
    }
}
