<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Entreprise;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\QrToken;
use App\Models\Role;
use App\Models\StaffAttendanceQr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Présences QR : code de pointage stable, présences et absents du jour,
 * rapport de période avec horaires de l'entreprise.
 */
class AttendancePageTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-14 15:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function employee(Entreprise $entreprise, string $name, string $matricule): Employee
    {
        $slug = str($name)->slug();
        $user = User::create([
            'name' => $name, 'email' => $slug . '@' . $entreprise->slug . '.test', 'password' => Hash::make('password'),
            'entreprise_id' => $entreprise->id, 'role_id' => Role::firstOrCreate(['name' => 'employee'], ['label' => 'Employé'])->id, 'is_active' => true,
        ]);

        return Employee::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'user_id' => $user->id, 'full_name' => $name, 'matricule' => $matricule,
            'position' => 'Comptable', 'department' => 'Comptabilité', 'contract_type' => 'CDI', 'hire_date' => '2025-01-06',
            'status' => 'active', 'email' => $slug . '@rh.test', 'monthly_salary' => 350000,
        ]);
    }

    private function attendance(Employee $employee, string $date, ?string $arrival, ?string $departure): StaffAttendanceQr
    {
        return StaffAttendanceQr::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $employee->entreprise_id, 'employee_id' => $employee->id, 'attendance_date' => $date,
            'arrival_time' => $arrival, 'departure_time' => $departure, 'scan_date' => $date . ' ' . ($arrival ?: '08:00:00'),
            'status' => $departure ? 'complete' : 'arrival', 'verification_status' => 'verified',
        ]);
    }

    public function test_le_code_de_pointage_ne_change_pas_a_chaque_affichage(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);

        $this->actingAs($admin)->get(route('admin.rh.qr.display'))->assertOk()->assertSee('Code de pointage');
        $first = QrToken::withoutGlobalScope('entreprise')->firstOrFail();

        // Revenir sur la page ne doit pas invalider le code déjà affiché ou imprimé.
        $this->actingAs($admin)->get(route('admin.rh.qr.display'))->assertOk()->assertSee($first->token);
        $this->assertSame(1, QrToken::withoutGlobalScope('entreprise')->count());
        $this->assertFalse((bool) $first->fresh()->is_used);

        $this->actingAs($admin)->from(route('admin.rh.qr.display'))
            ->post(route('admin.rh.qr.renew'))->assertSessionHasNoErrors();
        $this->assertTrue((bool) $first->fresh()->is_used);
        $this->assertSame(2, QrToken::withoutGlobalScope('entreprise')->count());
    }

    public function test_les_presences_du_jour_distinguent_presents_absents_et_conges(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $awa = $this->employee($alpha, 'Awa Koné', 'EMP-001');
        $koffi = $this->employee($alpha, 'Koffi Yao', 'EMP-002');
        $mariam = $this->employee($alpha, 'Mariam Ouattara', 'EMP-003');
        $ali = $this->employee($alpha, 'Ali Traoré', 'EMP-004');

        $this->attendance($awa, '2026-09-14', '07:52:00', '17:05:00');
        $this->attendance($koffi, '2026-09-14', '08:15:00', null);
        $type = LeaveType::withoutGlobalScope('entreprise')->create(['entreprise_id' => $alpha->id, 'name' => 'Congé annuel', 'days' => 30, 'is_active' => true]);
        LeaveRequest::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'employee_id' => $mariam->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-09-10', 'end_date' => '2026-09-18', 'days' => 9, 'status' => 'approved',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.rh.attendance.today'))
            ->assertOk()
            // Présents 2 sur 4, un seul encore au travail, un seul absent (Ali) : Mariam est en congé.
            ->assertSeeInOrder(['Présents', '2', 'sur 4 employé(s) en poste', 'Encore au travail', '1', 'Absents', '1', '1 en congé validé, non comptés'])
            ->assertSeeInOrder(['Awa Koné', '07:52', '17:05', '9 h 13', 'Journée complète'])
            ->assertSeeInOrder(['Koffi Yao', '08:15', 'Au travail'])
            ->assertSeeInOrder(['Absents du jour', 'Ali Traoré'])
            ->assertSee('En congé validé aujourd’hui');
    }

    public function test_la_recherche_du_rapport_fonctionne_et_les_horaires_se_reglent_a_part(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $awa = $this->employee($alpha, 'Awa Koné', 'EMP-001');
        $koffi = $this->employee($alpha, 'Koffi Yao', 'EMP-002');
        $this->attendance($awa, '2026-09-01', '08:00:00', '17:00:00');
        $this->attendance($awa, '2026-09-02', '08:45:00', '17:00:00');   // en retard
        $this->attendance($koffi, '2026-08-20', '08:00:00', '17:00:00'); // hors période

        // La recherche passe par l'URL : avant, elle déclenchait la validation
        // des horaires et n'aboutissait jamais.
        $response = $this->actingAs($admin)
            ->get(route('admin.rh.attendance.report', ['from' => '2026-09-01', 'to' => '2026-09-30']))
            ->assertOk()
            ->assertSessionHasNoErrors()
            ->assertSee('Awa Koné')
            // 8 h le 1er, 7 h 15 le 2 (arrivée à 08:45), pause d'une heure déduite chaque jour.
            ->assertSeeInOrder(['Jours pointés', '2', 'Heures travaillées', '15 h 15', 'Retards', '1'])
            ->assertSee('16 h 00');   // attendu : 8 h par jour pointé
        // Seuls les pointages de la période sont comptés (le filtre « employé » liste, lui, tout le personnel).
        $this->assertSame(2, $response->viewData('summary')['records']);
        $this->assertSame(['Awa Koné'], $response->viewData('employeeTotals')->map(fn ($total) => $total['employee']->full_name)->all());

        // Filtre par employé.
        $this->actingAs($admin)
            ->get(route('admin.rh.attendance.report', ['from' => '2026-08-01', 'to' => '2026-09-30', 'employee_id' => $koffi->id]))
            ->assertOk()
            ->assertSee('Koffi Yao');
        $this->assertSame(['Koffi Yao'], $this->actingAs($admin)
            ->get(route('admin.rh.attendance.report', ['from' => '2026-08-01', 'to' => '2026-09-30', 'employee_id' => $koffi->id]))
            ->viewData('employeeTotals')->map(fn ($total) => $total['employee']->full_name)->all());

        // Période incohérente : message, pas de page d'erreur.
        $this->actingAs($admin)
            ->get(route('admin.rh.attendance.report', ['from' => '2026-09-30', 'to' => '2026-09-01']))
            ->assertOk()
            ->assertSee('La période choisie est invalide');

        // Les horaires se règlent par leur propre formulaire.
        $this->actingAs($admin)->from(route('admin.rh.attendance.report'))
            ->post(route('admin.rh.attendance.report'), [
                '_schedule_form' => 1, 'start_time' => '07:30', 'break_start' => '12:30',
                'break_end' => '13:30', 'end_time' => '16:30', 'regular_hours' => 160,
            ])->assertSessionHasNoErrors();
        $this->assertSame('07:30', $alpha->fresh()->settings['attendance_schedule']['qr_attendance_start_time']);

        // Horaires incohérents : refus lisible.
        $this->actingAs($admin)->from(route('admin.rh.attendance.report'))
            ->post(route('admin.rh.attendance.report'), [
                '_schedule_form' => 1, 'start_time' => '08:00', 'break_start' => '12:00',
                'break_end' => '11:00', 'end_time' => '17:00', 'regular_hours' => 160,
            ])->assertSessionHasErrors(['break_end' => 'La reprise suit le début de la pause.']);
    }

    public function test_les_pointages_d_une_autre_entreprise_restent_invisibles(): void
    {
        $beta = $this->makeCompany('beta');
        $employee = $this->employee($beta, 'Employé confidentiel beta', 'BETA-1');
        $this->attendance($employee, '2026-09-14', '08:00:00', '17:00:00');

        $admin = $this->makeAdmin($this->makeCompany('alpha'));
        $this->actingAs($admin)->get(route('admin.rh.attendance.today'))->assertOk()
            ->assertDontSee('Employé confidentiel beta')->assertSee('Aucun pointage aujourd’hui');
        $this->actingAs($admin)->get(route('admin.rh.attendance.report'))->assertOk()
            ->assertDontSee('Employé confidentiel beta')->assertSee('Aucun pointage sur la période');
    }
}
