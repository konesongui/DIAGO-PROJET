<?php

namespace Tests\Feature;

use App\Models\CommercialClient;
use App\Models\CommercialProforma;
use App\Models\Entreprise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Liste des proformas : numéro, état lisible, envoi par e-mail à l'adresse du
 * client, suppression groupée cloisonnée par entreprise.
 */
class ProformasPageTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private function makeProforma(Entreprise $entreprise, array $attributes = []): CommercialProforma
    {
        $client = CommercialClient::withoutGlobalScope('entreprise')->create(['entreprise_id' => $entreprise->id, 'name' => 'Orange Côte d’Ivoire', 'email' => 'dsi@orange.ci']);

        return CommercialProforma::withoutGlobalScope('entreprise')->create($attributes + [
            'entreprise_id' => $entreprise->id, 'reference' => 'PRO-' . uniqid(), 'client_id' => $client->id, 'client_name' => $client->name,
            'creation_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'total_ht' => 1000000, 'total_discount' => 0, 'net_ht' => 1000000, 'tax_rate' => 18, 'tax_amount' => 180000, 'total_ttc' => 1180000, 'status' => 'draft',
        ]);
    }

    public function test_la_liste_montre_le_numero_et_l_etat_de_chaque_proforma(): void
    {
        $alpha = $this->makeCompany('alpha');
        $this->makeProforma($alpha, ['reference' => 'PRO-20260911-0001']);
        $this->makeProforma($alpha, ['status' => 'sent']);
        // Envoyée mais date limite passée : expirée.
        $this->makeProforma($alpha, ['status' => 'sent', 'creation_date' => now()->subDays(40)->toDateString(), 'due_date' => now()->subDays(10)->toDateString()]);

        $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.commercial.module', 'proforma'))
            ->assertOk()
            ->assertSee('PRO-20260911-0001')
            ->assertSee('Brouillons (1)')
            ->assertSee('Envoyées (1)')
            ->assertSee('Expirées (1)')
            ->assertSee(money(1180000))
            // L'adresse du client est proposée à l'envoi (elle était toujours vide).
            ->assertSee('data-email="dsi@orange.ci"', false)
            // Les montants étaient suivis de « XOF » écrit en dur.
            ->assertDontSee(' XOF</td>', false);
    }

    public function test_l_envoi_par_email_part_avec_le_numero_et_le_montant_formate(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $proforma = $this->makeProforma($alpha, ['reference' => 'PRO-20260911-0002']);

        $this->actingAs($admin)->post(route('admin.commercial.proforma.email', $proforma), ['email' => 'pas-une-adresse'])
            ->assertSessionHasErrors(['email' => 'Indiquez une adresse e-mail valide pour le destinataire.']);
        $this->assertSame('draft', $proforma->fresh()->status);

        $this->actingAs($admin)->post(route('admin.commercial.proforma.email', $proforma), ['email' => 'dsi@orange.ci'])->assertSessionHasNoErrors();

        $sent = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $email = $sent->first()->getOriginalMessage();
        $this->assertSame('Votre proforma PRO-20260911-0002', $email->getSubject());
        $this->assertStringContainsString(money(1180000), $email->getTextBody());
        $this->assertStringNotContainsString('XOF', $email->getTextBody());
        $this->assertSame('sent', $proforma->fresh()->status);
    }

    public function test_une_nouvelle_proforma_recoit_un_numero(): void
    {
        $alpha = $this->makeCompany('alpha');
        $client = CommercialClient::withoutGlobalScope('entreprise')->create(['entreprise_id' => $alpha->id, 'name' => 'Sotra BTP']);

        $this->actingAs($this->makeAdmin($alpha))->post(route('admin.commercial.proforma.store'), [
            'client_id' => $client->id, 'creation_date' => '2026-09-11',
            'lines' => [['type' => 'service', 'item_name' => 'Audit', 'quantity' => 1, 'unit_price' => 100000]],
        ])->assertSessionHasNoErrors();

        $this->assertSame('PRO-20260911-0001', CommercialProforma::withoutGlobalScope('entreprise')->value('reference'));
    }

    public function test_la_suppression_groupee_ne_touche_que_l_entreprise_courante(): void
    {
        $alpha = $this->makeCompany('alpha');
        $beta = $this->makeCompany('beta');
        $own = $this->makeProforma($alpha);
        $other = $this->makeProforma($beta);

        $this->actingAs($this->makeAdmin($alpha))
            ->delete(route('admin.commercial.proforma.bulkDestroy'), ['proforma_ids' => [$own->id, $other->id]])
            ->assertSessionHasNoErrors();

        $this->assertNull(CommercialProforma::withoutGlobalScope('entreprise')->find($own->id));
        $this->assertNotNull(CommercialProforma::withoutGlobalScope('entreprise')->find($other->id));
    }
}
