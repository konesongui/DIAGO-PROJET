<?php

namespace Tests\Feature;

use App\Models\Entreprise;
use App\Models\Role;
use App\Models\Succursale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Succursales : suppression bloquée tant que des comptes y sont rattachés,
 * fermeture qui suit sur les accès, code unique et cloisonnement.
 */
class SuccursalesPageTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private function companyWithBranches(string $slug = 'alpha'): Entreprise
    {
        $entreprise = $this->makeCompany($slug);
        $settings = $entreprise->settings;
        $settings['enabled_rubriques'] = ['succursales' => true];
        $entreprise->update(['settings' => $settings]);

        return $entreprise;
    }

    private function branch(Entreprise $entreprise, array $attributes = []): Succursale
    {
        return Succursale::create(array_merge([
            'entreprise_id' => $entreprise->id,
            'name' => 'Agence de Cocody',
            'code' => 'COC',
            'city' => 'Abidjan',
            'is_active' => true,
        ], $attributes));
    }

    private function manager(Entreprise $entreprise, Succursale $succursale, string $email = 'responsable@alpha.test'): User
    {
        return User::create([
            'name' => 'Responsable Cocody', 'email' => $email, 'password' => Hash::make('password'),
            'entreprise_id' => $entreprise->id, 'succursale_id' => $succursale->id,
            'role_id' => Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrateur'])->id,
            'is_active' => true,
        ]);
    }

    public function test_une_succursale_qui_porte_des_comptes_ne_se_supprime_pas(): void
    {
        $alpha = $this->companyWithBranches();
        $admin = $this->makeAdmin($alpha);
        $branch = $this->branch($alpha);
        $manager = $this->manager($alpha, $branch);

        $this->actingAs($admin)->from(route('admin.succursales.index'))
            ->delete(route('admin.succursales.destroy', $branch))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('succursales', ['id' => $branch->id]);
        // Sans ce refus, le compte perdrait sa succursale et deviendrait administrateur de l'entreprise.
        $this->assertSame($branch->id, $manager->refresh()->succursale_id);
        $this->assertFalse($manager->isEntrepriseAdmin());
    }

    public function test_fermer_une_succursale_ferme_l_acces_de_ses_comptes(): void
    {
        $alpha = $this->companyWithBranches();
        $admin = $this->makeAdmin($alpha);
        $branch = $this->branch($alpha);
        $manager = $this->manager($alpha, $branch);

        $this->actingAs($admin)->from(route('admin.succursales.index'))
            ->patch(route('admin.succursales.toggle', $branch))
            ->assertSessionHas('success');
        $this->assertFalse($branch->refresh()->is_active);
        $this->assertFalse($manager->refresh()->is_active);

        $this->actingAs($admin)->from(route('admin.succursales.index'))
            ->patch(route('admin.succursales.toggle', $branch));
        $this->assertTrue($branch->refresh()->is_active);
        $this->assertTrue($manager->refresh()->is_active);
    }

    public function test_le_code_reste_unique_quelle_que_soit_la_casse(): void
    {
        $alpha = $this->companyWithBranches();
        $admin = $this->makeAdmin($alpha);
        $this->branch($alpha);

        $this->actingAs($admin)->from(route('admin.succursales.index'))
            ->post(route('admin.succursales.store'), [
                'name' => 'Agence du Plateau', 'code' => 'coc',
                'admin_name' => 'Responsable Plateau', 'admin_email' => 'plateau@alpha.test',
                'admin_password' => 'motdepasse', 'admin_password_confirmation' => 'motdepasse',
            ])->assertSessionHasErrors('code');

        $this->assertSame(1, Succursale::where('entreprise_id', $alpha->id)->count());

        $this->actingAs($admin)->post(route('admin.succursales.store'), [
            'name' => 'Agence du Plateau', 'code' => 'PLA', 'city' => 'Abidjan',
            'admin_name' => 'Responsable Plateau', 'admin_email' => 'plateau@alpha.test',
            'admin_password' => 'motdepasse', 'admin_password_confirmation' => 'motdepasse',
        ])->assertSessionHasNoErrors();

        $created = Succursale::where('entreprise_id', $alpha->id)->where('code', 'PLA')->firstOrFail();
        $account = User::where('succursale_id', $created->id)->firstOrFail();
        $this->assertFalse($account->isEntrepriseAdmin(), 'Le responsable administre sa succursale, pas l’entreprise.');
    }

    public function test_un_responsable_ne_voit_que_sa_succursale_et_ne_cree_rien(): void
    {
        $alpha = $this->companyWithBranches();
        $cocody = $this->branch($alpha);
        $plateau = $this->branch($alpha, ['name' => 'Agence du Plateau', 'code' => 'PLA']);
        $manager = $this->manager($alpha, $cocody);

        $this->actingAs($manager)->get(route('admin.succursales.index'))
            ->assertOk()
            ->assertSee('Agence de Cocody')
            ->assertDontSee('Agence du Plateau');

        $this->actingAs($manager)->post(route('admin.succursales.store'), [
            'name' => 'Agence de Yopougon', 'code' => 'YOP',
            'admin_name' => 'X', 'admin_email' => 'x@alpha.test',
            'admin_password' => 'motdepasse', 'admin_password_confirmation' => 'motdepasse',
        ])->assertForbidden();
    }

    public function test_la_rubrique_fermee_interdit_l_ecran(): void
    {
        $alpha = $this->makeCompany('sans-succursales');
        $admin = $this->makeAdmin($alpha);

        $this->actingAs($admin)->get(route('admin.succursales.index'))->assertForbidden();
    }

    public function test_les_succursales_sont_cloisonnees_par_entreprise(): void
    {
        $alpha = $this->companyWithBranches();
        $beta = $this->companyWithBranches('beta');
        $admin = $this->makeAdmin($alpha);
        $this->branch($alpha, ['name' => 'Agence Alpha', 'code' => 'ALP']);
        $chezBeta = $this->branch($beta, ['name' => 'Agence Beta', 'code' => 'BET']);

        $this->actingAs($admin)->get(route('admin.succursales.index'))
            ->assertOk()
            ->assertSee('Agence Alpha')
            ->assertDontSee('Agence Beta');

        $this->actingAs($admin)->delete(route('admin.succursales.destroy', $chezBeta))->assertNotFound();
        $this->assertDatabaseHas('succursales', ['id' => $chezBeta->id]);
    }
}
