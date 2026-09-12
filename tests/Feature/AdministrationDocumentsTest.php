<?php

namespace Tests\Feature;

use App\Models\AdminDocument;
use App\Models\Entreprise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Documents administratifs : fichier exigé au classement, types contrôlés,
 * remplacement propre, archivage et cloisonnement.
 */
class AdministrationDocumentsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-14 10:00:00');
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function document(Entreprise $entreprise, array $attributes = []): AdminDocument
    {
        $document = new AdminDocument(array_merge([
            'title' => 'Statuts de la société',
            'category' => 'Registres légaux',
            'document_date' => '2024-01-15',
            'status' => 'active',
            'file_path' => 'administration/documents/statuts.pdf',
        ], $attributes));
        $document->entreprise_id = $entreprise->id;
        $document->saveQuietly();

        return $document;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Contrat de bail',
            'category' => 'Contrats',
            'document_date' => '2026-01-05',
            'status' => 'active',
        ], $overrides);
    }

    public function test_le_fichier_est_exige_au_classement_mais_pas_a_la_modification(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);

        $this->actingAs($admin)->from(route('admin.administration.documents'))
            ->post(route('admin.administration.documents.store'), $this->payload())
            ->assertSessionHasErrors('file');
        $this->assertSame(0, AdminDocument::withoutGlobalScope('entreprise')->count());

        $this->actingAs($admin)->post(route('admin.administration.documents.store'), $this->payload([
            'file' => UploadedFile::fake()->create('bail.pdf', 120, 'application/pdf'),
        ]))->assertSessionHasNoErrors();

        $document = AdminDocument::withoutGlobalScope('entreprise')->firstWhere('title', 'Contrat de bail');
        $this->assertNotNull($document->file_path);
        Storage::disk('public')->assertExists($document->file_path);

        // Modification sans nouveau fichier : le fichier déjà classé reste en place.
        $this->actingAs($admin)->put(route('admin.administration.documents.update', $document), $this->payload([
            'title' => 'Contrat de bail du siège',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('Contrat de bail du siège', $document->refresh()->title);
        Storage::disk('public')->assertExists($document->file_path);
    }

    public function test_les_fichiers_non_bureautiques_sont_refuses(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);

        $this->actingAs($admin)->from(route('admin.administration.documents'))
            ->post(route('admin.administration.documents.store'), $this->payload([
                'file' => UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream'),
            ]))->assertSessionHasErrors('file');

        $this->assertSame(0, AdminDocument::withoutGlobalScope('entreprise')->count());
    }

    public function test_remplacer_ou_supprimer_un_document_nettoie_le_disque(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);

        $this->actingAs($admin)->post(route('admin.administration.documents.store'), $this->payload([
            'file' => UploadedFile::fake()->create('bail.pdf', 120, 'application/pdf'),
        ]));
        $document = AdminDocument::withoutGlobalScope('entreprise')->firstWhere('title', 'Contrat de bail');
        $first = $document->file_path;

        $this->actingAs($admin)->put(route('admin.administration.documents.update', $document), $this->payload([
            'file' => UploadedFile::fake()->create('bail-avenant.pdf', 130, 'application/pdf'),
        ]))->assertSessionHasNoErrors();
        $second = $document->refresh()->file_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);

        $this->actingAs($admin)->from(route('admin.administration.documents'))
            ->delete(route('admin.administration.documents.destroy', $document));
        Storage::disk('public')->assertMissing($second);
        $this->assertDatabaseMissing('admin_documents', ['id' => $document->id]);
    }

    public function test_l_archivage_se_note_une_seule_fois_et_s_affiche_en_francais(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $document = $this->document($alpha);

        $this->actingAs($admin)->get(route('admin.administration.documents'))
            ->assertOk()
            ->assertSee('Actif')
            ->assertSee('Registres légaux')
            ->assertDontSee('>active<', false);

        $this->actingAs($admin)->from(route('admin.administration.documents'))
            ->post(route('admin.administration.documents.status', $document), ['status' => 'archived'])
            ->assertSessionHas('success');
        $this->assertSame('archived', $document->refresh()->status);

        $this->actingAs($admin)->from(route('admin.administration.documents'))
            ->post(route('admin.administration.documents.status', $document), ['status' => 'archived'])
            ->assertSessionHas('error');
    }

    public function test_un_fichier_disparu_du_serveur_est_signale(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $this->document($alpha, ['file_path' => 'administration/documents/disparu.pdf']);

        $this->actingAs($admin)->get(route('admin.administration.documents'))
            ->assertOk()
            ->assertSee('Introuvable')
            ->assertSee('fichier introuvable sur le serveur');
    }

    public function test_les_documents_sont_cloisonnes_par_entreprise(): void
    {
        $alpha = $this->makeCompany('alpha');
        $beta = $this->makeCompany('beta');
        $admin = $this->makeAdmin($alpha);
        $this->document($alpha, ['title' => 'Document Alpha']);
        $chezBeta = $this->document($beta, ['title' => 'Document Beta']);

        $this->actingAs($admin)->get(route('admin.administration.documents'))
            ->assertOk()
            ->assertSee('Document Alpha')
            ->assertDontSee('Document Beta');

        $this->actingAs($admin)->delete(route('admin.administration.documents.destroy', $chezBeta))->assertNotFound();
        $this->assertDatabaseHas('admin_documents', ['id' => $chezBeta->id]);
    }
}
