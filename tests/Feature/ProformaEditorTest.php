<?php

namespace Tests\Feature;

use App\Models\CommercialClient;
use App\Models\CommercialProforma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Éditeur de proforma : enregistrement possible avec ce que le formulaire
 * envoie, remises plafonnées, régime fiscal conservé, client créé à la volée.
 */
class ProformaEditorTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    /** Exactement les champs envoyés par l'éditeur (sans « item_type », qui bloquait tout enregistrement). */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'creation_date' => '2026-09-11', 'due_date' => '2026-10-11', 'payment_method' => 'Virement', 'subject' => 'Audit',
            'lines' => [
                ['type' => 'service', 'category' => 'Conseil', 'item_name' => 'Audit des systèmes', 'unit' => 'forfait', 'quantity' => 1, 'unit_price' => 1000000, 'discount' => 10, 'discount_type' => 'percent'],
                ['type' => 'product', 'category' => '', 'item_name' => 'Licence', 'unit' => 'licence', 'quantity' => 2, 'unit_price' => 50000, 'discount' => 0, 'discount_type' => 'amount'],
            ],
        ];
    }

    public function test_la_proforma_s_enregistre_avec_son_numero_ses_lignes_et_sa_tva(): void
    {
        $alpha = $this->makeCompany('alpha');
        $client = CommercialClient::withoutGlobalScope('entreprise')->create(['entreprise_id' => $alpha->id, 'name' => 'Société Générale CI']);

        $this->actingAs($this->makeAdmin($alpha))
            ->post(route('admin.commercial.proforma.store'), $this->payload(['client_id' => (string) $client->id, 'tax_rate_id' => $this->rate($alpha, 'TVA18')->id]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Proforma PRO-20260911-0001 enregistrée.');

        $proforma = CommercialProforma::withoutGlobalScope('entreprise')->with('lines')->firstOrFail();
        // 1 000 000 − 10 % + 100 000 = 1 000 000 HT net ; TVA 18 %.
        $this->assertSame(1100000.0, (float) $proforma->total_ht);
        $this->assertSame(100000.0, (float) $proforma->total_discount);
        $this->assertSame(1000000.0, (float) $proforma->net_ht);
        $this->assertSame(180000.0, (float) $proforma->tax_amount);
        $this->assertSame(1180000.0, (float) $proforma->total_ttc);
        $this->assertSame('standard', $proforma->tax_regime);
        $this->assertSame(['service', 'product'], $proforma->lines->pluck('type')->all());
        $this->assertSame('forfait', $proforma->lines->first()->unit);
    }

    public function test_un_taux_exonere_est_conserve_comme_tel(): void
    {
        $alpha = $this->makeCompany('alpha');
        $client = CommercialClient::withoutGlobalScope('entreprise')->create(['entreprise_id' => $alpha->id, 'name' => 'Ambassade']);

        // L'ancien éditeur n'envoyait qu'un pourcentage : un client exonéré devenait « sans taxe », régime perdu.
        $this->actingAs($this->makeAdmin($alpha))
            ->post(route('admin.commercial.proforma.store'), $this->payload(['client_id' => $client->id, 'tax_rate_id' => $this->rate($alpha, 'EXO')->id]))
            ->assertSessionHasNoErrors();

        $proforma = CommercialProforma::withoutGlobalScope('entreprise')->firstOrFail();
        $this->assertSame('exempt', $proforma->tax_regime);
        $this->assertSame(0.0, (float) $proforma->tax_amount);
        $this->assertSame(1000000.0, (float) $proforma->total_ttc);
    }

    public function test_une_remise_ne_depasse_jamais_le_montant_de_sa_ligne(): void
    {
        $alpha = $this->makeCompany('alpha');
        $client = CommercialClient::withoutGlobalScope('entreprise')->create(['entreprise_id' => $alpha->id, 'name' => 'Orange']);

        $this->actingAs($this->makeAdmin($alpha))->post(route('admin.commercial.proforma.store'), $this->payload([
            'client_id' => $client->id,
            'lines' => [
                ['type' => 'service', 'item_name' => 'Remise en montant trop forte', 'quantity' => 1, 'unit_price' => 100000, 'discount' => 500000, 'discount_type' => 'amount'],
                ['type' => 'service', 'item_name' => 'Remise de 150 %', 'quantity' => 1, 'unit_price' => 100000, 'discount' => 150, 'discount_type' => 'percent'],
                ['type' => 'service', 'item_name' => 'Sans remise', 'quantity' => 1, 'unit_price' => 100000],
            ],
        ]))->assertSessionHasNoErrors();

        $proforma = CommercialProforma::withoutGlobalScope('entreprise')->with('lines')->firstOrFail();
        // Avant : 500 000 + 150 000 de remise sur 300 000, soit un HT net négatif.
        $this->assertSame(200000.0, (float) $proforma->total_discount);
        $this->assertSame(100000.0, (float) $proforma->net_ht);
        $this->assertSame([0.0, 0.0, 100000.0], $proforma->lines->pluck('line_total')->map(fn ($v) => (float) $v)->all());
    }

    public function test_un_nouveau_client_est_ajoute_au_carnet_et_son_nom_est_exige(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);

        // Un client manquant renvoyait une page d'erreur 422 : la saisie était perdue.
        $this->actingAs($admin)->from(route('admin.commercial.proforma.create'))
            ->post(route('admin.commercial.proforma.store'), $this->payload(['client_id' => 'new']))
            ->assertRedirect(route('admin.commercial.proforma.create'))
            ->assertSessionHasErrors(['client_id' => 'Choisissez un client, ou saisissez le nom du nouveau client.']);
        $this->assertSame(0, CommercialProforma::withoutGlobalScope('entreprise')->count());

        $this->actingAs($admin)->post(route('admin.commercial.proforma.store'), $this->payload([
            'client_id' => 'new', 'new_client_name' => 'Cabinet Kouassi', 'new_client_email' => 'contact@kouassi.ci',
        ]))->assertSessionHasNoErrors();

        $client = CommercialClient::withoutGlobalScope('entreprise')->where('name', 'Cabinet Kouassi')->firstOrFail();
        $this->assertSame('contact@kouassi.ci', $client->email);
        $this->assertSame($client->id, CommercialProforma::withoutGlobalScope('entreprise')->value('client_id'));
    }

    public function test_la_saisie_est_reprise_apres_une_erreur_et_la_modification_garde_le_numero(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $client = CommercialClient::withoutGlobalScope('entreprise')->create(['entreprise_id' => $alpha->id, 'name' => 'Sotra BTP']);

        $this->actingAs($admin)->from(route('admin.commercial.proforma.create'))
            ->post(route('admin.commercial.proforma.store'), $this->payload(['client_id' => $client->id, 'due_date' => '2026-09-01']))
            ->assertSessionHasErrors(['due_date' => 'La date limite ne peut pas précéder la date de la proforma.']);
        $this->actingAs($admin)->get(route('admin.commercial.proforma.create'))
            ->assertSee('Audit des systèmes')
            ->assertSee('value="' . $client->id . '" selected', false);

        $this->actingAs($admin)->post(route('admin.commercial.proforma.store'), $this->payload(['client_id' => $client->id]))->assertSessionHasNoErrors();
        $proforma = CommercialProforma::withoutGlobalScope('entreprise')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.commercial.proforma.update', $proforma), $this->payload(['client_id' => $client->id, 'subject' => 'Audit révisé']))
            ->assertSessionHasNoErrors();
        $this->assertSame('PRO-20260911-0001', $proforma->fresh()->reference);
        $this->assertSame('Audit révisé', $proforma->fresh()->subject);
        $this->assertSame(2, $proforma->lines()->count());
    }

    public function test_une_proforma_d_une_autre_entreprise_reste_inaccessible(): void
    {
        $alpha = $this->makeCompany('alpha');
        $beta = $this->makeCompany('beta');
        $client = CommercialClient::withoutGlobalScope('entreprise')->create(['entreprise_id' => $beta->id, 'name' => 'Client beta']);
        $this->actingAs($this->makeAdmin($beta))->post(route('admin.commercial.proforma.store'), $this->payload(['client_id' => $client->id]))->assertSessionHasNoErrors();
        $proforma = CommercialProforma::withoutGlobalScope('entreprise')->firstOrFail();

        $this->actingAs($this->makeAdmin($alpha))->get(route('admin.commercial.proforma.edit', $proforma))->assertNotFound();
    }
}
