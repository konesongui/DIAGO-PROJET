<?php

namespace Tests\Feature;

use App\Models\AdminMeeting;
use App\Models\Entreprise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Réunions : horaire cohérent, compte-rendu qui vaut tenue de la réunion,
 * travail en attente toujours visible et cloisonnement.
 */
class AdministrationMeetingsTest extends TestCase
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

    private function meeting(Entreprise $entreprise, array $attributes = []): AdminMeeting
    {
        $meeting = new AdminMeeting(array_merge([
            'title' => 'Comité de direction',
            'location' => 'Salle du conseil',
            'starts_at' => '2026-09-10 09:00:00',
            'ends_at' => '2026-09-10 10:30:00',
            'organizer' => 'Direction générale',
            'attendees' => "Awa Koné\nKoffi Yao",
            'status' => 'held',
        ], $attributes));
        $meeting->entreprise_id = $entreprise->id;
        $meeting->saveQuietly();

        return $meeting;
    }

    public function test_la_fin_doit_suivre_le_debut(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);

        foreach (['2026-09-20T09:00', '2026-09-20T08:30'] as $end) {
            $this->actingAs($admin)->from(route('admin.administration.meetings'))
                ->post(route('admin.administration.meetings.store'), [
                    'title' => 'Point hebdomadaire', 'starts_at' => '2026-09-20T09:00', 'ends_at' => $end, 'status' => 'planned',
                ])->assertSessionHasErrors('ends_at');
        }

        $this->actingAs($admin)->post(route('admin.administration.meetings.store'), [
            'title' => 'Point hebdomadaire', 'starts_at' => '2026-09-20T09:00', 'ends_at' => '2026-09-20T10:00', 'status' => 'planned',
        ])->assertSessionHasNoErrors();

        $meeting = AdminMeeting::withoutGlobalScope('entreprise')->firstWhere('title', 'Point hebdomadaire');
        $this->assertSame(60, $meeting->durationMinutes());
    }

    public function test_rediger_le_compte_rendu_note_la_reunion_tenue(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $meeting = $this->meeting($alpha, ['status' => 'planned', 'starts_at' => '2026-09-12 09:00:00', 'ends_at' => '2026-09-12 10:00:00']);

        $this->actingAs($admin)->from(route('admin.administration.meetings'))
            ->post(route('admin.administration.meetings.minutes', $meeting), ['minutes' => 'Budget 2027 validé.'])
            ->assertSessionHas('success');

        $meeting->refresh();
        $this->assertSame('held', $meeting->status);
        $this->assertTrue($meeting->hasMinutes());
        $this->assertFalse($meeting->needsMinutes());
    }

    public function test_le_travail_en_attente_reste_visible_hors_periode(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $this->meeting($alpha, ['title' => 'Réunion à venir', 'status' => 'planned', 'starts_at' => '2026-10-02 09:00:00', 'ends_at' => '2026-10-02 10:00:00']);
        $this->meeting($alpha, ['title' => 'Réunion sans compte-rendu', 'status' => 'held', 'starts_at' => '2026-07-03 09:00:00', 'ends_at' => '2026-07-03 10:00:00']);
        $this->meeting($alpha, ['title' => 'Réunion de juillet classée', 'status' => 'held', 'starts_at' => '2026-07-06 09:00:00', 'ends_at' => '2026-07-06 10:00:00', 'minutes' => 'Rien à signaler.']);
        $this->meeting($alpha, ['title' => 'Réunion du mois', 'status' => 'held', 'minutes' => 'Décisions prises.']);

        $this->actingAs($admin)->get(route('admin.administration.meetings'))
            ->assertOk()
            ->assertSee('Réunion à venir')
            ->assertSee('Réunion sans compte-rendu')
            ->assertSee('Réunion du mois')
            ->assertDontSee('Réunion de juillet classée');
    }

    public function test_les_etats_sont_affiches_en_francais_et_notes_une_seule_fois(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $meeting = $this->meeting($alpha, ['status' => 'planned', 'starts_at' => '2026-09-20 09:00:00', 'ends_at' => '2026-09-20 10:00:00']);

        $this->actingAs($admin)->get(route('admin.administration.meetings'))
            ->assertOk()
            ->assertSee('Prévue')
            ->assertSee('1 h 00')
            ->assertDontSee('>planned<', false);

        $this->actingAs($admin)->from(route('admin.administration.meetings'))
            ->post(route('admin.administration.meetings.status', $meeting), ['status' => 'held'])
            ->assertSessionHas('success');
        $this->assertSame('held', $meeting->refresh()->status);

        $this->actingAs($admin)->from(route('admin.administration.meetings'))
            ->post(route('admin.administration.meetings.status', $meeting), ['status' => 'held'])
            ->assertSessionHas('error');
    }

    public function test_les_reunions_sont_cloisonnees_par_entreprise(): void
    {
        $alpha = $this->makeCompany('alpha');
        $beta = $this->makeCompany('beta');
        $admin = $this->makeAdmin($alpha);
        $this->meeting($alpha, ['title' => 'Réunion Alpha', 'minutes' => 'Compte-rendu.']);
        $chezBeta = $this->meeting($beta, ['title' => 'Réunion Beta', 'minutes' => 'Compte-rendu.']);

        $this->actingAs($admin)->get(route('admin.administration.meetings'))
            ->assertOk()
            ->assertSee('Réunion Alpha')
            ->assertDontSee('Réunion Beta');

        $this->actingAs($admin)->delete(route('admin.administration.meetings.destroy', $chezBeta))->assertNotFound();
        $this->assertDatabaseHas('admin_meetings', ['id' => $chezBeta->id]);
    }
}
