<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Entreprise;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Congés : paramétrage des types, demandes avec solde annuel et
 * chevauchements, validation, et calendrier du mois.
 */
class LeavesPageTest extends TestCase
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

    private function employee(Entreprise $entreprise, string $name): Employee
    {
        $slug = str($name)->slug();
        $user = User::create([
            'name' => $name, 'email' => $slug . '@' . $entreprise->slug . '.test', 'password' => Hash::make('password'),
            'entreprise_id' => $entreprise->id, 'role_id' => Role::firstOrCreate(['name' => 'employee'], ['label' => 'Employé'])->id, 'is_active' => true,
            // Droit d'accès à ses propres congés, comme un employé à qui l'entreprise l'ouvre.
            'permissions' => ['leaves' => ['view' => true, 'edit' => true]],
        ]);

        return Employee::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'user_id' => $user->id, 'full_name' => $name, 'matricule' => 'EMP-' . $user->id,
            'position' => 'Comptable', 'department' => 'Comptabilité', 'contract_type' => 'CDI', 'hire_date' => '2025-01-06',
            'status' => 'active', 'email' => $slug . '@rh.test', 'monthly_salary' => 350000,
        ]);
    }

    private function leaveType(Entreprise $entreprise, string $name = 'Congé annuel', int $days = 30): LeaveType
    {
        return LeaveType::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'name' => $name, 'days' => $days, 'is_active' => true,
        ]);
    }

    public function test_l_administration_enregistre_un_conge_valide_pour_un_employe(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $awa = $this->employee($alpha, 'Awa Koné');
        $type = $this->leaveType($alpha);

        $this->actingAs($admin)
            ->from(route('admin.rh.leaves'))
            ->post(route('admin.rh.leaves.store'), [
                'employee_id' => $awa->id, 'leave_type_id' => $type->id,
                'start_date' => '2026-09-10', 'end_date' => '2026-09-19', 'reason' => 'Congé annuel',
            ])->assertSessionHasNoErrors();

        $leave = LeaveRequest::withoutGlobalScope('entreprise')->firstOrFail();
        $this->assertSame('approved', $leave->status);
        $this->assertSame(10, $leave->days);
        $this->assertSame($admin->id, $leave->reviewed_by);

        $this->actingAs($admin)
            ->get(route('admin.rh.leaves'))
            ->assertOk()
            ->assertSeeInOrder(['En attente', '0', 'Validés en 2026', '1', 'En congé aujourd’hui', '1'])
            // Les états ne s'affichent plus en anglais dans le tableau.
            ->assertSeeInOrder(['Awa Koné', 'Congé annuel', '10/09/2026', 'au 19/09/2026', 'Validé', 'en cours, retour le 20/09/2026']);
    }

    public function test_le_solde_annuel_et_les_chevauchements_sont_controles(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $koffi = $this->employee($alpha, 'Koffi Yao');
        $type = $this->leaveType($alpha, 'Congé annuel', 30);

        $post = fn (array $data) => $this->actingAs($admin)->from(route('admin.rh.leaves'))
            ->post(route('admin.rh.leaves.store'), $data + ['employee_id' => $koffi->id, 'leave_type_id' => $type->id]);

        $post(['start_date' => '2026-02-02', 'end_date' => '2026-02-26'])->assertSessionHasNoErrors();   // 25 jours

        // Le solde annuel restant est de 5 jours : une demande de 10 est refusée.
        $post(['start_date' => '2026-07-01', 'end_date' => '2026-07-10'])
            ->assertSessionHasErrors(['end_date' => 'Koffi Yao a déjà pris 25 jour(s) de « Congé annuel » en 2026 : il reste 5 jour(s) sur 30.']);

        // Chevauchement avec le congé de février.
        $post(['start_date' => '2026-02-20', 'end_date' => '2026-02-22'])
            ->assertSessionHasErrors(['start_date' => 'Koffi Yao a déjà un congé sur cette période (Congé annuel du 02/02/2026 au 26/02/2026).']);

        // Une demande qui tient dans le solde et hors période passe.
        $post(['start_date' => '2026-11-02', 'end_date' => '2026-11-06'])->assertSessionHasNoErrors();
        $this->assertSame(2, LeaveRequest::withoutGlobalScope('entreprise')->count());
        // L'année suivante, le droit est de nouveau entier.
        $post(['start_date' => '2027-01-05', 'end_date' => '2027-01-24'])->assertSessionHasNoErrors();
    }

    public function test_une_demande_deja_traitee_ne_se_retraite_pas(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $koffi = $this->employee($alpha, 'Koffi Yao');
        $type = $this->leaveType($alpha);
        $request = LeaveRequest::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'employee_id' => $koffi->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-10-05', 'end_date' => '2026-10-09', 'days' => 5, 'status' => 'pending',
        ]);

        $this->actingAs($admin)->from(route('admin.rh.leaves'))
            ->patch(route('admin.rh.leaves.review', $request), ['status' => 'approved', 'review_comment' => 'Bon congé'])
            ->assertSessionHasNoErrors();
        $this->assertSame('approved', $request->fresh()->status);

        $this->actingAs($admin)->from(route('admin.rh.leaves'))
            ->patch(route('admin.rh.leaves.review', $request), ['status' => 'rejected'])
            ->assertSessionHasErrors(['request' => 'Cette demande a déjà été traitée (validée).']);
        $this->assertSame('approved', $request->fresh()->status);
    }

    public function test_un_employe_demande_un_conge_et_voit_son_solde(): void
    {
        $alpha = $this->makeCompany('alpha');
        $this->makeAdmin($alpha);
        $koffi = $this->employee($alpha, 'Koffi Yao');
        $type = $this->leaveType($alpha, 'Congé annuel', 30);
        LeaveRequest::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'employee_id' => $koffi->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-03-02', 'end_date' => '2026-03-13', 'days' => 12, 'status' => 'approved',
        ]);

        $this->actingAs($koffi->user)
            ->get(route('admin.rh.leaves'))
            ->assertOk()
            ->assertSee('Vos soldes 2026')
            ->assertSee('il vous reste 18 jour(s)')
            ->assertSee('Demander un congé');

        $this->actingAs($koffi->user)->from(route('admin.rh.leaves'))
            ->post(route('admin.rh.leaves.store'), ['leave_type_id' => $type->id, 'start_date' => '2026-12-01', 'end_date' => '2026-12-05'])
            ->assertSessionHasNoErrors();
        // Une demande d'employé attend la validation de l'administration.
        $this->assertSame('pending', LeaveRequest::withoutGlobalScope('entreprise')->latest('id')->first()->status);
    }

    public function test_un_type_de_conge_utilise_se_ferme_mais_ne_se_supprime_pas(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $koffi = $this->employee($alpha, 'Koffi Yao');
        $type = $this->leaveType($alpha, 'Congé annuel', 30);

        $this->actingAs($admin)->from(route('admin.rh.leaveTypes'))
            ->post(route('admin.rh.leaveTypes.store'), ['_leave_type_form' => 1, 'name' => 'congé annuel', 'days' => 20])
            ->assertSessionHasErrors(['name' => 'Un type de congé porte déjà ce nom.']);

        LeaveRequest::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'employee_id' => $koffi->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-05-04', 'end_date' => '2026-05-08', 'days' => 5, 'status' => 'approved',
        ]);

        $this->actingAs($admin)->from(route('admin.rh.leaveTypes'))
            ->delete(route('admin.rh.leaveTypes.destroy', $type))
            ->assertSessionHasErrors(['type' => 'Des demandes utilisent le congé « Congé annuel » : désactivez-le plutôt, l’historique doit rester lisible.']);

        $this->actingAs($admin)->from(route('admin.rh.leaveTypes'))
            ->put(route('admin.rh.leaveTypes.update', $type), ['_leave_type_form' => 1, 'name' => 'Congé annuel', 'days' => 30])
            ->assertSessionHasNoErrors();
        $this->assertFalse((bool) $type->fresh()->is_active);

        $this->actingAs($admin)->get(route('admin.rh.leaveTypes'))
            ->assertOk()
            ->assertSeeInOrder(['Congé annuel', '30', '1 demande(s)', '5 jour(s) validé(s)', 'Fermé']);
    }

    public function test_le_calendrier_montre_le_mois_choisi(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $awa = $this->employee($alpha, 'Awa Koné');
        $koffi = $this->employee($alpha, 'Koffi Yao');
        $type = $this->leaveType($alpha);
        $leave = fn ($employee, $from, $to, $status) => LeaveRequest::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'start_date' => $from, 'end_date' => $to, 'days' => Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1, 'status' => $status,
        ]);
        $leave($awa, '2026-09-28', '2026-10-05', 'approved');    // à cheval sur octobre
        $leave($koffi, '2026-10-12', '2026-10-16', 'approved');
        $leave($koffi, '2026-11-02', '2026-11-06', 'approved');  // autre mois
        $leave($awa, '2026-10-20', '2026-10-22', 'pending');     // pas encore validé

        $this->actingAs($admin)
            ->get(route('admin.rh.leaveCalendar', ['mois' => '2026-10']))
            ->assertOk()
            ->assertSee('Octobre 2026')
            ->assertSee('Awa Koné')
            ->assertSee('Koffi Yao')
            ->assertSee('Employés en congé')
            ->assertSee('Demandes en attente')
            // Awa : 5 jours d'octobre sur son congé à cheval ; Koffi : 5 jours.
            ->assertSee('5 jour(s)');

        $this->actingAs($admin)
            ->get(route('admin.rh.leaveCalendar', ['mois' => '2026-12']))
            ->assertOk()
            ->assertSee('Aucun congé validé en décembre 2026');
    }

    public function test_les_conges_d_une_autre_entreprise_restent_invisibles(): void
    {
        $beta = $this->makeCompany('beta');
        $employee = $this->employee($beta, 'Employé confidentiel beta');
        $type = $this->leaveType($beta, 'Congé beta');
        LeaveRequest::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $beta->id, 'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-09-10', 'end_date' => '2026-09-14', 'days' => 5, 'status' => 'approved',
        ]);

        $admin = $this->makeAdmin($this->makeCompany('alpha'));
        $this->actingAs($admin)->get(route('admin.rh.leaves'))->assertOk()
            ->assertDontSee('Employé confidentiel beta')->assertSee('Aucune demande de congé');
        $this->actingAs($admin)->get(route('admin.rh.leaveTypes'))->assertOk()
            ->assertDontSee('Congé beta')->assertSee('Aucun type de congé');
        $this->actingAs($admin)->get(route('admin.rh.leaveCalendar'))->assertOk()
            ->assertDontSee('Employé confidentiel beta');
    }
}
