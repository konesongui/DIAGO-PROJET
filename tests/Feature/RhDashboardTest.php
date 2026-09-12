<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Entreprise;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Payroll;
use App\Models\Role;
use App\Models\SalaryCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Tableau RH et catégories salariales : effectif réel, mouvements de la
 * période, masse salariale, et catégories rattachées aux fiches employés.
 */
class RhDashboardTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-15 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function employee(Entreprise $entreprise, string $name, array $attributes = []): Employee
    {
        $slug = str($name)->slug();
        $user = User::create([
            'name' => $name, 'email' => $slug . '@' . $entreprise->slug . '.test', 'password' => Hash::make('password'),
            'entreprise_id' => $entreprise->id, 'role_id' => Role::firstOrCreate(['name' => 'employee'], ['label' => 'Employé'])->id, 'is_active' => true,
        ]);

        return Employee::withoutGlobalScope('entreprise')->create(array_merge([
            'entreprise_id' => $entreprise->id, 'user_id' => $user->id, 'full_name' => $name, 'matricule' => 'EMP-' . $user->id,
            'position' => 'Comptable', 'department' => 'Comptabilité', 'contract_type' => 'CDI', 'gender' => 'M',
            'hire_date' => '2024-02-01', 'registered_at' => '2024-02-01', 'status' => 'active',
            'email' => $slug . '@rh.test', 'monthly_salary' => 400000,
        ], $attributes));
    }

    public function test_l_effectif_ne_depend_pas_de_la_periode_choisie(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        // Embauchés avant la période par défaut : ils font pourtant partie de l'effectif.
        $this->employee($alpha, 'Koffi Yao');
        $this->employee($alpha, 'Awa Koné', ['department' => 'Commercial', 'gender' => 'F', 'monthly_salary' => 650000]);
        $this->employee($alpha, 'Mariam Ouattara', ['registered_at' => '2026-03-02', 'hire_date' => '2026-03-02', 'gender' => 'F', 'monthly_salary' => 300000]);
        $this->employee($alpha, 'Bintou Diallo', ['status' => 'inactive', 'contract_end_date' => '2026-05-20', 'gender' => 'F']);

        $this->actingAs($admin)
            ->get(route('admin.rh.tableau'))
            ->assertOk()
            // 3 en poste (la 4e est sortie), masse salariale des 3.
            ->assertSeeInOrder(['Effectif à ce jour', '3', 'Masse salariale', money(1350000)])
            ->assertSeeInOrder(['Entrées sur la période', '1', '1 départ(s) sur la même période'])
            ->assertSeeInOrder(['Effectif par service', 'Comptabilité', 'Commercial'])
            // Les mouvements sont listés du plus récent au plus ancien : la sortie de mai avant l'entrée de mars.
            ->assertSeeInOrder(['Mouvements du personnel', 'Bintou Diallo', 'Contrat clôturé', 'Mariam Ouattara', 'Entrée']);
    }

    public function test_le_tableau_rh_suit_les_conges_et_la_masse_salariale_de_la_periode(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $koffi = $this->employee($alpha, 'Koffi Yao');
        $awa = $this->employee($alpha, 'Awa Koné');
        $type = LeaveType::withoutGlobalScope('entreprise')->create(['entreprise_id' => $alpha->id, 'name' => 'Congé annuel', 'days' => 30, 'is_active' => true]);
        LeaveRequest::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'employee_id' => $awa->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-09-14', 'end_date' => '2026-09-18', 'days' => 5, 'status' => 'approved',
        ]);
        LeaveRequest::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'employee_id' => $koffi->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-10-05', 'end_date' => '2026-10-09', 'days' => 5, 'status' => 'pending',
        ]);
        foreach ([[7, 380000, 90000], [8, 400000, 95000]] as [$month, $net, $charges]) {
            Payroll::withoutGlobalScope('entreprise')->create([
                'entreprise_id' => $alpha->id, 'employee_id' => $koffi->id, 'month' => $month, 'year' => 2026,
                'base_salary' => 400000, 'gross_salary' => 430000, 'net_salary' => $net, 'employer_charges' => $charges,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.rh.tableau'))
            ->assertOk()
            // L'employée en congé aujourd'hui est comptée, même si sa fiche reste « active ».
            ->assertSee('1 en congé aujourd’hui')
            ->assertSeeInOrder(['Bulletins de la période', '2', money(780000) . ' de net versé'])
            ->assertSeeInOrder(['Congés à valider', '1'])
            ->assertSeeInOrder(['Charges patronales', money(185000)])
            ->assertSeeInOrder(['Derniers bulletins', 'Koffi Yao', 'Août 2026']);

        // Période restreinte : seul le bulletin de juillet est compté.
        $this->actingAs($admin)
            ->get(route('admin.rh.tableau', ['date_debut' => '2026-07-01', 'date_fin' => '2026-07-31']))
            ->assertOk()
            ->assertSee(money(380000))
            ->assertSee('Effectif à ce jour');

        // Période incohérente : message, pas de page d'erreur.
        $this->actingAs($admin)
            ->get(route('admin.rh.tableau', ['date_debut' => '2026-09-30', 'date_fin' => '2026-01-01']))
            ->assertOk()
            ->assertSee('La période choisie est invalide');
    }

    public function test_une_categorie_salariale_utilisee_ne_se_supprime_pas(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $this->employee($alpha, 'Koffi Yao', ['salary_category' => 'Catégorie 1A']);
        $category = SalaryCategory::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'name' => 'Catégorie 1A', 'amount' => 350000, 'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('admin.rh.salaryCategories'))
            ->assertOk()
            ->assertSeeInOrder(['Catégorie 1A', money(350000), '1', money(400000), 'Ouverte']);

        $this->actingAs($admin)->from(route('admin.rh.salaryCategories'))
            ->delete(route('admin.rh.salaryCategories.destroy', $category))
            ->assertSessionHasErrors(['category' => '1 employé(s) sont rattachés à la catégorie « Catégorie 1A » : désactivez-la, ou changez d’abord leur catégorie.']);
        $this->assertNotNull($category->fresh());

        // Nom en double refusé.
        $this->actingAs($admin)->from(route('admin.rh.salaryCategories'))
            ->post(route('admin.rh.salaryCategories.store'), ['_category_form' => 1, 'name' => 'catégorie 1a', 'amount' => 100000])
            ->assertSessionHasErrors(['name' => 'Une catégorie salariale porte déjà ce nom.']);

        // Renommer suit les fiches employés.
        $this->actingAs($admin)->from(route('admin.rh.salaryCategories'))
            ->put(route('admin.rh.salaryCategories.update', $category), ['_category_form' => 1, 'name' => 'Catégorie 1B', 'amount' => 380000, 'is_active' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame('Catégorie 1B', Employee::withoutGlobalScope('entreprise')->where('full_name', 'Koffi Yao')->first()->salary_category);
    }

    public function test_le_tableau_rh_d_une_autre_entreprise_reste_invisible(): void
    {
        $beta = $this->makeCompany('beta');
        $this->employee($beta, 'Employé confidentiel beta');

        $this->actingAs($this->makeAdmin($this->makeCompany('alpha')))
            ->get(route('admin.rh.tableau'))
            ->assertOk()
            ->assertDontSee('Employé confidentiel beta')
            ->assertSee('Aucun employé en poste');
    }
}
