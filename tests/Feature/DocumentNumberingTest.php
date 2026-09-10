<?php

namespace Tests\Feature;

use App\Models\CustomInvoice;
use App\Services\DocumentNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Numerotation : un numero de facture en double est un defaut de conformite.
 */
class DocumentNumberingTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private DocumentNumberService $numbers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->numbers = app(DocumentNumberService::class);
    }

    public function test_les_numeros_se_suivent_sans_trou(): void
    {
        $company = $this->makeCompany();

        $refs = [];
        for ($i = 0; $i < 5; $i++) {
            $refs[] = $this->numbers->next($company->id, 'custom_invoice');
        }

        $today = Carbon::now()->format('Ymd');
        $this->assertSame([
            "FC-{$today}-0001", "FC-{$today}-0002", "FC-{$today}-0003",
            "FC-{$today}-0004", "FC-{$today}-0005",
        ], $refs);
    }

    public function test_aucun_doublon_sur_un_grand_volume(): void
    {
        $company = $this->makeCompany();

        $refs = [];
        for ($i = 0; $i < 200; $i++) {
            $refs[] = $this->numbers->next($company->id, 'custom_invoice');
        }

        $this->assertCount(200, array_unique($refs));
    }

    /**
     * L'unicite etait globale alors que le comptage etait par entreprise :
     * la deuxieme societe ne pouvait pas creer sa premiere facture du jour.
     */
    public function test_deux_entreprises_ont_chacune_leur_numerotation(): void
    {
        $a = $this->makeCompany('a');
        $b = $this->makeCompany('b');

        $refA = $this->numbers->next($a->id, 'custom_invoice');
        $refB = $this->numbers->next($b->id, 'custom_invoice');

        $this->assertSame($refA, $refB, 'Chaque entreprise repart à 1.');

        foreach ([[$a, $refA], [$b, $refB]] as [$company, $ref]) {
            CustomInvoice::withoutGlobalScope('entreprise')->create([
                'entreprise_id' => $company->id, 'reference' => $ref, 'client_name' => 'C',
                'items' => [], 'total_ht' => 1000, 'total_ttc' => 1000, 'status' => 'draft',
            ]);
        }

        $this->assertSame(2, CustomInvoice::withoutGlobalScope('entreprise')->count());
    }

    public function test_chaque_type_de_document_a_son_compteur(): void
    {
        $company = $this->makeCompany();

        $this->numbers->next($company->id, 'custom_invoice');
        $quote = $this->numbers->next($company->id, 'quote');
        $credit = $this->numbers->next($company->id, 'credit_note');

        $this->assertStringStartsWith('DEV-', $quote);
        $this->assertStringEndsWith('-0001', $quote);
        $this->assertStringStartsWith('AV-', $credit);
    }

    public function test_le_compteur_des_factures_est_annuel(): void
    {
        $company = $this->makeCompany();

        $thisYear = $this->numbers->next($company->id, 'credit_note', Carbon::create(2026, 3, 1));
        $sameYear = $this->numbers->next($company->id, 'credit_note', Carbon::create(2026, 11, 1));
        $nextYear = $this->numbers->next($company->id, 'credit_note', Carbon::create(2027, 1, 5));

        $this->assertSame('AV-2026-00001', $thisYear);
        $this->assertSame('AV-2026-00002', $sameYear);
        $this->assertSame('AV-2027-00001', $nextYear);
    }
}
