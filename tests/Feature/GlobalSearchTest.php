<?php

namespace Tests\Feature;

use App\Models\CommercialClient;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Recherche globale de la barre du haut : elle ne doit montrer que les
 * données de l'entreprise de l'utilisateur, et seulement les modules qu'il a
 * le droit de consulter.
 */
class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private function client(int $entrepriseId, string $name): CommercialClient
    {
        return CommercialClient::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entrepriseId,
            'name' => $name,
        ]);
    }

    /** Titres des résultats d'un groupe, pour une recherche donnée. */
    private function titles(User $user, string $term, string $group): array
    {
        $groups = $this->actingAs($user)
            ->getJson(route('admin.search', ['q' => $term]))
            ->assertOk()
            ->json('groups');

        return collect($groups)->firstWhere('key', $group)['items'] ?? [];
    }

    public function test_la_recherche_trouve_un_client_de_l_entreprise(): void
    {
        $alpha = $this->makeCompany('alpha');
        $this->client($alpha->id, 'Société Générale CI');

        $items = $this->titles($this->makeAdmin($alpha), 'générale', 'clients');

        $this->assertSame(['Société Générale CI'], array_column($items, 'title'));
    }

    public function test_la_recherche_ignore_les_donnees_d_une_autre_entreprise(): void
    {
        $alpha = $this->makeCompany('alpha');
        $beta = $this->makeCompany('beta');
        $this->client($alpha->id, 'Orange Alpha');
        $this->client($beta->id, 'Orange Beta');

        $items = $this->titles($this->makeAdmin($alpha), 'orange', 'clients');

        $this->assertSame(['Orange Alpha'], array_column($items, 'title'));
    }

    public function test_un_module_non_autorise_n_apparait_pas(): void
    {
        $alpha = $this->makeCompany('alpha');
        $this->client($alpha->id, 'Orange Côte d’Ivoire');
        $role = Role::firstOrCreate(['name' => 'agent'], ['label' => 'Agent']);
        $agent = User::create([
            'name' => 'Agent alpha',
            'email' => 'agent@alpha.test',
            'password' => Hash::make('password'),
            'entreprise_id' => $alpha->id,
            'role_id' => $role->id,
            'is_active' => true,
            'permissions' => ['hr' => ['view' => true]],
        ]);

        $this->assertSame([], $this->titles($agent, 'orange', 'clients'));
    }

    public function test_une_recherche_trop_courte_ne_renvoie_rien(): void
    {
        $alpha = $this->makeCompany('alpha');
        $this->client($alpha->id, 'Orange');

        $this->actingAs($this->makeAdmin($alpha))
            ->getJson(route('admin.search', ['q' => 'o']))
            ->assertOk()
            ->assertExactJson(['groups' => []]);
    }

    public function test_les_jokers_sql_sont_cherches_comme_du_texte(): void
    {
        $alpha = $this->makeCompany('alpha');
        $this->client($alpha->id, 'Orange');
        $this->client($alpha->id, 'Remise 100% garantie');

        $admin = $this->makeAdmin($alpha);

        $this->assertSame(['Remise 100% garantie'], array_column($this->titles($admin, '0%', 'clients'), 'title'));
        $this->assertSame([], $this->titles($admin, '%%', 'clients'));
    }
}
