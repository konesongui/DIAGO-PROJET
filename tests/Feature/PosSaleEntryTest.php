<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Entreprise;
use App\Models\LedgerAccount;
use App\Models\PosSale;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Saisie d'une vente au comptoir : refus lisibles, banque au montant exact,
 * modification qui met à jour la TVA et le journal, annulation qui remet le
 * journal à zéro même après une modification.
 */
class PosSaleEntryTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private function cashBox(Entreprise $entreprise): CashAccount
    {
        return CashAccount::withoutGlobalScope('entreprise')->create(['entreprise_id' => $entreprise->id, 'name' => 'Caisse principale', 'balance' => 0, 'is_active' => true]);
    }

    public function test_un_montant_recu_insuffisant_est_refuse_sur_l_ecran_avec_la_saisie_conservee(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $cash = $this->cashBox($alpha);

        // C'était une page d'erreur 422 : la vente saisie était perdue.
        $this->actingAs($admin)->from(route('admin.commercial.pos.create'))->post(route('admin.commercial.pos.store'), [
            'lines' => [['item_name' => 'Statuts de SARL', 'quantity' => 1, 'unit_price' => 177000]],
            'payment_method' => 'cash', 'paid_amount' => 150000, 'cash_account_id' => $cash->id,
        ])->assertRedirect(route('admin.commercial.pos.create'))
            ->assertSessionHasErrors(['paid_amount' => 'Le montant reçu (' . money(150000) . ') est inférieur au total de la vente (' . money(177000) . ').']);

        $this->assertSame(0, PosSale::withoutGlobalScope('entreprise')->count());
        $this->actingAs($admin)->get(route('admin.commercial.pos.create'))->assertSee('Statuts de SARL');
    }

    public function test_une_vente_libre_en_especes_annonce_la_monnaie_et_une_vente_par_banque_est_au_montant_exact(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $cash = $this->cashBox($alpha);
        $bank = BankAccount::withoutGlobalScope('entreprise')->create(['entreprise_id' => $alpha->id, 'name' => 'NSIA', 'bank_name' => 'NSIA', 'account_number' => '1', 'account_type' => 'compte_courant', 'current_balance' => 0, 'status' => 'credit']);

        // Une désignation hors catalogue est acceptée : l'ancien écran imposait un service.
        $this->actingAs($admin)->post(route('admin.commercial.pos.store'), [
            'lines' => [['item_name' => 'Timbre fiscal', 'quantity' => 2, 'unit_price' => 1000]],
            'payment_method' => 'cash', 'paid_amount' => 5000, 'cash_account_id' => $cash->id,
        ])->assertRedirect(route('admin.commercial.pos.create'))
            ->assertSessionHas('pos_change', money(3000));

        $this->actingAs($admin)->post(route('admin.commercial.pos.store'), [
            'lines' => [['item_name' => 'Domiciliation annuelle', 'quantity' => 1, 'unit_price' => 354000]],
            'payment_method' => 'bank', 'paid_amount' => 500000, 'bank_account_id' => $bank->id,
        ])->assertSessionHasNoErrors();

        $sale = PosSale::withoutGlobalScope('entreprise')->where('payment_method', 'bank')->firstOrFail();
        $this->assertSame(354000.0, (float) $sale->paid_amount);
        $this->assertSame(0.0, (float) $sale->change_amount);
        $this->assertSame(354000.0, (float) $bank->fresh()->current_balance);
    }

    public function test_modifier_une_vente_met_a_jour_la_caisse_la_tva_et_le_journal_puis_l_annulation_remet_tout_a_zero(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $cash = $this->cashBox($alpha);
        $ledger = app(LedgerService::class);
        $this->actingAs($admin)->post(route('admin.commercial.pos.store'), [
            'lines' => [['item_name' => 'Conseil', 'quantity' => 1, 'unit_price' => 118000]],
            'payment_method' => 'cash', 'paid_amount' => 118000, 'cash_account_id' => $cash->id,
        ])->assertSessionHasNoErrors();
        $sale = PosSale::withoutGlobalScope('entreprise')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.commercial.pos.update', $sale), [
            'lines' => [['item_name' => 'Conseil', 'quantity' => 2, 'unit_price' => 118000]], 'paid_amount' => 236000,
        ])->assertSessionHasNoErrors();

        $sale->refresh();
        $this->assertSame(236000.0, (float) $cash->fresh()->balance);
        // La TVA restait celle de la première saisie, et le journal ignorait la modification.
        $this->assertSame(200000.0, (float) $sale->total_ht);
        $this->assertSame(36000.0, (float) $sale->tax_amount);
        $this->assertSame(-200000.0, $ledger->balance($alpha->id, LedgerAccount::ROLE_SALES));
        $this->assertSame(-36000.0, $ledger->balance($alpha->id, LedgerAccount::ROLE_VAT_COLLECTED));
        $this->assertSame(236000.0, $ledger->balance($alpha->id, LedgerAccount::ROLE_CASH));

        $this->actingAs($admin)->post(route('admin.commercial.pos.cancel', $sale))->assertSessionHasNoErrors();
        $this->assertSame(0.0, (float) $cash->fresh()->balance);
        $this->assertSame(0.0, $ledger->balance($alpha->id, LedgerAccount::ROLE_SALES));
        $this->assertSame(0.0, $ledger->balance($alpha->id, LedgerAccount::ROLE_VAT_COLLECTED));
        $this->assertSame(0.0, $ledger->balance($alpha->id, LedgerAccount::ROLE_CASH));
    }

    public function test_le_mode_de_paiement_ne_change_pas_et_une_vente_annulee_ne_s_ouvre_pas_en_modification(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $cash = $this->cashBox($alpha);
        $this->actingAs($admin)->post(route('admin.commercial.pos.store'), [
            'lines' => [['item_name' => 'Conseil', 'quantity' => 1, 'unit_price' => 59000]],
            'payment_method' => 'cash', 'paid_amount' => 59000, 'cash_account_id' => $cash->id,
        ]);
        $sale = PosSale::withoutGlobalScope('entreprise')->firstOrFail();

        // L'écran proposait de changer le mode et la caisse, que le serveur ignorait (et exigeait une caisse pour enregistrer).
        $this->actingAs($admin)->get(route('admin.commercial.pos.edit', $sale))
            ->assertOk()
            ->assertSee('Le mode de paiement ne se modifie pas')
            ->assertDontSee('type="radio" name="payment_method"', false);

        $this->actingAs($admin)->post(route('admin.commercial.pos.cancel', $sale));
        $this->actingAs($admin)->get(route('admin.commercial.pos.edit', $sale))
            ->assertRedirect(route('admin.commercial.pos.show', $sale))
            ->assertSessionHasErrors('sale');
    }
}
