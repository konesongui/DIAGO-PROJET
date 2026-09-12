<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\Employee;
use App\Models\Entreprise;
use App\Models\Payroll;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Paie : génération d'un bulletin, sortie d'argent, fiche, PDF, envoi
 * par e-mail et livre de paie.
 */
class PayrollPageTest extends TestCase
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

    private function employee(Entreprise $entreprise, string $name, float $salary = 400000, array $attributes = []): Employee
    {
        $slug = str($name)->slug();
        $user = User::create([
            'name' => $name, 'email' => $slug . '@' . $entreprise->slug . '.test', 'password' => Hash::make('password'),
            'entreprise_id' => $entreprise->id, 'role_id' => Role::firstOrCreate(['name' => 'employee'], ['label' => 'Employé'])->id, 'is_active' => true,
        ]);

        return Employee::withoutGlobalScope('entreprise')->create(array_merge([
            'entreprise_id' => $entreprise->id, 'user_id' => $user->id, 'full_name' => $name, 'matricule' => 'EMP-' . $user->id,
            'position' => 'Comptable', 'department' => 'Comptabilité', 'contract_type' => 'CDI', 'hire_date' => '2024-01-08',
            'status' => 'active', 'email' => $slug . '@rh.test', 'monthly_salary' => $salary, 'cnps_number' => '19456789',
        ], $attributes));
    }

    private function cashAccount(Entreprise $entreprise, float $balance = 5000000): CashAccount
    {
        return CashAccount::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'name' => 'Caisse principale', 'balance' => $balance, 'is_active' => true,
        ]);
    }

    public function test_la_generation_sort_le_net_de_la_caisse_et_le_bulletin_est_complet(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $koffi = $this->employee($alpha, 'Koffi Yao', 400000);
        $cash = $this->cashAccount($alpha);

        $this->actingAs($admin)
            ->from(route('admin.rh.payroll.create'))
            ->post(route('admin.rh.payroll.generate'), [
                'employee_id' => $koffi->id, 'month' => 8, 'year' => 2026, 'monthly_salary' => 400000,
                'transport_allowance' => 30000, 'payment_mode' => 'cash', 'cash_account_id' => $cash->id,
                'part_igr' => 1, 'children_count' => 0,
            ])->assertSessionHasNoErrors();

        $payroll = Payroll::withoutGlobalScope('entreprise')->firstOrFail();
        $this->assertSame(430000.0, (float) $payroll->gross_salary);
        // Le net est sorti de la caisse choisie.
        $this->assertSame(5000000.0 - (float) $payroll->net_salary, (float) $cash->fresh()->balance);
        $this->assertSame(1, CashMovement::withoutGlobalScope('entreprise')->where('reference', 'PAY-' . $payroll->id)->count());

        $this->actingAs($admin)->get(route('admin.rh.payroll.show', $payroll))
            ->assertOk()
            // La fiche s'affiche dans l'application, avec la navigation.
            ->assertSee('Bulletin de paie')
            ->assertSee('Koffi Yao')
            ->assertSeeInOrder(['Salaire brut', money(430000), 'Retenues salariales', 'Net à payer'])
            ->assertSee('Charges patronales')
            ->assertSee('Pas encore envoyé');

        $this->actingAs($admin)->get(route('admin.rh.payroll.pdf', $payroll))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_regenerer_un_bulletin_corrige_la_sortie_d_argent(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $koffi = $this->employee($alpha, 'Koffi Yao', 400000);
        $cash = $this->cashAccount($alpha);
        $post = fn (array $data) => $this->actingAs($admin)->from(route('admin.rh.payroll.create'))
            ->post(route('admin.rh.payroll.generate'), $data + [
                'employee_id' => $koffi->id, 'month' => 8, 'year' => 2026,
                'payment_mode' => 'cash', 'cash_account_id' => $cash->id, 'part_igr' => 1, 'children_count' => 0,
            ]);

        $post(['monthly_salary' => 400000])->assertSessionHasNoErrors();
        $first = Payroll::withoutGlobalScope('entreprise')->firstOrFail();
        $this->assertSame(5000000.0 - (float) $first->net_salary, (float) $cash->fresh()->balance);

        // Bulletin corrigé : l'ancien mouvement de caisse doit être repris,
        // sinon la caisse restait sur l'ancien net.
        $post(['monthly_salary' => 500000])->assertSessionHasNoErrors();
        $second = Payroll::withoutGlobalScope('entreprise')->firstOrFail();
        $this->assertSame(1, Payroll::withoutGlobalScope('entreprise')->count());
        $this->assertSame(1, CashMovement::withoutGlobalScope('entreprise')->where('reference', 'PAY-' . $second->id)->count());
        $this->assertSame(5000000.0 - (float) $second->net_salary, (float) $cash->fresh()->balance);
        $this->assertNotSame((float) $first->net_salary, (float) $second->net_salary);
    }

    public function test_un_contrat_cloture_ne_donne_plus_de_bulletin(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $parti = $this->employee($alpha, 'Fatou Camara', 400000, ['status' => 'inactive', 'contract_end_date' => '2026-06-30']);
        $cash = $this->cashAccount($alpha);

        $this->actingAs($admin)->from(route('admin.rh.payroll.create'))
            ->post(route('admin.rh.payroll.generate'), [
                'employee_id' => $parti->id, 'month' => 8, 'year' => 2026, 'monthly_salary' => 400000,
                'payment_mode' => 'cash', 'cash_account_id' => $cash->id,
            ])
            ->assertSessionHasErrors(['employee_id' => 'Choisissez un employé en poste : un contrat clôturé ne donne plus lieu à bulletin.']);
        $this->assertSame(0, Payroll::withoutGlobalScope('entreprise')->count());

        // Caisse absente : refus lisible, pas de page d'erreur.
        $koffi = $this->employee($alpha, 'Koffi Yao');
        $this->actingAs($admin)->from(route('admin.rh.payroll.create'))
            ->post(route('admin.rh.payroll.generate'), [
                'employee_id' => $koffi->id, 'month' => 8, 'year' => 2026, 'monthly_salary' => 400000, 'payment_mode' => 'cash',
            ])->assertSessionHasErrors('cash_account_id');
    }

    public function test_la_liste_signale_les_employes_sans_bulletin_et_envoie_les_bulletins(): void
    {
        Mail::fake();
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $koffi = $this->employee($alpha, 'Koffi Yao', 400000);
        $awa = $this->employee($alpha, 'Awa Koné', 650000);
        $payroll = Payroll::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'employee_id' => $koffi->id, 'month' => 8, 'year' => 2026,
            'base_salary' => 400000, 'gross_salary' => 430000, 'net_salary' => 380000,
            'total_employee_deductions' => 50000, 'total_employer_deductions' => 90000, 'employer_charges' => 90000,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.rh.payroll', ['month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertSeeInOrder(['Bulletins', '1', '1 employé(s) sans bulletin ce mois'])
            ->assertSeeInOrder(['Koffi Yao', money(430000), money(380000), 'À envoyer'])
            ->assertSee('Awa Koné');   // signalée comme sans bulletin

        $this->actingAs($admin)->from(route('admin.rh.payroll'))
            ->post(route('admin.rh.payroll.emailBulk'), ['payroll_ids' => [$payroll->id]])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($payroll->fresh()->sent_at);
    }

    public function test_le_livre_de_paie_totalise_la_periode(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $koffi = $this->employee($alpha, 'Koffi Yao', 400000);
        $awa = $this->employee($alpha, 'Awa Koné', 650000);
        foreach ([[$koffi, 7, 380000], [$koffi, 8, 390000], [$awa, 8, 600000]] as [$employee, $month, $net]) {
            Payroll::withoutGlobalScope('entreprise')->create([
                'entreprise_id' => $alpha->id, 'employee_id' => $employee->id, 'month' => $month, 'year' => 2026,
                'base_salary' => $net, 'gross_salary' => $net + 40000, 'net_salary' => $net,
                'total_employee_deductions' => 40000, 'total_employer_deductions' => 70000, 'employer_charges' => 70000,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.rh.payrollBook', ['period' => 'last_3_months']))
            ->assertOk()
            ->assertSeeInOrder(['Bulletins', '3', '2 employé(s) payé(s)'])
            ->assertSee(money(1370000))   // net cumulé
            // Classement par net cumulé : Koffi (770 000 sur deux mois) devant Awa (600 000).
            ->assertSeeInOrder(['Total par employé', 'Koffi Yao', 'Awa Koné'])
            ->assertSeeInOrder(['Mois par mois', 'Août 2026', 'Juillet 2026']);

        // Période choisie invalide : message, pas de page d'erreur.
        $this->actingAs($admin)
            ->get(route('admin.rh.payrollBook', ['period' => 'custom', 'from' => '2026-09-30', 'to' => '2026-01-01']))
            ->assertOk()
            ->assertSee('La période choisie est invalide');
    }

    public function test_les_bulletins_d_une_autre_entreprise_restent_inaccessibles(): void
    {
        $beta = $this->makeCompany('beta');
        $employee = $this->employee($beta, 'Employé confidentiel beta');
        $payroll = Payroll::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $beta->id, 'employee_id' => $employee->id, 'month' => 8, 'year' => 2026,
            'base_salary' => 400000, 'gross_salary' => 430000, 'net_salary' => 380000,
        ]);

        $admin = $this->makeAdmin($this->makeCompany('alpha'));
        $this->actingAs($admin)->get(route('admin.rh.payroll', ['month' => 8, 'year' => 2026]))
            ->assertOk()->assertDontSee('Employé confidentiel beta');
        $this->actingAs($admin)->get(route('admin.rh.payroll.show', $payroll))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.rh.payrollBook'))
            ->assertOk()->assertDontSee('Employé confidentiel beta');
    }
}
