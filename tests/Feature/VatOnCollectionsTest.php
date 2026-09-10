<?php

namespace Tests\Feature;

use App\Models\CustomInvoice;
use App\Services\DocumentNumberService;
use App\Services\InvoiceIntegrityService;
use App\Services\PaymentRecorder;
use App\Services\VatDeclarationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * TVA sur les encaissements.
 *
 * Sous ce regime la taxe n'est exigible qu'au reglement. Une facture emise
 * mais impayee ne doit rien faire payer a l'entreprise, et un reglement
 * partiel ne rend exigible que la fraction encaissee.
 */
class VatOnCollectionsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private VatDeclarationService $vat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vat = app(VatDeclarationService::class);
    }

    private function issued(int $id, float $ht = 100000, float $tax = 18000, string $regime = 'standard'): CustomInvoice
    {
        $invoice = CustomInvoice::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $id,
            'reference' => app(DocumentNumberService::class)->next($id, 'custom_invoice'),
            'client_name' => 'Client', 'items' => [],
            'total_ht' => $ht, 'tax_amount' => $tax, 'tax_rate' => $tax > 0 ? 18 : 0,
            'tax_regime' => $regime, 'total_ttc' => $ht + $tax, 'status' => 'draft',
        ]);

        app(InvoiceIntegrityService::class)->issue($invoice);

        return $invoice->refresh();
    }

    private function declare(int $id, string $basis): array
    {
        return $this->vat->declare($id, Carbon::now()->startOfYear(), Carbon::now()->endOfYear(), $basis);
    }

    public function test_une_facture_impayee_ne_rend_aucune_taxe_exigible(): void
    {
        $company = $this->makeCompany();
        $this->issued($company->id);

        $debits = $this->declare($company->id, VatDeclarationService::BASIS_DEBITS);
        $encaissements = $this->declare($company->id, VatDeclarationService::BASIS_COLLECTIONS);

        $this->assertSame(18000.0, $debits['collected']['tax'], 'Sur les débits, la taxe est due dès l’émission.');
        $this->assertSame(0.0, $encaissements['collected']['tax'], 'Sur les encaissements, rien n’est dû sans règlement.');
    }

    public function test_un_reglement_total_rend_toute_la_taxe_exigible(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->issued($company->id);

        app(PaymentRecorder::class)->record($invoice, 118000, 'cash', now());

        $this->assertSame(18000.0, $this->declare($company->id, VatDeclarationService::BASIS_COLLECTIONS)['collected']['tax']);
    }

    /** Le coeur du regime : la taxe suit la fraction encaissee. */
    public function test_un_reglement_partiel_ne_rend_exigible_que_sa_fraction(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->issued($company->id);

        app(PaymentRecorder::class)->record($invoice, 59000, 'cash', now());

        $result = $this->declare($company->id, VatDeclarationService::BASIS_COLLECTIONS);

        $this->assertSame(9000.0, $result['collected']['tax'], 'La moitié encaissée rend la moitié de la taxe exigible.');
        $this->assertSame(50000.0, $result['collected']['base_ht']);
    }

    public function test_les_reglements_successifs_s_additionnent(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->issued($company->id);

        app(PaymentRecorder::class)->record($invoice, 59000, 'cash', now());
        app(PaymentRecorder::class)->record($invoice, 59000, 'bank', now());

        $this->assertSame(18000.0, $this->declare($company->id, VatDeclarationService::BASIS_COLLECTIONS)['collected']['tax']);
    }

    public function test_la_taxe_est_rattachee_a_la_date_du_reglement(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->issued($company->id);

        // Facture emise cette annee, reglee l'annee suivante.
        app(PaymentRecorder::class)->record($invoice, 118000, 'cash', Carbon::now()->addYear());

        $this->assertSame(0.0, $this->declare($company->id, VatDeclarationService::BASIS_COLLECTIONS)['collected']['tax']);

        $anneeSuivante = $this->vat->declare(
            $company->id,
            Carbon::now()->addYear()->startOfYear(),
            Carbon::now()->addYear()->endOfYear(),
            VatDeclarationService::BASIS_COLLECTIONS
        );

        $this->assertSame(18000.0, $anneeSuivante['collected']['tax']);
    }

    public function test_un_reglement_sur_facture_exoneree_ne_produit_aucune_taxe(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->issued($company->id, 100000, 0, 'exempt');

        app(PaymentRecorder::class)->record($invoice, 100000, 'cash', now());

        $result = $this->declare($company->id, VatDeclarationService::BASIS_COLLECTIONS);

        $this->assertSame(0.0, $result['collected']['tax']);
        $this->assertSame(100000.0, $result['collected']['base_ht']);
    }

    public function test_le_regime_retenu_est_indique_dans_le_resultat(): void
    {
        $company = $this->makeCompany();

        $this->assertSame('debits', $this->declare($company->id, VatDeclarationService::BASIS_DEBITS)['basis']);
        $this->assertSame('collections', $this->declare($company->id, VatDeclarationService::BASIS_COLLECTIONS)['basis']);
    }

    public function test_un_regime_inconnu_retombe_sur_les_debits(): void
    {
        $company = $this->makeCompany();
        $this->issued($company->id);

        $result = $this->declare($company->id, 'fantaisie');

        $this->assertSame('debits', $result['basis']);
        $this->assertSame(18000.0, $result['collected']['tax']);
    }
}
