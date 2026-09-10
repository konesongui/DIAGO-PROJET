<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Partie double : la somme des debits doit egaler celle des credits. Sans
 * cette garantie, aucun bilan ni compte de resultat n'est exploitable.
 */
class LedgerTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private LedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = app(LedgerService::class);
    }

    public function test_une_ecriture_equilibree_est_enregistree(): void
    {
        $company = $this->makeCompany();

        $entry = $this->ledger->post($company->id, 'VE', now(), 'Facture de vente', [
            ['role' => LedgerAccount::ROLE_CUSTOMERS, 'debit' => 118000],
            ['role' => LedgerAccount::ROLE_SALES, 'credit' => 100000],
            ['role' => LedgerAccount::ROLE_VAT_COLLECTED, 'credit' => 18000],
        ]);

        $this->assertTrue($entry->isBalanced());
        $this->assertSame(118000.0, $entry->totalDebit());
        $this->assertSame(118000.0, $entry->totalCredit());
        $this->assertCount(3, $entry->lines);
    }

    public function test_une_ecriture_desequilibree_est_refusee(): void
    {
        $company = $this->makeCompany();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/déséquilibrée/');

        $this->ledger->post($company->id, 'VE', now(), 'Facture faussée', [
            ['role' => LedgerAccount::ROLE_CUSTOMERS, 'debit' => 118000],
            ['role' => LedgerAccount::ROLE_SALES, 'credit' => 100000],
        ]);
    }

    public function test_une_ecriture_desequilibree_ne_laisse_aucune_trace(): void
    {
        $company = $this->makeCompany();

        try {
            $this->ledger->post($company->id, 'VE', now(), 'Facture faussée', [
                ['role' => LedgerAccount::ROLE_CUSTOMERS, 'debit' => 500],
                ['role' => LedgerAccount::ROLE_SALES, 'credit' => 400],
            ]);
        } catch (RuntimeException) {
            // attendu
        }

        $this->assertSame(0, JournalEntry::withoutGlobalScope('entreprise')->count());
    }

    public function test_une_ecriture_a_une_seule_ligne_est_refusee(): void
    {
        $company = $this->makeCompany();

        $this->expectException(RuntimeException::class);

        $this->ledger->post($company->id, 'OD', now(), 'Ligne isolée', [
            ['role' => LedgerAccount::ROLE_CASH, 'debit' => 1000],
        ]);
    }

    public function test_le_solde_d_un_compte_est_la_difference_debit_credit(): void
    {
        $company = $this->makeCompany();

        $this->ledger->post($company->id, 'VE', now(), 'Vente', [
            ['role' => LedgerAccount::ROLE_CUSTOMERS, 'debit' => 118000],
            ['role' => LedgerAccount::ROLE_SALES, 'credit' => 100000],
            ['role' => LedgerAccount::ROLE_VAT_COLLECTED, 'credit' => 18000],
        ]);
        $this->ledger->post($company->id, 'CA', now(), 'Règlement', [
            ['role' => LedgerAccount::ROLE_CASH, 'debit' => 50000],
            ['role' => LedgerAccount::ROLE_CUSTOMERS, 'credit' => 50000],
        ]);

        $this->assertSame(68000.0, $this->ledger->balance($company->id, LedgerAccount::ROLE_CUSTOMERS));
        $this->assertSame(50000.0, $this->ledger->balance($company->id, LedgerAccount::ROLE_CASH));
    }

    public function test_le_solde_se_limite_a_la_periode_demandee(): void
    {
        $company = $this->makeCompany();

        $this->ledger->post($company->id, 'CA', now()->subYear(), 'Ancien exercice', [
            ['role' => LedgerAccount::ROLE_CASH, 'debit' => 1000],
            ['role' => LedgerAccount::ROLE_SALES, 'credit' => 1000],
        ]);
        $this->ledger->post($company->id, 'CA', now(), 'Exercice courant', [
            ['role' => LedgerAccount::ROLE_CASH, 'debit' => 500],
            ['role' => LedgerAccount::ROLE_SALES, 'credit' => 500],
        ]);

        $this->assertSame(1500.0, $this->ledger->balance($company->id, LedgerAccount::ROLE_CASH));
        $this->assertSame(
            500.0,
            $this->ledger->balance($company->id, LedgerAccount::ROLE_CASH, now()->startOfYear(), now()->endOfYear())
        );
    }

    public function test_le_plan_comptable_est_propre_a_chaque_entreprise(): void
    {
        $a = $this->makeCompany('a');
        $b = $this->makeCompany('b');

        $this->assertNotSame(
            $this->ledgerAccount($a, LedgerAccount::ROLE_CUSTOMERS)->id,
            $this->ledgerAccount($b, LedgerAccount::ROLE_CUSTOMERS)->id
        );

        $this->ledger->post($a->id, 'VE', now(), 'Vente A', [
            ['role' => LedgerAccount::ROLE_CUSTOMERS, 'debit' => 1000],
            ['role' => LedgerAccount::ROLE_SALES, 'credit' => 1000],
        ]);

        $this->assertSame(1000.0, $this->ledger->balance($a->id, LedgerAccount::ROLE_CUSTOMERS));
        $this->assertSame(0.0, $this->ledger->balance($b->id, LedgerAccount::ROLE_CUSTOMERS));
    }
}
