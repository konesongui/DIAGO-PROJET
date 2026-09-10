<?php

namespace Tests\Feature;

use App\Models\CustomInvoice;
use App\Models\TaxRate;
use App\Services\DocumentNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Isolation multi-entreprises : une societe cliente ne doit jamais voir les
 * donnees d'une autre. C'est la garantie centrale d'un logiciel mutualise.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private function invoiceFor(int $entrepriseId, string $client): CustomInvoice
    {
        return CustomInvoice::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entrepriseId,
            'reference' => app(DocumentNumberService::class)->next($entrepriseId, 'custom_invoice'),
            'client_name' => $client, 'items' => [],
            'total_ht' => 1000, 'tax_amount' => 180, 'tax_rate' => 18,
            'tax_regime' => 'standard', 'total_ttc' => 1180, 'status' => 'draft',
        ]);
    }

    public function test_un_utilisateur_ne_voit_que_les_factures_de_son_entreprise(): void
    {
        $a = $this->makeCompany('alpha');
        $b = $this->makeCompany('beta');
        $this->invoiceFor($a->id, 'Client Alpha');
        $this->invoiceFor($b->id, 'Client Beta');

        $this->actingAs($this->makeAdmin($a));

        $visible = CustomInvoice::all();

        $this->assertCount(1, $visible);
        $this->assertSame('Client Alpha', $visible->first()->client_name);
    }

    public function test_un_utilisateur_ne_peut_pas_ouvrir_la_facture_d_une_autre_entreprise(): void
    {
        $a = $this->makeCompany('alpha');
        $b = $this->makeCompany('beta');
        $invoiceB = $this->invoiceFor($b->id, 'Client Beta');

        $response = $this->actingAs($this->makeAdmin($a))
            ->get(route('admin.commercial.custom-invoice.show', $invoiceB));

        $this->assertContains($response->status(), [403, 404],
            "Une facture d'une autre entreprise ne doit être ni affichée ni trouvée.");
    }

    public function test_les_taux_de_taxe_sont_cloisonnes(): void
    {
        $a = $this->makeCompany('alpha', 'XOF');
        $b = $this->makeCompany('beta', 'EUR');

        $this->actingAs($this->makeAdmin($a));

        $this->assertEqualsCanonicalizing(
            ['TVA18', 'TVA9', 'EXO'],
            TaxRate::all()->pluck('code')->all()
        );
    }

    public function test_une_creation_rattache_automatiquement_a_l_entreprise_connectee(): void
    {
        $a = $this->makeCompany('alpha');
        $this->makeCompany('beta');

        $this->actingAs($this->makeAdmin($a));

        $invoice = CustomInvoice::create([
            'reference' => app(DocumentNumberService::class)->next($a->id, 'custom_invoice'),
            'client_name' => 'Nouveau', 'items' => [],
            'total_ht' => 100, 'total_ttc' => 100, 'status' => 'draft',
        ]);

        $this->assertSame($a->id, $invoice->entreprise_id);
    }

    public function test_un_avoir_ne_peut_pas_viser_la_facture_d_une_autre_entreprise(): void
    {
        $a = $this->makeCompany('alpha');
        $b = $this->makeCompany('beta');
        $invoiceB = $this->invoiceFor($b->id, 'Client Beta');
        $invoiceB->forceFill(['issued_at' => now()])->save();

        $response = $this->actingAs($this->makeAdmin($a))
            ->post(route('admin.commercial.custom-invoice.credit', $invoiceB), [
                'amount' => 100, 'reason' => 'Tentative depuis une autre société',
            ]);

        $this->assertContains($response->status(), [403, 404]);
        $this->assertSame(0.0, (float) $invoiceB->refresh()->credited_amount);
    }
}
