<?php

namespace Tests\Feature;

use App\Models\CommercialClient;
use App\Models\CommercialInvoice;
use App\Models\CommercialQuote;
use App\Models\Entreprise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Liste des devis : états (en attente, validé, expiré), validation avec bon de
 * commande, suppression limitée aux devis en attente, duplication datée du jour.
 */
class QuotesPageTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private function makeQuote(Entreprise $entreprise, string $status, string $date, ?string $dueDate, float $ttc = 1180000): CommercialQuote
    {
        $client = CommercialClient::withoutGlobalScope('entreprise')->firstOrCreate(
            ['entreprise_id' => $entreprise->id, 'name' => 'Client test'],
            ['phone' => '0102030405', 'email' => 'client@test.ci']
        );

        return CommercialQuote::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'reference' => 'DEV-' . uniqid(), 'client_id' => $client->id, 'client_name' => $client->name,
            'quote_date' => $date, 'due_date' => $dueDate, 'total_ht' => $ttc / 1.18, 'net_ht' => $ttc / 1.18, 'tax_rate' => 18,
            'tax_amount' => $ttc - $ttc / 1.18, 'total_ttc' => $ttc, 'tax_regime' => 'standard',
            'lines' => [['item_name' => 'Audit', 'quantity' => 1, 'unit_price' => $ttc / 1.18]], 'status' => $status,
        ]);
    }

    public function test_un_devis_en_attente_dont_la_date_limite_est_passee_est_expire(): void
    {
        $alpha = $this->makeCompany('alpha');
        $this->makeQuote($alpha, 'pending_validation', now()->subDays(40)->toDateString(), now()->subDays(10)->toDateString());
        $this->makeQuote($alpha, 'pending_validation', now()->subDays(2)->toDateString(), now()->addDays(28)->toDateString());
        $this->makeQuote($alpha, 'validated', now()->subDays(60)->toDateString(), now()->subDays(30)->toDateString());

        $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.commercial.module', 'devis'))
            ->assertOk()
            ->assertSee('Expirés (1)')
            ->assertSee('En attente (1)')
            ->assertSee('Validés (1)')
            ->assertSee('data-state="expired"', false);
    }

    public function test_un_devis_valide_ne_peut_pas_etre_supprime_ni_modifie_depuis_la_liste(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $pending = $this->makeQuote($alpha, 'pending_validation', now()->toDateString(), null);

        // La validation crée la commande et la livraison ; la facture suivrait la livraison.
        $this->actingAs($admin)->post(route('admin.commercial.quotes.validate', $pending), ['customer_order_code' => 'BC-118'])->assertSessionHasNoErrors();
        $this->assertSame('validated', $pending->fresh()->status);

        $html = $this->actingAs($admin)->get(route('admin.commercial.module', 'devis'))->getContent();
        $this->assertStringNotContainsString(route('admin.commercial.quotes.edit', $pending), $html);
        // L'adresse de suppression est un préfixe de celle d'impression : on cherche le formulaire exact.
        $this->assertStringNotContainsString('action="' . route('admin.commercial.quotes.destroy', $pending) . '"', $html);

        $this->actingAs($admin)->delete(route('admin.commercial.quotes.destroy', $pending))->assertSessionHasErrors('quote');
        $this->assertNotNull($pending->fresh(), 'Le devis validé, et avec lui sa commande, restent en base.');
        $this->assertSame(1, \App\Models\CommercialOrder::withoutGlobalScope('entreprise')->where('quote_id', $pending->id)->count());
    }

    public function test_un_devis_en_attente_peut_etre_supprime(): void
    {
        $alpha = $this->makeCompany('alpha');
        $quote = $this->makeQuote($alpha, 'pending_validation', now()->toDateString(), null);

        $this->actingAs($this->makeAdmin($alpha))->delete(route('admin.commercial.quotes.destroy', $quote))->assertSessionHasNoErrors();
        $this->assertNull($quote->fresh());
    }

    public function test_la_copie_d_un_devis_est_datee_du_jour_avec_la_meme_validite(): void
    {
        $alpha = $this->makeCompany('alpha');
        $quote = $this->makeQuote($alpha, 'validated', now()->subDays(90)->toDateString(), now()->subDays(60)->toDateString());

        $this->actingAs($this->makeAdmin($alpha))->post(route('admin.commercial.quotes.duplicate', $quote))->assertSessionHasNoErrors();

        $copy = CommercialQuote::withoutGlobalScope('entreprise')->where('entreprise_id', $alpha->id)->latest('id')->first();
        $this->assertNotSame($quote->id, $copy->id);
        $this->assertSame('pending_validation', $copy->status);
        $this->assertSame(now()->toDateString(), $copy->quote_date->toDateString());
        $this->assertSame(now()->addDays(30)->toDateString(), $copy->due_date->toDateString());
    }
}
