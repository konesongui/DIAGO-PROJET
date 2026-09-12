<?php

namespace Tests\Feature;

use App\Models\Entreprise;
use App\Models\LedgerAccount;
use App\Services\AnnualReportService;
use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Bilan annuel PDF : téléchargement, contenu tiré du journal et mentions
 * légales de l'entreprise.
 */
class AnnualReportPdfTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    /** Exercice clos avec une vente de 1 000 000 HT et un achat de 200 000 HT. */
    private function closedYearWithEntries(Entreprise $entreprise): int
    {
        $ledger = app(LedgerService::class);
        $year = app(AnnualReportService::class)->latestClosedYear();

        $ledger->post($entreprise->id, 'VE', Carbon::create($year, 6, 10), 'Facture de vente', [
            ['role' => LedgerAccount::ROLE_CUSTOMERS, 'debit' => 1180000],
            ['role' => LedgerAccount::ROLE_SALES, 'credit' => 1000000],
            ['role' => LedgerAccount::ROLE_VAT_COLLECTED, 'credit' => 180000],
        ]);
        $ledger->post($entreprise->id, 'AC', Carbon::create($year, 7, 2), 'Facture fournisseur', [
            ['role' => LedgerAccount::ROLE_PURCHASES, 'debit' => 200000],
            ['role' => LedgerAccount::ROLE_VAT_DEDUCTIBLE, 'debit' => 36000],
            ['role' => LedgerAccount::ROLE_SUPPLIERS, 'credit' => 236000],
        ]);

        return $year;
    }

    public function test_le_telechargement_produit_un_pdf_et_vaut_lecture(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $this->actingAs($admin);
        $report = app(AnnualReportService::class)->reportFor($alpha->id, $this->closedYearWithEntries($alpha));

        $response = $this->get(route('admin.bilans.download', $report))->assertOk();

        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('bilan-financier-' . $report->fiscal_year . '.pdf', $response->headers->get('content-disposition'));
        // Police réduite aux caractères utilisés : sans elle, le fichier dépasse le mégaoctet.
        $this->assertLessThan(300000, strlen($response->getContent()));
        $this->assertNotNull($report->fresh()->downloaded_at);
    }

    public function test_le_pdf_reprend_les_comptes_le_resultat_et_les_mentions_legales(): void
    {
        $alpha = $this->makeCompany('alpha');
        $alpha->update(['settings' => array_merge($alpha->settings, [
            'nccm_rccm' => 'CI-ABJ-2019-B-12345', 'taxpayer_account' => '1912345 A',
        ])]);
        $this->actingAs($this->makeAdmin($alpha));
        $report = app(AnnualReportService::class)->reportFor($alpha->id, $this->closedYearWithEntries($alpha));

        $html = view('admin.annual-report-pdf', ['report' => $report, 'data' => $report->data, 'company' => $alpha->fresh()])->render();

        $this->assertStringContainsString('BILAN FINANCIER', $html);
        $this->assertStringContainsString('Exercice ' . $report->fiscal_year, $html);
        $this->assertStringContainsString('Bilan équilibré', $html);
        $this->assertStringContainsString('Bénéfice', $html);
        $this->assertStringContainsString('RCCM : CI-ABJ-2019-B-12345', $html);
        $this->assertStringContainsString('Compte contribuable : 1912345 A', $html);
    }
}
