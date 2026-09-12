<?php

namespace Tests\Feature;

use App\Models\Entreprise;
use App\Models\LedgerAccount;
use App\Models\Role;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Entreprises & filiales : une création produit un espace utilisable, la
 * désactivation ferme les accès et une fiche qui porte des données ne se
 * supprime pas.
 */
class EntreprisesPageTest extends TestCase
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

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Super Admin', 'email' => 'super@diago.test', 'password' => Hash::make('password'),
            'entreprise_id' => null, 'is_active' => true,
            'role_id' => Role::firstOrCreate(['name' => 'super_admin'], ['label' => 'Super administrateur'])->id,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ivoire Cacao Trading',
            'activity' => 'Agroalimentaire / Export',
            'address' => 'Boulevard de la Paix, San-Pédro',
            'city' => 'San-Pédro',
            'email' => 'a.kouadio@ivoirecacao.test',
            'phone' => '+225 27 34 71 00 00',
            'subscription_expires_at' => '2027-09-14',
            'rubriques' => ['commercial' => '1', 'comptabilite' => '1'],
            'admin_name' => 'Aya Kouadio',
            'admin_email' => 'a.kouadio@ivoirecacao.test',
            'admin_password' => 'motdepasse',
            'admin_password_confirmation' => 'motdepasse',
        ], $overrides);
    }

    public function test_une_entreprise_creee_est_immediatement_utilisable(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($super)->post(route('admin.entreprises.store'), $this->payload())
            ->assertRedirect(route('admin.entreprises.index'));

        $entreprise = Entreprise::firstWhere('name', 'Ivoire Cacao Trading');
        $this->assertNotNull($entreprise);
        $this->assertSame('ivoire-cacao-trading', $entreprise->slug, 'Le slug est déduit de la raison sociale.');

        // Compte administrateur : sans lui, personne ne peut se connecter à l'espace.
        $admin = User::where('entreprise_id', $entreprise->id)->firstOrFail();
        $this->assertSame('a.kouadio@ivoirecacao.test', $admin->email);
        $this->assertTrue($admin->isEntrepriseAdmin());

        // Rubriques ouvertes, sinon le menu est vide.
        $this->assertTrue(data_get($entreprise->settings, 'enabled_rubriques.commercial'));
        $this->assertFalse(data_get($entreprise->settings, 'enabled_rubriques.rh'));

        // Taux de taxe et plan comptable, sinon aucune facture ni écriture n'est possible.
        $this->assertTrue(TaxRate::withoutGlobalScope('entreprise')->where('entreprise_id', $entreprise->id)->exists());
        $this->assertTrue(LedgerAccount::withoutGlobalScope('entreprise')->where('entreprise_id', $entreprise->id)->exists());

        // Identité reprise dans les réglages, d'où les documents imprimés tirent l'en-tête.
        $this->assertSame('Agroalimentaire / Export', data_get($entreprise->settings, 'activity'));
        $this->assertSame('Ivoire Cacao Trading', data_get($entreprise->settings, 'name'));
    }

    public function test_une_entreprise_sans_rubrique_ou_au_slug_pris_est_refusee(): void
    {
        $super = $this->superAdmin();
        $this->makeCompany('ivoire-cacao-trading');

        $this->actingAs($super)->from(route('admin.entreprises.create'))
            ->post(route('admin.entreprises.store'), $this->payload(['rubriques' => []]))
            ->assertSessionHasErrors('rubriques');

        $this->actingAs($super)->from(route('admin.entreprises.create'))
            ->post(route('admin.entreprises.store'), $this->payload())
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, Entreprise::where('slug', 'ivoire-cacao-trading')->count());
    }

    public function test_desactiver_une_entreprise_ferme_l_acces_de_ses_comptes(): void
    {
        $super = $this->superAdmin();
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);

        $this->actingAs($super)->from(route('admin.entreprises.index'))
            ->patch(route('admin.entreprises.toggle', $alpha))
            ->assertSessionHas('success');

        $this->assertFalse($alpha->refresh()->is_active);
        $this->assertFalse($admin->refresh()->is_active);
    }

    public function test_une_entreprise_qui_porte_des_donnees_ne_se_supprime_pas(): void
    {
        $super = $this->superAdmin();
        $alpha = $this->makeCompany('alpha');
        $this->makeAdmin($alpha);

        $this->actingAs($super)->from(route('admin.entreprises.index'))
            ->delete(route('admin.entreprises.destroy', $alpha))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('entreprises', ['id' => $alpha->id]);

        // Une fiche vide, elle, se supprime.
        $vide = Entreprise::create(['name' => 'Coquille vide', 'slug' => 'coquille-vide', 'is_active' => true, 'settings' => []]);
        $this->actingAs($super)->from(route('admin.entreprises.index'))
            ->delete(route('admin.entreprises.destroy', $vide))
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('entreprises', ['id' => $vide->id]);
    }

    public function test_la_console_super_admin_cree_le_meme_espace_complet(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($super)->get(route('console.entreprises.create'))->assertOk();

        $this->actingAs($super)->post(route('console.entreprises.store'), [
            'company_name' => 'Sotra BTP & Construction',
            'phone' => '+225 27 21 75 80 80',
            'address' => 'Rue des Jardins, Cocody',
            'city' => 'Abidjan',
            'subscription_expires_at' => '2027-02-20',
            'admin_name' => 'Direction Sotra',
            'admin_email' => 'info@sotrabtp.test',
            'admin_password' => 'motdepasse',
            'admin_password_confirmation' => 'motdepasse',
            'rubriques' => ['commercial' => '1'],
        ])->assertRedirect(route('console.index'));

        $entreprise = Entreprise::firstWhere('slug', 'sotra-btp-construction');
        $this->assertNotNull($entreprise);
        $this->assertTrue(User::where('entreprise_id', $entreprise->id)->exists());
        $this->assertTrue(TaxRate::withoutGlobalScope('entreprise')->where('entreprise_id', $entreprise->id)->exists());
        $this->assertTrue(LedgerAccount::withoutGlobalScope('entreprise')->where('entreprise_id', $entreprise->id)->exists());
    }

    public function test_l_ecran_est_reserve_au_super_administrateur(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);

        $this->actingAs($admin)->get(route('admin.entreprises.index'))->assertForbidden();
        $this->actingAs($this->superAdmin())->get(route('admin.entreprises.index'))->assertOk();
    }

    public function test_la_modification_conserve_les_reglages_existants(): void
    {
        $super = $this->superAdmin();
        $alpha = $this->makeCompany('alpha');

        $this->actingAs($super)->put(route('admin.entreprises.update', $alpha), [
            'name' => 'Société Alpha renommée',
            'address' => 'Rue du Commerce',
            'activity' => 'Conseil & audit',
            'subscription_expires_at' => '2027-01-31',
            'rubriques' => ['rh' => '1'],
        ])->assertRedirect(route('admin.entreprises.index'));

        $alpha->refresh();
        $this->assertSame('Société Alpha renommée', $alpha->name);
        $this->assertSame('societe-alpha-renommee', $alpha->slug);
        $this->assertSame('XOF', data_get($alpha->settings, 'currency'), 'Les réglages déjà en place ne sont pas écrasés.');
        $this->assertTrue(data_get($alpha->settings, 'enabled_rubriques.rh'));
        $this->assertFalse(data_get($alpha->settings, 'enabled_rubriques.commercial'));
    }
}
