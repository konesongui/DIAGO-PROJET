<?php

namespace Tests\Feature;

use App\Models\TaxRate;
use App\Services\TaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Calcul de la taxe : c'est le montant que l'entreprise devra reverser a
 * l'administration fiscale. Une erreur ici a des consequences legales.
 */
class TaxCalculationTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private TaxService $taxes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->taxes = app(TaxService::class);
    }

    public function test_le_taux_vient_du_parametrage_et_non_du_code(): void
    {
        $company = $this->makeCompany('ci', 'XOF');

        $rates = $this->taxes->ratesFor($company->id);

        $this->assertSame(['TVA18', 'TVA9', 'EXO'], $rates->pluck('code')->all());
        $this->assertSame('TVA18', $this->taxes->defaultRate($company->id)->code);
    }

    public function test_chaque_pays_recoit_son_propre_bareme(): void
    {
        $france = $this->makeCompany('fr', 'EUR');

        $rates = $this->taxes->ratesFor($france->id);

        $this->assertEqualsCanonicalizing(
            [20.0, 10.0, 5.5, 0.0],
            $rates->map(fn (TaxRate $r) => (float) $r->rate)->all()
        );
    }

    public function test_la_taxe_est_calculee_au_taux_configure(): void
    {
        $company = $this->makeCompany('ci');
        $rate = $this->rate($company, 'TVA18');

        $result = $this->taxes->breakdown(100000, $rate, 'XOF');

        $this->assertSame(100000.0, $result['base_ht']);
        $this->assertSame(18000.0, $result['tax_amount']);
        $this->assertSame(118000.0, $result['total_ttc']);
        $this->assertSame('standard', $result['regime']);
    }

    /**
     * Le regime prime sur la valeur du taux : c'est le defaut qui faisait
     * declarer un client exonere comme taxable.
     */
    public function test_un_regime_exonere_ne_produit_aucune_taxe(): void
    {
        $company = $this->makeCompany('ci');
        $exempt = $this->rate($company, 'EXO');

        $result = $this->taxes->breakdown(100000, $exempt, 'XOF');

        $this->assertSame(0.0, $result['tax_amount']);
        $this->assertSame(100000.0, $result['total_ttc']);
        $this->assertSame('exempt', $result['regime']);
    }

    public function test_un_regime_exonere_reste_exonere_meme_avec_un_taux_saisi(): void
    {
        $company = $this->makeCompany('ci');

        $piege = TaxRate::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $company->id, 'name' => 'Export taxé par erreur',
            'code' => 'EXP', 'rate' => 18, 'regime' => TaxRate::REGIME_EXPORT, 'is_active' => true,
        ]);

        $this->assertSame(0.0, $this->taxes->breakdown(100000, $piege, 'XOF')['tax_amount']);
    }

    /**
     * Le franc CFA ne se subdivise pas, l'euro utilise deux decimales et le
     * dinar koweitien trois. Un arrondi unique produirait des ecarts.
     */
    public function test_l_arrondi_suit_les_decimales_de_la_devise(): void
    {
        $company = $this->makeCompany('ci');
        $rate = $this->rate($company, 'TVA18');

        $this->assertSame(18.0, $this->taxes->breakdown(100.55, $rate, 'XOF')['tax_amount']);
        $this->assertSame(18.1, $this->taxes->breakdown(100.55, $rate, 'EUR')['tax_amount']);
        $this->assertSame(18.099, $this->taxes->breakdown(100.55, $rate, 'KWD')['tax_amount']);
    }

    /**
     * En caisse le prix affiche est celui paye : la taxe s'en extrait, elle ne
     * s'y ajoute pas, sinon le montant encaisse changerait.
     */
    public function test_la_taxe_est_extraite_d_un_montant_ttc(): void
    {
        $company = $this->makeCompany('ci');
        $rate = $this->rate($company, 'TVA18');

        $result = $this->taxes->breakdownFromTtc(11800, $rate, 'XOF');

        $this->assertSame(10000.0, $result['base_ht']);
        $this->assertSame(1800.0, $result['tax_amount']);
        $this->assertSame(11800.0, $result['total_ttc'], 'Le montant encaissé ne doit pas bouger.');
    }

    public function test_la_ventilation_d_un_ttc_retombe_toujours_sur_le_total(): void
    {
        $company = $this->makeCompany('ci');
        $rate = $this->rate($company, 'TVA18');

        foreach ([1, 7, 99, 12345, 999999] as $ttc) {
            $r = $this->taxes->breakdownFromTtc($ttc, $rate, 'XOF');

            $this->assertSame(
                (float) $ttc,
                round($r['base_ht'] + $r['tax_amount'], 2),
                "La somme HT + taxe doit égaler le TTC pour {$ttc}."
            );
        }
    }

    public function test_un_taux_inconnu_retombe_sur_le_taux_par_defaut(): void
    {
        $company = $this->makeCompany('ci');

        $this->assertSame('TVA18', $this->taxes->resolveRate($company->id, 999999)->code);
        $this->assertSame('TVA18', $this->taxes->resolveRate($company->id, null)->code);
    }

    public function test_un_taux_non_encore_en_vigueur_est_ignore(): void
    {
        $company = $this->makeCompany('ci');

        TaxRate::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $company->id, 'name' => 'TVA future', 'code' => 'FUT',
            'rate' => 25, 'regime' => TaxRate::REGIME_STANDARD, 'is_active' => true,
            'effective_from' => now()->addYear()->toDateString(),
        ]);

        $codes = $this->taxes->ratesFor($company->id)->pluck('code')->all();

        $this->assertNotContains('FUT', $codes);
        $this->assertContains('FUT', $this->taxes->ratesFor($company->id, now()->addYears(2))->pluck('code')->all());
    }
}
