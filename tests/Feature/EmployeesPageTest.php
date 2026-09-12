<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Entreprise;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Payroll;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Liste du personnel : fiches, état du jour lu dans les congés validés,
 * fin de contrat et suppression protégée par l'historique.
 */
class EmployeesPageTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-12 09:00:00');
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
            'entreprise_id' => $entreprise->id, 'user_id' => $user->id, 'full_name' => $name,
            'matricule' => 'EMP-' . $user->id, 'position' => 'Comptable', 'department' => 'Comptabilité',
            'contract_type' => 'CDI', 'hire_date' => '2025-01-06', 'registered_at' => '2025-01-06',
            'status' => 'active', 'email' => $slug . '@rh.test', 'monthly_salary' => 350000,
        ], $attributes));
    }

    private function approvedLeave(Employee $employee, string $from, string $to, string $type = 'Congé annuel'): LeaveRequest
    {
        $leaveType = LeaveType::withoutGlobalScope('entreprise')->firstOrCreate(
            ['entreprise_id' => $employee->entreprise_id, 'name' => $type],
            ['days' => 30, 'is_active' => true]
        );

        return LeaveRequest::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $employee->entreprise_id, 'employee_id' => $employee->id, 'leave_type_id' => $leaveType->id,
            'start_date' => $from, 'end_date' => $to, 'days' => Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1,
            'reason' => 'Congé annuel', 'status' => 'approved',
        ]);
    }

    public function test_l_etat_du_jour_vient_des_conges_valides_et_non_du_statut(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $awa = $this->employee($alpha, 'Awa Koné', ['department' => 'Commercial', 'position' => 'Commerciale', 'contract_type' => 'CDD']);
        $koffi = $this->employee($alpha, 'Koffi Yao');
        $bintou = $this->employee($alpha, 'Bintou Diallo', ['status' => 'inactive', 'contract_end_date' => '2026-06-30']);
        $this->approvedLeave($awa, '2026-09-10', '2026-09-20');
        // Congé terminé : Koffi est de nouveau en poste.
        $this->approvedLeave($koffi, '2026-08-01', '2026-08-15');
        // Congé demandé mais pas encore validé : il ne change rien.
        $this->approvedLeave($koffi, '2026-09-11', '2026-09-13')->update(['status' => 'pending']);

        $this->actingAs($admin)
            ->get(route('admin.rh.module', 'personnel'))
            ->assertOk()
            ->assertSee('Liste du personnel')
            ->assertSeeInOrder(['Effectif', '2', 'En congé aujourd’hui', '1', 'Masse salariale', money(700000), 'Contrats clôturés', '1'])
            ->assertSeeInOrder(['Awa Koné', 'En congé', 'Congé annuel jusqu’au 20/09/2026'])
            ->assertSeeInOrder(['Bintou Diallo', 'Contrat clôturé', 'depuis le 30/06/2026'])
            ->assertSee('En poste (1)')
            ->assertSee('En congé (1)')
            ->assertSee('Sortis (1)');
        $this->assertSame('active', $awa->fresh()->status);
    }

    public function test_la_fiche_avec_un_historique_ne_se_supprime_pas(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $koffi = $this->employee($alpha, 'Koffi Yao');
        Payroll::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'employee_id' => $koffi->id, 'month' => 8, 'year' => 2026,
            'base_salary' => 350000, 'gross_salary' => 350000, 'net_salary' => 300000,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.rh.module', 'personnel'))
            ->delete(route('admin.rh.employees.destroy', $koffi))
            ->assertSessionHasErrors(['employee' => 'Koffi Yao a un historique (1 bulletin(s) de paie) : mettez fin à son contrat plutôt que de supprimer la fiche, l’historique doit être conservé.']);
        $this->assertNotNull($koffi->fresh());
        $this->assertSame(1, Payroll::withoutGlobalScope('entreprise')->count());
    }

    public function test_une_fiche_sans_historique_se_supprime_mais_le_compte_est_seulement_desactive(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $mariam = $this->employee($alpha, 'Mariam Ouattara');
        $userId = $mariam->user_id;

        $this->actingAs($admin)
            ->from(route('admin.rh.module', 'personnel'))
            ->delete(route('admin.rh.employees.destroy', $mariam))
            ->assertSessionHasNoErrors();

        $this->assertNull(Employee::withoutGlobalScope('entreprise')->find($mariam->id));
        // Le compte signe les documents qu'il a créés : il est désactivé, pas supprimé.
        $user = User::find($userId);
        $this->assertNotNull($user);
        $this->assertFalse((bool) $user->is_active);
    }

    public function test_la_fin_de_contrat_desactive_le_compte_et_ne_se_repete_pas(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $koffi = $this->employee($alpha, 'Koffi Yao');

        $this->actingAs($admin)
            ->from(route('admin.rh.module', 'personnel'))
            ->patch(route('admin.rh.employees.terminate', $koffi))
            ->assertSessionHasNoErrors();

        $koffi->refresh();
        $this->assertSame('inactive', $koffi->status);
        $this->assertSame('2026-09-12', $koffi->contract_end_date->toDateString());
        $this->assertFalse((bool) $koffi->user->fresh()->is_active);

        // Deuxième clôture : refus lisible, la date d'origine est conservée.
        $this->actingAs($admin)
            ->from(route('admin.rh.module', 'personnel'))
            ->patch(route('admin.rh.employees.terminate', $koffi))
            ->assertSessionHasErrors(['employee' => 'Le contrat de Koffi Yao est déjà clôturé.']);
    }

    public function test_le_personnel_d_une_autre_entreprise_reste_invisible(): void
    {
        $beta = $this->makeCompany('beta');
        $this->employee($beta, 'Employé confidentiel beta');

        $this->actingAs($this->makeAdmin($this->makeCompany('alpha')))
            ->get(route('admin.rh.module', 'personnel'))
            ->assertOk()
            ->assertDontSee('Employé confidentiel beta')
            ->assertSee('Aucun employé enregistré');
    }
}
