<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * État de trésorerie : les graphiques ne doivent refléter que les opérations
 * réellement enregistrées, jamais des valeurs de démonstration.
 */
class TreasuryReportTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    public function test_sans_operation_le_rapport_n_invente_aucun_montant(): void
    {
        $alpha = $this->makeCompany('alpha');

        $response = $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.comptabilite.rapports'))
            ->assertOk();

        $chart = collect($response->viewData('chartData'));
        $this->assertCount(6, $chart, 'Le graphique couvre les six derniers mois.');
        $this->assertSame(0.0, (float) $chart->sum('value'));
        $this->assertTrue(collect($response->viewData('distribution'))->isEmpty());
    }

    public function test_le_graphique_et_la_repartition_suivent_les_operations_reelles(): void
    {
        $alpha = $this->makeCompany('alpha');
        $account = BankAccount::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'name' => 'Compte courant', 'bank_name' => 'NSIA',
            'account_number' => '000123', 'account_type' => 'compte_courant', 'current_balance' => 0, 'status' => 'credit',
        ]);
        BankTransaction::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'bank_account_id' => $account->id, 'transaction_type' => 'credit',
            'is_transfer' => false, 'label' => 'Règlement client', 'amount' => 250000, 'transaction_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.comptabilite.rapports'))
            ->assertOk();

        $chart = collect($response->viewData('chartData'));
        $this->assertSame(250000.0, (float) $chart->last()['value'], 'Le mois en cours porte l’opération.');
        $this->assertSame(250000.0, (float) $chart->sum('value'));

        $distribution = collect($response->viewData('distribution'));
        $this->assertSame(['Entrées'], $distribution->pluck('label')->all());
        $this->assertSame(100.0, (float) $distribution->first()['percent']);
    }
}
