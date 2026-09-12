<?php

namespace Tests\Feature;

use App\Models\CommercialSupplier;
use App\Models\Entreprise;
use App\Models\SupplierInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Carnet des fournisseurs : factures rattachées par le NCC, jamais par le nom.
 */
class SuppliersPageTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private function makeSupplier(Entreprise $entreprise, string $name, string $ncc): CommercialSupplier
    {
        return CommercialSupplier::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'name' => $name, 'responsible_name' => 'Responsable', 'tax_id' => $ncc,
            'phone' => '0102030405', 'email' => 'contact@fournisseur.ci', 'address' => 'Abidjan',
        ]);
    }

    private function makeInvoice(Entreprise $entreprise, string $supplierName, ?string $ncc, float $ttc, string $date): SupplierInvoice
    {
        return SupplierInvoice::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'supplier_name' => $supplierName, 'supplier_tax_id' => $ncc, 'invoice_number' => 'F-' . $ttc,
            'invoice_date' => $date, 'total_amount' => $ttc, 'currency' => 'XOF', 'status' => 'imported',
            'file_path' => 'factures/demo.pdf', 'original_filename' => 'facture.pdf',
        ]);
    }

    public function test_les_factures_sont_rattachees_par_le_ncc_sans_tenir_compte_des_espaces(): void
    {
        $alpha = $this->makeCompany('alpha');
        $macstore = $this->makeSupplier($alpha, 'MacStore Abidjan', '1504321 C');
        $this->makeInvoice($alpha, 'MacStore Abidjan', '1504321 C', 1000000, '2026-08-30');
        $this->makeInvoice($alpha, 'MACSTORE SARL', '1504321c', 500000, '2026-07-03');
        // Même nom, NCC différent : ce n'est pas le même fournisseur.
        $this->makeInvoice($alpha, 'MacStore Abidjan', '9999999 Z', 250000, '2026-09-01');
        $this->makeInvoice($alpha, 'Air Côte d’Ivoire', null, 420000, '2026-09-05');

        $response = $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.commercial.module', 'fournisseurs'))
            ->assertOk()
            ->assertSee('MacStore Abidjan');

        $activity = $response->viewData('activity')[$macstore->id];
        $this->assertSame(1500000.0, $activity['purchases']);
        $this->assertCount(2, $activity['invoices']);
        $this->assertSame('2026-08-30', $activity['last']);
        $this->assertSame(2, $response->viewData('unmatchedInvoices'));
    }

    public function test_une_entreprise_ne_voit_ni_les_fournisseurs_ni_les_factures_d_une_autre(): void
    {
        $alpha = $this->makeCompany('alpha');
        $beta = $this->makeCompany('beta');
        $supplier = $this->makeSupplier($alpha, 'Librairie de France', '0712456 A');
        $this->makeSupplier($beta, 'Fournisseur de Beta', '1111111 B');
        $this->makeInvoice($beta, 'Librairie de France', '0712456 A', 800000, '2026-08-01');

        $response = $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.commercial.module', 'fournisseurs'))
            ->assertOk()
            ->assertDontSee('Fournisseur de Beta');

        $this->assertArrayNotHasKey($supplier->id, $response->viewData('activity'), 'La facture de Beta ne se rattache pas au fournisseur d’Alpha.');
    }
}
