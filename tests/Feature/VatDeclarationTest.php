<?php

namespace Tests\Feature;

use App\Models\CustomInvoice;
use App\Models\PosSale;
use App\Models\SupplierInvoice;
use App\Services\DocumentNumberService;
use App\Services\InvoiceIntegrityService;
use App\Services\VatDeclarationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Etat de TVA : c'est le document transmis a l'administration fiscale.
 */
class VatDeclarationTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private VatDeclarationService $vat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vat = app(VatDeclarationService::class);
    }

    private function issuedInvoice(int $id, float $ht, float $tax, string $regime = 'standard'): CustomInvoice
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

    private function declare(int $id): array
    {
        return $this->vat->declare($id, Carbon::now()->startOfYear(), Carbon::now()->endOfYear());
    }

    public function test_la_taxe_collectee_additionne_toutes_les_origines(): void
    {
        $company = $this->makeCompany();

        $this->issuedInvoice($company->id, 100000, 18000);
        PosSale::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $company->id, 'client_name' => 'C', 'lines' => [],
            'total' => 11800, 'paid_amount' => 11800, 'change_amount' => 0,
            'payment_method' => 'cash', 'status' => 'completed',
            'total_ht' => 10000, 'tax_amount' => 1800, 'tax_rate' => 18,
            'tax_regime' => 'standard', 'currency' => 'XOF',
        ]);

        $this->assertSame(19800.0, $this->declare($company->id)['collected']['tax']);
    }

    /** Un brouillon n'est pas opposable : il ne doit jamais etre declare. */
    public function test_un_brouillon_n_est_pas_declare(): void
    {
        $company = $this->makeCompany();

        CustomInvoice::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $company->id,
            'reference' => app(DocumentNumberService::class)->next($company->id, 'custom_invoice'),
            'client_name' => 'Client', 'items' => [],
            'total_ht' => 50000, 'tax_amount' => 9000, 'tax_rate' => 18,
            'tax_regime' => 'standard', 'total_ttc' => 59000, 'status' => 'draft',
        ]);

        $this->assertSame(0.0, $this->declare($company->id)['collected']['tax']);
    }

    public function test_les_avoirs_viennent_en_diminution(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->issuedInvoice($company->id, 100000, 18000);

        app(InvoiceIntegrityService::class)->credit($invoice, 5900, 'Retour de marchandise');

        $result = $this->declare($company->id);

        $this->assertSame(18000.0, $result['collected']['tax']);
        $this->assertSame(900.0, $result['credit_notes']['tax']);
        $this->assertSame(17100.0, $result['collected_net']);
    }

    public function test_le_solde_est_la_taxe_collectee_moins_la_deductible(): void
    {
        $company = $this->makeCompany();
        $this->issuedInvoice($company->id, 100000, 18000);

        SupplierInvoice::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $company->id, 'invoice_number' => 'F-1', 'supplier_name' => 'F',
            'invoice_date' => now()->toDateString(), 'total_ht' => 50000, 'tax_amount' => 9000,
            'total_amount' => 59000, 'tax_regime' => 'standard', 'currency' => 'XOF',
            'status' => 'imported', 'file_path' => 'x.pdf', 'original_filename' => 'x.pdf',
        ]);

        $result = $this->declare($company->id);

        $this->assertSame(9000.0, $result['deductible']['tax']);
        $this->assertSame(9000.0, $result['balance']);
        $this->assertTrue($result['is_payable']);
    }

    public function test_un_credit_de_tva_est_signale_comme_reportable(): void
    {
        $company = $this->makeCompany();

        SupplierInvoice::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $company->id, 'invoice_number' => 'F-1', 'supplier_name' => 'F',
            'invoice_date' => now()->toDateString(), 'total_ht' => 50000, 'tax_amount' => 9000,
            'total_amount' => 59000, 'tax_regime' => 'standard', 'currency' => 'XOF',
            'status' => 'imported', 'file_path' => 'x.pdf', 'original_filename' => 'x.pdf',
        ]);

        $result = $this->declare($company->id);

        $this->assertSame(-9000.0, $result['balance']);
        $this->assertFalse($result['is_payable']);
    }

    public function test_une_facture_exoneree_apparait_sans_taxe(): void
    {
        $company = $this->makeCompany();
        $this->issuedInvoice($company->id, 100000, 0, 'exempt');

        $result = $this->declare($company->id);

        $this->assertSame(0.0, $result['collected']['tax']);
        $this->assertSame(100000.0, $result['collected']['base_ht']);
    }

    public function test_un_document_sans_regime_est_signale(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->issuedInvoice($company->id, 100000, 18000);
        $invoice->forceFill(['tax_regime' => 'unknown'])->save();

        $this->assertNotEmpty($this->declare($company->id)['warnings']);
    }

    public function test_la_declaration_se_limite_a_sa_periode(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->issuedInvoice($company->id, 100000, 18000);
        $invoice->forceFill(['issued_at' => now()->subYears(2)])->save();

        $this->assertSame(0.0, $this->declare($company->id)['collected']['tax']);
    }
}
