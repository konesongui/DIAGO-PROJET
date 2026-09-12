<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\Entreprise;
use App\Models\LedgerAccount;
use App\Models\PosSale;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Point de vente : numéro de ticket, liste par période, annulation qui
 * contrepasse la vente au journal, actions selon l'état.
 */
class PosSalesPageTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private function sell(User $admin, CashAccount $cash, float $price = 118000): PosSale
    {
        $this->actingAs($admin)->post(route('admin.commercial.pos.store'), [
            'lines' => [['item_name' => 'Rédaction des statuts', 'quantity' => 1, 'unit_price' => $price]],
            'payment_method' => 'cash', 'paid_amount' => $price, 'cash_account_id' => $cash->id,
        ])->assertSessionHasNoErrors();

        return PosSale::withoutGlobalScope('entreprise')->latest('id')->firstOrFail();
    }

    private function cashBox(Entreprise $entreprise): CashAccount
    {
        return CashAccount::withoutGlobalScope('entreprise')->create(['entreprise_id' => $entreprise->id, 'name' => 'Caisse principale', 'balance' => 0, 'is_active' => true]);
    }

    public function test_chaque_vente_recoit_un_numero_et_la_liste_couvre_le_mois_par_defaut(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $cash = $this->cashBox($alpha);

        Carbon::setTestNow(now()->subMonthsNoOverflow(2));
        $old = $this->sell($admin, $cash, 5000);
        Carbon::setTestNow();
        $recent = $this->sell($admin, $cash);

        $this->assertMatchesRegularExpression('/^POS-\d{8}-0001$/', $recent->reference);

        // La liste se limitait aux 20 dernières ventes, sans accès aux précédentes.
        $this->actingAs($admin)->get(route('admin.commercial.module', 'point-de-vente'))
            ->assertOk()
            ->assertSee($recent->reference)
            ->assertDontSee($old->reference)
            ->assertDontSee(' XOF</td>', false);

        $this->actingAs($admin)->get(route('admin.commercial.module', 'point-de-vente') . '?du=' . $old->created_at->format('Y-m-d') . '&au=' . now()->format('Y-m-d'))
            ->assertSee($old->reference)
            ->assertSee($recent->reference);
    }

    public function test_annuler_une_vente_rend_l_argent_et_contrepasse_la_vente_au_journal(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $cash = $this->cashBox($alpha);
        $sale = $this->sell($admin, $cash);
        $ledger = app(LedgerService::class);
        $this->assertSame(-100000.0, $ledger->balance($alpha->id, LedgerAccount::ROLE_SALES));

        $this->actingAs($admin)->post(route('admin.commercial.pos.cancel', $sale))->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $sale->fresh()->status);
        $this->assertSame(0.0, (float) $cash->fresh()->balance);
        // Avant : la caisse était vidée mais le journal gardait la vente, sa TVA et son encaissement.
        $this->assertSame(0.0, $ledger->balance($alpha->id, LedgerAccount::ROLE_SALES));
        $this->assertSame(0.0, $ledger->balance($alpha->id, LedgerAccount::ROLE_VAT_COLLECTED));
        $this->assertSame(0.0, $ledger->balance($alpha->id, LedgerAccount::ROLE_CASH));
    }

    public function test_les_actions_suivent_l_etat_et_les_refus_restent_lisibles(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $cash = $this->cashBox($alpha);
        $completed = $this->sell($admin, $cash);
        $cancelled = $this->sell($admin, $cash, 59000);
        $this->actingAs($admin)->post(route('admin.commercial.pos.cancel', $cancelled))->assertSessionHasNoErrors();

        $html = $this->actingAs($admin)->get(route('admin.commercial.module', 'point-de-vente'))->getContent();
        // L'ancienne liste proposait « Modifier » et « Annuler » sur une vente annulée, « Supprimer » sur une vente terminée : erreurs 422.
        $this->assertStringNotContainsString(route('admin.commercial.pos.edit', $cancelled), $html);
        $this->assertStringNotContainsString(route('admin.commercial.pos.cancel', $cancelled), $html);
        // L'adresse de suppression est aussi celle de la fiche : on vise le formulaire.
        $this->assertStringNotContainsString('action="' . route('admin.commercial.pos.destroy', $completed) . '"', $html);
        $this->assertStringContainsString('action="' . route('admin.commercial.pos.destroy', $cancelled) . '"', $html);

        $this->actingAs($admin)->post(route('admin.commercial.pos.cancel', $cancelled))->assertSessionHasErrors(['sale' => 'Cette vente est déjà annulée.']);
        $this->actingAs($admin)->delete(route('admin.commercial.pos.destroy', $completed))->assertSessionHasErrors('sale');
        $this->assertNotNull($completed->fresh());

        $this->actingAs($admin)->delete(route('admin.commercial.pos.destroy', $cancelled))->assertSessionHasNoErrors();
        $this->assertNull($cancelled->fresh());
    }

    public function test_une_vente_d_une_autre_entreprise_ne_peut_pas_etre_annulee(): void
    {
        $alpha = $this->makeCompany('alpha');
        $beta = $this->makeCompany('beta');
        $sale = $this->sell($this->makeAdmin($beta), $this->cashBox($beta));

        $this->actingAs($this->makeAdmin($alpha))->post(route('admin.commercial.pos.cancel', $sale))->assertNotFound();
        $this->assertSame('completed', $sale->fresh()->status);
    }

    public function test_les_chiffres_de_gestion_sont_reserves_a_l_encadrement(): void
    {
        $alpha = $this->makeCompany('alpha');
        $this->sell($this->makeAdmin($alpha), $this->cashBox($alpha));
        $cashier = User::create([
            'name' => 'Caissière', 'email' => 'caisse@alpha.test', 'password' => bcrypt('password'), 'entreprise_id' => $alpha->id, 'is_active' => true,
            'role_id' => \App\Models\Role::firstOrCreate(['name' => 'standard_user'], ['label' => 'Utilisateur Standard'])->id,
            'permissions' => ['commercial' => ['view' => true, 'edit' => true]],
        ]);
        $manager = User::create([
            'name' => 'Responsable', 'email' => 'manager@alpha.test', 'password' => bcrypt('password'), 'entreprise_id' => $alpha->id, 'is_active' => true,
            'role_id' => \App\Models\Role::firstOrCreate(['name' => 'manager'], ['label' => 'Manager'])->id,
            'permissions' => ['commercial' => ['view' => true, 'edit' => true]],
        ]);

        // La caisse voit les ventes, pas le chiffre d'affaires ni les encaissements cumulés.
        $this->actingAs($cashier)->get(route('admin.commercial.module', 'point-de-vente'))
            ->assertOk()
            ->assertSee('POS-')
            ->assertDontSee('Chiffre d’affaires TTC')
            ->assertDontSee('Encaissé en espèces');

        $this->actingAs($manager)->get(route('admin.commercial.module', 'point-de-vente'))
            ->assertOk()
            ->assertSee('Chiffre d’affaires TTC')
            ->assertSee('Encaissé en espèces');
    }

    public function test_la_fiche_detaille_la_vente_et_ses_actions_selon_l_etat(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $sale = $this->sell($admin, $this->cashBox($alpha));

        // L'ancienne fiche : ni numéro, ni TVA, ni caisse, « XOF » écrit en dur.
        $this->actingAs($admin)->get(route('admin.commercial.pos.show', $sale))
            ->assertOk()
            ->assertSee('Vente ' . $sale->reference)
            ->assertSee('Vente terminée')
            ->assertSeeInOrder(['Total HT', money(100000), 'TVA (18 %)', money(18000), 'Total TTC', money(118000)])
            ->assertSee('Caisse principale')
            ->assertSee(route('admin.commercial.pos.edit', $sale))
            ->assertSee('id="cancelSaleModal"', false)
            ->assertDontSee(' XOF');

        $this->actingAs($admin)->post(route('admin.commercial.pos.cancel', $sale));
        $this->actingAs($admin)->get(route('admin.commercial.pos.show', $sale))
            ->assertSee('Vente annulée')
            ->assertDontSee(route('admin.commercial.pos.edit', $sale))
            ->assertDontSee('id="cancelSaleModal"', false);

        // Supprimer depuis la fiche ramène à la liste, et non à une vente qui n'existe plus.
        $this->actingAs($admin)->delete(route('admin.commercial.pos.destroy', $sale))
            ->assertRedirect(route('admin.commercial.module', 'point-de-vente'));
    }
}
