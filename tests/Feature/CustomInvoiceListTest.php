<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\CustomInvoice;
use App\Models\Entreprise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Factures personnalisées : état lisible, paiement par banque réellement
 * encaissé, reste dû net des avoirs.
 */
class CustomInvoiceListTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private function makeInvoice(Entreprise $entreprise, array $attributes = []): CustomInvoice
    {
        return CustomInvoice::withoutGlobalScope('entreprise')->create($attributes + [
            'entreprise_id' => $entreprise->id, 'reference' => 'FC-' . uniqid(), 'client_name' => 'Éditions du Plateau',
            'quote_date' => now()->toDateString(), 'items' => [], 'total_ht' => 1000000, 'tax_amount' => 180000,
            'total_ttc' => 1180000, 'paid_amount' => 0, 'credited_amount' => 0, 'status' => 'draft', 'tax_regime' => 'standard',
        ]);
    }

    public function test_l_etat_de_chaque_facture_est_deduit_des_montants_et_des_dates(): void
    {
        $alpha = $this->makeCompany('alpha');
        $this->makeInvoice($alpha);
        $this->makeInvoice($alpha, ['issued_at' => now()]);
        $this->makeInvoice($alpha, ['issued_at' => now(), 'paid_amount' => 300000]);
        $this->makeInvoice($alpha, ['issued_at' => now(), 'paid_amount' => 1180000, 'status' => 'paid']);
        $this->makeInvoice($alpha, ['issued_at' => now(), 'credited_amount' => 1180000]);

        $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.commercial.custom-invoice.index'))
            ->assertOk()
            ->assertSee('Brouillons (1)')
            ->assertSee('Impayées (1)')
            ->assertSee('Partielles (1)')
            ->assertSee('Payées (1)')
            ->assertSee('Annulées (1)');
    }

    public function test_un_paiement_par_banque_credite_le_compte_choisi(): void
    {
        $alpha = $this->makeCompany('alpha');
        $invoice = $this->makeInvoice($alpha, ['issued_at' => now()]);
        $bank = BankAccount::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'name' => 'Compte courant', 'bank_name' => 'NSIA', 'account_number' => '1',
            'account_type' => 'compte_courant', 'current_balance' => 100000, 'status' => 'credit',
        ]);

        $this->actingAs($this->makeAdmin($alpha))->post(route('admin.commercial.custom-invoice.payment', $invoice), [
            'amount' => 500000, 'payment_method' => 'bank', 'bank_account_id' => $bank->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(600000.0, (float) $bank->fresh()->current_balance);
        $this->assertTrue(BankTransaction::withoutGlobalScope('entreprise')->where('bank_account_id', $bank->id)->where('amount', 500000)->exists());
        $this->assertSame(500000.0, (float) $invoice->fresh()->paid_amount);
    }

    public function test_le_reste_du_tient_compte_des_avoirs_et_une_facture_annulee_refuse_les_paiements(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $cash = \App\Models\CashAccount::withoutGlobalScope('entreprise')->create(['entreprise_id' => $alpha->id, 'name' => 'Caisse', 'balance' => 0, 'is_active' => true]);
        $partlyCredited = $this->makeInvoice($alpha, ['issued_at' => now(), 'credited_amount' => 180000]);

        // 1 180 000 facturés, 180 000 annulés par avoir : 1 000 000 suffisent à solder.
        $this->actingAs($admin)->post(route('admin.commercial.custom-invoice.payment', $partlyCredited), [
            'amount' => 1000000, 'payment_method' => 'cash', 'cash_account_id' => $cash->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame('paid', $partlyCredited->fresh()->status);

        $cancelled = $this->makeInvoice($alpha, ['issued_at' => now(), 'credited_amount' => 1180000]);
        $this->actingAs($admin)->post(route('admin.commercial.custom-invoice.payment', $cancelled), [
            'amount' => 1000, 'payment_method' => 'cash', 'cash_account_id' => $cash->id,
        ])->assertSessionHasErrors('amount');
        $this->assertSame(0.0, (float) $cancelled->fresh()->paid_amount);
    }

    public function test_une_facture_emise_ne_propose_plus_modifier_ni_supprimer(): void
    {
        $alpha = $this->makeCompany('alpha');
        $issued = $this->makeInvoice($alpha, ['issued_at' => now()]);
        $draft = $this->makeInvoice($alpha);

        $html = $this->actingAs($this->makeAdmin($alpha))->get(route('admin.commercial.custom-invoice.index'))->getContent();

        $this->assertStringNotContainsString(route('admin.commercial.custom-invoice.edit', $issued), $html);
        $this->assertStringContainsString(route('admin.commercial.custom-invoice.edit', $draft), $html);
        $this->assertStringContainsString(route('admin.commercial.custom-invoice.issue', $draft), $html);
    }
}
