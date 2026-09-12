<?php

namespace Tests\Feature;

use App\Models\AdminCall;
use App\Models\Entreprise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Journal des appels : durée réservée aux appels aboutis, suite donnée à un
 * appel, appels à passer toujours visibles et cloisonnement.
 */
class AdministrationCallsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-14 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function logCall(Entreprise $entreprise, array $attributes = []): AdminCall
    {
        $call = new AdminCall(array_merge([
            'contact_name' => 'Sogex Distribution',
            'phone' => '+225 07 08 11 22 33',
            'subject' => 'Relance de la facture',
            'direction' => 'incoming',
            'status' => 'missed',
            'call_at' => '2026-09-14 08:20:00',
        ], $attributes));
        $call->entreprise_id = $entreprise->id;
        $call->saveQuietly();

        return $call;
    }

    public function test_seul_un_appel_abouti_porte_une_duree(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);

        $this->actingAs($admin)->post(route('admin.administration.calls.store'), [
            'contact_name' => 'Banque Atlantique', 'direction' => 'outgoing', 'call_at' => '2026-09-14T09:00',
            'status' => 'missed', 'duration' => 12,
        ])->assertRedirect();

        $call = AdminCall::withoutGlobalScope('entreprise')->firstWhere('contact_name', 'Banque Atlantique');
        $this->assertNull($call->duration, 'Un appel manqué ne porte pas de durée.');

        $this->actingAs($admin)->put(route('admin.administration.calls.update', $call), [
            'contact_name' => 'Banque Atlantique', 'direction' => 'outgoing', 'call_at' => '2026-09-14T09:00',
            'status' => 'completed', 'duration' => 12,
        ])->assertRedirect();
        $this->assertSame(12, $call->refresh()->duration);
    }

    public function test_un_appel_note_abouti_prend_l_heure_du_moment_s_il_etait_a_venir(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $call = $this->logCall($alpha, ['status' => 'planned', 'call_at' => '2026-09-16 15:00:00', 'duration' => 30]);

        $this->actingAs($admin)->from(route('admin.administration.calls'))
            ->post(route('admin.administration.calls.status', $call), ['status' => 'completed'])
            ->assertSessionHas('success');

        $call->refresh();
        $this->assertSame('completed', $call->status);
        $this->assertSame('2026-09-14 10:00:00', $call->call_at->format('Y-m-d H:i:s'));

        // Le même état deux fois de suite est refusé.
        $this->actingAs($admin)->from(route('admin.administration.calls'))
            ->post(route('admin.administration.calls.status', $call), ['status' => 'completed'])
            ->assertSessionHas('error');

        // Noté manqué, il perd sa durée.
        $this->actingAs($admin)->from(route('admin.administration.calls'))
            ->post(route('admin.administration.calls.status', $call), ['status' => 'missed']);
        $this->assertNull($call->refresh()->duration);
    }

    public function test_les_appels_a_passer_restent_visibles_hors_periode(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $this->logCall($alpha, ['contact_name' => 'Appel à passer', 'status' => 'planned', 'call_at' => '2026-07-02 09:00:00']);
        $this->logCall($alpha, ['contact_name' => 'Appel de juillet', 'status' => 'completed', 'call_at' => '2026-07-03 09:00:00', 'duration' => 8]);
        $this->logCall($alpha, ['contact_name' => 'Appel du mois', 'status' => 'completed', 'call_at' => '2026-09-02 09:00:00', 'duration' => 15]);

        $this->actingAs($admin)->get(route('admin.administration.calls'))
            ->assertOk()
            ->assertSee('Appel à passer')
            ->assertSee('Appel du mois')
            ->assertDontSee('Appel de juillet');
    }

    public function test_les_sens_et_etats_sont_affiches_en_francais(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $this->logCall($alpha, ['direction' => 'outgoing', 'status' => 'completed', 'duration' => 90]);

        $this->actingAs($admin)->get(route('admin.administration.calls'))
            ->assertOk()
            ->assertSee('Sortant')
            ->assertSee('Abouti')
            ->assertSee('1 h 30')
            ->assertDontSee('>outgoing<', false);
    }

    public function test_le_journal_est_cloisonne_par_entreprise(): void
    {
        $alpha = $this->makeCompany('alpha');
        $beta = $this->makeCompany('beta');
        $admin = $this->makeAdmin($alpha);
        $this->logCall($alpha, ['contact_name' => 'Contact Alpha']);
        $chezBeta = $this->logCall($beta, ['contact_name' => 'Contact Beta']);

        $this->actingAs($admin)->get(route('admin.administration.calls'))
            ->assertOk()
            ->assertSee('Contact Alpha')
            ->assertDontSee('Contact Beta');

        $this->actingAs($admin)->delete(route('admin.administration.calls.destroy', $chezBeta))->assertNotFound();
        $this->assertDatabaseHas('admin_calls', ['id' => $chezBeta->id]);
    }
}
