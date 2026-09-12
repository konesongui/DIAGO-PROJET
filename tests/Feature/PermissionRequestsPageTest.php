<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Entreprise;
use App\Models\PermissionRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Demandes de permission : absences courtes, saisie par l'employé ou par
 * l'administration, validation unique et motifs normalisés.
 */
class PermissionRequestsPageTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-14 09:00:00');
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
            'entreprise_id' => $entreprise->id, 'role_id' => Role::firstOrCreate(['name' => 'employee'], ['label' => 'Employé'])->id,
            'is_active' => true, 'permissions' => ['permission_requests' => ['view' => true, 'edit' => true]],
        ]);

        return Employee::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'user_id' => $user->id, 'full_name' => $name, 'matricule' => 'EMP-' . $user->id,
            'position' => 'Comptable', 'department' => 'Comptabilité', 'contract_type' => 'CDI', 'hire_date' => '2025-01-06',
            'status' => 'active', 'email' => $slug . '@rh.test', 'monthly_salary' => 350000,
        ]);
    }

    public function test_l_administration_enregistre_une_permission_acceptee(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $awa = $this->employee($alpha, 'Awa Koné');

        $this->actingAs($admin)->from(route('admin.rh.permissions'))
            ->post(route('admin.rh.permissions.store'), [
                '_permission_form' => 1, 'employee_id' => $awa->id, 'type' => 'medical',
                'start_date' => '2026-09-14', 'end_date' => '2026-09-15', 'reason' => 'Rendez-vous à la polyclinique',
            ])->assertSessionHasNoErrors();

        $request = PermissionRequest::withoutGlobalScope('entreprise')->firstOrFail();
        $this->assertSame('approved', $request->status);
        $this->assertSame($admin->id, $request->reviewed_by);

        $this->actingAs($admin)->get(route('admin.rh.permissions'))
            ->assertOk()
            // Le motif s'affiche en clair, plus le code brut saisi en base.
            ->assertSeeInOrder(['Awa Koné', 'Rendez-vous médical', '14/09/2026', 'au 15/09/2026', '2', 'Acceptée'])
            ->assertSeeInOrder(['Absences en cours', '1']);

        // Chevauchement refusé.
        $this->actingAs($admin)->from(route('admin.rh.permissions'))
            ->post(route('admin.rh.permissions.store'), [
                '_permission_form' => 1, 'employee_id' => $awa->id, 'type' => 'family',
                'start_date' => '2026-09-15', 'end_date' => '2026-09-16', 'reason' => 'Baptême',
            ])->assertSessionHasErrors(['start_date' => 'Awa Koné a déjà une permission sur cette période (du 14/09/2026 au 15/09/2026).']);
    }

    public function test_une_demande_d_employe_attend_la_validation_et_ne_se_traite_qu_une_fois(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $koffi = $this->employee($alpha, 'Koffi Yao');

        $this->actingAs($koffi->user)->from(route('admin.rh.permissions'))
            ->post(route('admin.rh.permissions.store'), [
                '_permission_form' => 1, 'type' => 'administrative',
                'start_date' => '2026-09-21', 'end_date' => '2026-09-21', 'reason' => 'Retrait de la carte nationale d’identité',
            ])->assertSessionHasNoErrors();

        $request = PermissionRequest::withoutGlobalScope('entreprise')->firstOrFail();
        $this->assertSame('pending', $request->status);

        $this->actingAs($koffi->user)->get(route('admin.rh.permissions'))
            ->assertOk()
            ->assertSee('Démarche administrative')
            ->assertSee('Demander une permission');

        $this->actingAs($admin)->from(route('admin.rh.permissions'))
            ->patch(route('admin.rh.permissions.review', $request), ['status' => 'approved', 'review_comment' => 'Accordé'])
            ->assertSessionHasNoErrors();
        $this->assertSame('approved', $request->fresh()->status);

        $this->actingAs($admin)->from(route('admin.rh.permissions'))
            ->patch(route('admin.rh.permissions.review', $request), ['status' => 'rejected'])
            ->assertSessionHasErrors(['request' => 'Cette demande a déjà été traitée (acceptée).']);
        $this->assertSame('approved', $request->fresh()->status);
    }

    public function test_les_permissions_d_une_autre_entreprise_restent_invisibles(): void
    {
        $beta = $this->makeCompany('beta');
        $employee = $this->employee($beta, 'Employé confidentiel beta');
        PermissionRequest::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $beta->id, 'employee_id' => $employee->id, 'type' => 'medical',
            'start_date' => '2026-09-14', 'end_date' => '2026-09-14', 'reason' => 'Consultation', 'status' => 'pending',
        ]);

        $this->actingAs($this->makeAdmin($this->makeCompany('alpha')))
            ->get(route('admin.rh.permissions'))
            ->assertOk()
            ->assertDontSee('Employé confidentiel beta')
            ->assertSee('Aucune demande de permission');
    }
}
