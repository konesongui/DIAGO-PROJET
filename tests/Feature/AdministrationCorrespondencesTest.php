<?php

namespace Tests\Feature;

use App\Models\AdminCorrespondence;
use App\Models\Entreprise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Registre des courriers : référence unique, correspondant exigé selon le sens,
 * pièce jointe remplaçable et suivi du traitement.
 */
class AdministrationCorrespondencesTest extends TestCase
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

    private function mail(Entreprise $entreprise, array $attributes = []): AdminCorrespondence
    {
        $mail = new AdminCorrespondence(array_merge([
            'reference' => 'CR-2026-041',
            'type' => 'incoming',
            'subject' => 'Mise en demeure fournisseur',
            'sender' => 'Ivoire Bureau',
            'received_at' => '2026-09-10',
            'status' => 'received',
        ], $attributes));
        $mail->entreprise_id = $entreprise->id;
        $mail->saveQuietly();

        return $mail;
    }

    public function test_deux_courriers_ne_partagent_pas_la_meme_reference(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $existing = $this->mail($alpha);

        $this->actingAs($admin)->from(route('admin.administration.correspondences'))
            ->post(route('admin.administration.correspondences.store'), [
                'reference' => 'cr-2026-041', 'type' => 'incoming', 'subject' => 'Autre courrier',
                'sender' => 'DGI', 'received_at' => '2026-09-12', 'status' => 'received',
            ])->assertSessionHasErrors('reference');

        $this->assertSame(1, AdminCorrespondence::withoutGlobalScope('entreprise')->where('entreprise_id', $alpha->id)->count());

        // Le courrier garde sa propre référence à la modification.
        $this->actingAs($admin)->put(route('admin.administration.correspondences.update', $existing), [
            'reference' => 'CR-2026-041', 'type' => 'incoming', 'subject' => 'Mise en demeure fournisseur (suite)',
            'sender' => 'Ivoire Bureau', 'received_at' => '2026-09-10', 'status' => 'in_progress',
        ])->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $existing->refresh()->status);
    }

    public function test_le_correspondant_depend_du_sens_du_courrier(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);

        $this->actingAs($admin)->from(route('admin.administration.correspondences'))
            ->post(route('admin.administration.correspondences.store'), [
                'reference' => 'CR-1', 'type' => 'incoming', 'subject' => 'Courrier arrivé',
                'received_at' => '2026-09-12', 'status' => 'received',
            ])->assertSessionHasErrors('sender');

        $this->actingAs($admin)->from(route('admin.administration.correspondences'))
            ->post(route('admin.administration.correspondences.store'), [
                'reference' => 'CR-2', 'type' => 'outgoing', 'subject' => 'Courrier parti',
                'received_at' => '2026-09-12', 'status' => 'received',
            ])->assertSessionHasErrors('recipient');
    }

    public function test_la_piece_jointe_est_remplacee_retiree_et_supprimee_avec_le_courrier(): void
    {
        Storage::fake('public');
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);

        $this->actingAs($admin)->post(route('admin.administration.correspondences.store'), [
            'reference' => 'CR-2026-050', 'type' => 'incoming', 'subject' => 'Convocation',
            'sender' => 'Mairie du Plateau', 'received_at' => '2026-09-12', 'status' => 'received',
            'file' => UploadedFile::fake()->create('convocation.pdf', 80, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $mail = AdminCorrespondence::withoutGlobalScope('entreprise')->firstWhere('reference', 'CR-2026-050');
        $first = $mail->file_path;
        $this->assertNotNull($first);
        Storage::disk('public')->assertExists($first);

        // Remplacement : l'ancien fichier ne doit pas rester sur le disque.
        $this->actingAs($admin)->put(route('admin.administration.correspondences.update', $mail), [
            'reference' => 'CR-2026-050', 'type' => 'incoming', 'subject' => 'Convocation',
            'sender' => 'Mairie du Plateau', 'received_at' => '2026-09-12', 'status' => 'received',
            'file' => UploadedFile::fake()->create('convocation-v2.pdf', 90, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $second = $mail->refresh()->file_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);

        // Retrait explicite.
        $this->actingAs($admin)->put(route('admin.administration.correspondences.update', $mail), [
            'reference' => 'CR-2026-050', 'type' => 'incoming', 'subject' => 'Convocation',
            'sender' => 'Mairie du Plateau', 'received_at' => '2026-09-12', 'status' => 'processed', 'remove_file' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertNull($mail->refresh()->file_path);
        Storage::disk('public')->assertMissing($second);

        // Suppression du courrier : le fichier part avec lui.
        $this->actingAs($admin)->put(route('admin.administration.correspondences.update', $mail), [
            'reference' => 'CR-2026-050', 'type' => 'incoming', 'subject' => 'Convocation',
            'sender' => 'Mairie du Plateau', 'received_at' => '2026-09-12', 'status' => 'processed',
            'file' => UploadedFile::fake()->create('convocation-v3.pdf', 90, 'application/pdf'),
        ]);
        $third = $mail->refresh()->file_path;
        $this->actingAs($admin)->from(route('admin.administration.correspondences'))
            ->delete(route('admin.administration.correspondences.destroy', $mail));
        Storage::disk('public')->assertMissing($third);
        $this->assertDatabaseMissing('admin_correspondences', ['id' => $mail->id]);
    }

    public function test_les_courriers_non_traites_restent_visibles_hors_periode(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $this->mail($alpha, ['reference' => 'CR-JUILLET-OUVERT', 'received_at' => '2026-07-04', 'status' => 'received']);
        $this->mail($alpha, ['reference' => 'CR-JUILLET-CLOS', 'received_at' => '2026-07-05', 'status' => 'processed']);
        $this->mail($alpha, ['reference' => 'CR-DU-MOIS', 'received_at' => '2026-09-03', 'status' => 'processed']);

        $this->actingAs($admin)->get(route('admin.administration.correspondences'))
            ->assertOk()
            ->assertSee('CR-JUILLET-OUVERT')
            ->assertSee('CR-DU-MOIS')
            ->assertDontSee('CR-JUILLET-CLOS');
    }

    public function test_le_suivi_se_note_en_francais_et_une_seule_fois(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $mail = $this->mail($alpha, ['type' => 'outgoing', 'recipient' => 'Direction des Impôts', 'sender' => null]);

        $this->actingAs($admin)->get(route('admin.administration.correspondences'))
            ->assertOk()
            ->assertSee('Départ')
            ->assertSee('À traiter')
            ->assertSee('Direction des Impôts')
            ->assertDontSee('>received<', false);

        $this->actingAs($admin)->from(route('admin.administration.correspondences'))
            ->post(route('admin.administration.correspondences.status', $mail), ['status' => 'processed'])
            ->assertSessionHas('success');
        $this->assertSame('processed', $mail->refresh()->status);

        $this->actingAs($admin)->from(route('admin.administration.correspondences'))
            ->post(route('admin.administration.correspondences.status', $mail), ['status' => 'processed'])
            ->assertSessionHas('error');
    }

    public function test_le_registre_est_cloisonne_par_entreprise(): void
    {
        $alpha = $this->makeCompany('alpha');
        $beta = $this->makeCompany('beta');
        $admin = $this->makeAdmin($alpha);
        $this->mail($alpha, ['reference' => 'CR-ALPHA']);
        $chezBeta = $this->mail($beta, ['reference' => 'CR-BETA']);

        $this->actingAs($admin)->get(route('admin.administration.correspondences'))
            ->assertOk()
            ->assertSee('CR-ALPHA')
            ->assertDontSee('CR-BETA');

        // La même référence chez une autre entreprise ne gêne pas.
        $this->actingAs($admin)->post(route('admin.administration.correspondences.store'), [
            'reference' => 'CR-BETA', 'type' => 'incoming', 'subject' => 'Courrier homonyme',
            'sender' => 'Fournisseur', 'received_at' => '2026-09-12', 'status' => 'received',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->delete(route('admin.administration.correspondences.destroy', $chezBeta))->assertNotFound();
    }
}
