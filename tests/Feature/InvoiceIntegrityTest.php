<?php

namespace Tests\Feature;

use App\Models\CustomInvoice;
use App\Services\DocumentNumberService;
use App\Services\InvoiceIntegrityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Integrite des documents fiscaux : une facture remise au client est
 * opposable. Elle ne se modifie pas et ne se supprime pas, elle se corrige
 * par un avoir.
 */
class InvoiceIntegrityTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private InvoiceIntegrityService $integrity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->integrity = app(InvoiceIntegrityService::class);
    }

    private function invoice(int $entrepriseId, float $ht = 100000, float $tax = 18000, string $regime = 'standard'): CustomInvoice
    {
        return CustomInvoice::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entrepriseId,
            'reference' => app(DocumentNumberService::class)->next($entrepriseId, 'custom_invoice'),
            'client_name' => 'Client', 'items' => [],
            'total_ht' => $ht, 'tax_amount' => $tax, 'tax_rate' => $tax > 0 ? 18 : 0,
            'tax_regime' => $regime, 'total_ttc' => $ht + $tax, 'status' => 'draft',
        ]);
    }

    public function test_un_brouillon_reste_modifiable(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->invoice($company->id);

        $this->assertFalse($this->integrity->isLocked($invoice));
        $this->integrity->assertModifiable($invoice);
    }

    public function test_une_facture_emise_ne_peut_plus_etre_modifiee(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->invoice($company->id);

        $this->integrity->issue($invoice);

        $this->assertTrue($this->integrity->isLocked($invoice));
        $this->expectException(RuntimeException::class);
        $this->integrity->assertModifiable($invoice);
    }

    public function test_une_facture_payee_est_verrouillee_meme_sans_emission(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->invoice($company->id);
        $invoice->forceFill(['paid_amount' => 1000])->save();

        $this->assertTrue($this->integrity->isLocked($invoice));
        $this->assertStringContainsString('paiement', $this->integrity->lockReason($invoice));
    }

    public function test_un_avoir_sur_brouillon_est_refuse(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->invoice($company->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/n'est pas encore émise/");

        $this->integrity->credit($invoice, 1000, 'Motif suffisant');
    }

    public function test_un_avoir_partiel_reprend_le_taux_d_origine(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->invoice($company->id);
        $this->integrity->issue($invoice);

        $note = $this->integrity->credit($invoice->refresh(), 11800, 'Retour partiel de marchandise');

        $this->assertSame(10000.0, (float) $note->total_ht);
        $this->assertSame(1800.0, (float) $note->tax_amount);
        $this->assertSame(11800.0, (float) $note->total_ttc);
        $this->assertSame('standard', $note->tax_regime);
    }

    /** Corriger une facture exoneree ne doit pas produire un avoir taxe. */
    public function test_un_avoir_sur_facture_exoneree_ne_porte_aucune_taxe(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->invoice($company->id, 100000, 0, 'exempt');
        $this->integrity->issue($invoice);

        $note = $this->integrity->credit($invoice->refresh(), 10000, 'Annulation de prestation');

        $this->assertSame(0.0, (float) $note->tax_amount);
        $this->assertSame(10000.0, (float) $note->total_ht);
        $this->assertSame('exempt', $note->tax_regime);
    }

    public function test_on_ne_peut_pas_crediter_plus_que_le_montant_facture(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->invoice($company->id);
        $this->integrity->issue($invoice);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/dépasse/');

        $this->integrity->credit($invoice->refresh(), 200000, 'Montant excessif');
    }

    public function test_le_cumul_des_avoirs_est_suivi(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->invoice($company->id);
        $this->integrity->issue($invoice);
        $invoice->refresh();

        $this->integrity->credit($invoice, 18000, 'Première correction');
        $invoice->refresh();
        $this->integrity->credit($invoice, 20000, 'Seconde correction');
        $invoice->refresh();

        $this->assertSame(38000.0, (float) $invoice->credited_amount);
        $this->assertSame(80000.0, $this->integrity->creditableAmount($invoice));
    }

    public function test_la_facture_d_origine_n_est_jamais_modifiee_par_un_avoir(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->invoice($company->id);
        $this->integrity->issue($invoice);
        $invoice->refresh();

        $this->integrity->credit($invoice, 11800, 'Retour de marchandise');

        $this->assertSame(118000.0, (float) $invoice->refresh()->total_ttc);
        $this->assertSame(100000.0, (float) $invoice->total_ht);
    }

    public function test_un_avoir_exige_un_motif(): void
    {
        $company = $this->makeCompany();
        $admin = $this->makeAdmin($company);
        $invoice = $this->invoice($company->id);
        $this->integrity->issue($invoice);

        $response = $this->actingAs($admin)
            ->post(route('admin.commercial.custom-invoice.credit', $invoice), ['amount' => 1000, 'reason' => 'abc']);

        $response->assertSessionHasErrors('reason');
        $this->assertSame(0.0, (float) $invoice->refresh()->credited_amount);
    }
}
