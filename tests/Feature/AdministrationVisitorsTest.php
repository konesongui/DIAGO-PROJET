<?php

namespace Tests\Feature;

use App\Models\AdminVisitor;
use App\Models\Entreprise;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Registre des visiteurs : état déduit de l'arrivée et du départ, pointage de
 * l'accueil, période d'affichage et cloisonnement entre entreprises.
 */
class AdministrationVisitorsTest extends TestCase
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

    private function visitor(Entreprise $entreprise, array $attributes = []): AdminVisitor
    {
        $visitor = new AdminVisitor(array_merge([
            'entreprise_id' => $entreprise->id,
            'name' => 'Awa Koné',
            'company' => 'Sogex',
            'purpose' => 'Remise de dossier',
            'host' => 'Direction',
        ], $attributes));
        $visitor->entreprise_id = $entreprise->id;
        $visitor->syncStatus();
        $visitor->saveQuietly();

        return $visitor;
    }

    public function test_l_etat_de_la_visite_suit_l_arrivee_et_le_depart(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);

        $this->actingAs($admin)->post(route('admin.administration.visitors.store'), [
            'name' => 'Awa Koné', 'company' => 'Sogex', 'purpose' => 'Remise de dossier',
        ])->assertRedirect();

        $visitor = AdminVisitor::withoutGlobalScope('entreprise')->firstWhere('name', 'Awa Koné');
        $this->assertSame('expected', $visitor->status, 'Sans arrivée, la visite est attendue.');

        $this->actingAs($admin)->from(route('admin.administration.visitors'))
            ->post(route('admin.administration.visitors.presence', $visitor), ['action' => 'arrivee']);
        $this->assertSame('inside', $visitor->refresh()->status);
        $this->assertSame('2026-09-14 10:00:00', $visitor->check_in_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow('2026-09-14 10:45:00');
        $this->actingAs($admin)->from(route('admin.administration.visitors'))
            ->post(route('admin.administration.visitors.presence', $visitor), ['action' => 'depart']);
        $this->assertSame('completed', $visitor->refresh()->status);
        $this->assertSame(45, $visitor->durationMinutes());
    }

    public function test_un_depart_sans_arrivee_est_refuse(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $visitor = $this->visitor($alpha);

        $this->actingAs($admin)->from(route('admin.administration.visitors'))
            ->post(route('admin.administration.visitors.presence', $visitor), ['action' => 'depart'])
            ->assertRedirect(route('admin.administration.visitors'))
            ->assertSessionHas('error');
        $this->assertNull($visitor->refresh()->check_out_at);

        // Même refus à la saisie manuelle.
        $this->actingAs($admin)->from(route('admin.administration.visitors'))
            ->put(route('admin.administration.visitors.update', $visitor), [
                'name' => 'Awa Koné', 'check_out_at' => '2026-09-14T11:00',
            ])->assertSessionHasErrors('check_out_at');
    }

    public function test_une_arrivee_deja_pointee_n_est_pas_reecrite(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $visitor = $this->visitor($alpha, ['check_in_at' => '2026-09-14 08:30:00']);

        $this->actingAs($admin)->from(route('admin.administration.visitors'))
            ->post(route('admin.administration.visitors.presence', $visitor), ['action' => 'arrivee'])
            ->assertSessionHas('error');
        $this->assertSame('08:30', $visitor->refresh()->check_in_at->format('H:i'));

        // Une visite déjà commencée ne s'annule pas.
        $this->actingAs($admin)->from(route('admin.administration.visitors'))
            ->post(route('admin.administration.visitors.presence', $visitor), ['action' => 'annuler'])
            ->assertSessionHas('error');
        $this->assertSame('inside', $visitor->refresh()->status);
    }

    public function test_les_visites_attendues_restent_visibles_hors_periode(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $this->visitor($alpha, ['name' => 'Visite annoncée']);
        $this->visitor($alpha, ['name' => 'Visite de juillet', 'check_in_at' => '2026-07-03 09:00:00', 'check_out_at' => '2026-07-03 09:30:00']);
        $this->visitor($alpha, ['name' => 'Visite du mois', 'check_in_at' => '2026-09-02 09:00:00', 'check_out_at' => '2026-09-02 09:20:00']);

        $this->actingAs($admin)->get(route('admin.administration.visitors'))
            ->assertOk()
            ->assertSee('Visite annoncée')
            ->assertSee('Visite du mois')
            ->assertDontSee('Visite de juillet');

        $this->actingAs($admin)->get(route('admin.administration.visitors', ['from' => '2026-07-01', 'to' => '2026-07-31']))
            ->assertOk()
            ->assertSee('Visite de juillet')
            ->assertSee('Visite annoncée');
    }

    public function test_les_etats_sont_affiches_en_francais_avec_l_email(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $this->visitor($alpha, ['email' => 'awa@sogex.test', 'check_in_at' => '2026-09-14 09:00:00']);

        $this->actingAs($admin)->get(route('admin.administration.visitors'))
            ->assertOk()
            ->assertSee('Sur place')
            ->assertSee('awa@sogex.test')
            ->assertDontSee('>inside<', false);
    }

    public function test_le_registre_est_cloisonne_par_entreprise(): void
    {
        $alpha = $this->makeCompany('alpha');
        $beta = $this->makeCompany('beta');
        $admin = $this->makeAdmin($alpha);
        $this->visitor($alpha, ['name' => 'Visiteur Alpha', 'check_in_at' => '2026-09-10 09:00:00']);
        $chezBeta = $this->visitor($beta, ['name' => 'Visiteur Beta', 'check_in_at' => '2026-09-10 09:00:00']);

        $this->actingAs($admin)->get(route('admin.administration.visitors'))
            ->assertOk()
            ->assertSee('Visiteur Alpha')
            ->assertDontSee('Visiteur Beta');

        $this->actingAs($admin)->delete(route('admin.administration.visitors.destroy', $chezBeta))->assertNotFound();
        $this->assertDatabaseHas('admin_visitors', ['id' => $chezBeta->id]);
    }

    public function test_un_utilisateur_avec_la_permission_administration_ouvre_les_rubriques(): void
    {
        $alpha = $this->makeCompany('alpha');
        $role = Role::firstOrCreate(['name' => 'employee'], ['label' => 'Employé']);
        $agent = User::create([
            'name' => 'Agent d’accueil', 'email' => 'accueil@alpha.test', 'password' => Hash::make('password'),
            'entreprise_id' => $alpha->id, 'role_id' => $role->id, 'is_active' => true,
            'permissions' => ['administration' => ['view' => true, 'edit' => true]],
        ]);

        // Les rubriques ne portent pas de permission propre : celle de l'espace suffit.
        $this->actingAs($agent)->get(route('admin.administration'))->assertOk();
        $this->actingAs($agent)->get(route('admin.administration.visitors'))->assertOk();
        $this->actingAs($agent)->get(route('admin.administration.module', 'appels'))->assertOk();
    }

    public function test_l_ancienne_adresse_des_visiteurs_ouvre_le_nouvel_ecran(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);

        // /administration/visiteurs servait la page générique ; elle sert désormais le registre.
        $this->actingAs($admin)->get(route('admin.administration.module', 'visiteurs'))
            ->assertOk()
            ->assertSee('Registre des visites');
    }

    public function test_l_accueil_administration_compte_les_enregistrements(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $this->visitor($alpha, ['check_in_at' => '2026-09-14 09:00:00']);
        $this->visitor($alpha, ['name' => 'Annoncé']);

        $this->actingAs($admin)->get(route('admin.administration'))
            ->assertOk()
            ->assertSee('Registre des visiteurs')
            ->assertSee('2 enregistrement(s)')
            ->assertSee('1 sur place');
    }
}
