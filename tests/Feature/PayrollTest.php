<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Employee;
use App\Models\Entreprise;
use App\Models\Payroll;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Calcul de la paie.
 *
 * Un bulletin faux expose l'entreprise a un redressement et le salarie a une
 * perte. Ces tests fixent les regles de calcul en vigueur (bareme ivoirien)
 * pour qu'une modification ne les change pas par accident.
 */
class PayrollTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private const CNPS_RATE = 0.063;
    private const CNPS_CEILING = 3375000;
    private const TRANSPORT_EXEMPTION = 30000;

    private function employee(Entreprise $company, float $salary = 500000): Employee
    {
        return Employee::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $company->id,
            'full_name' => 'Salarié Test',
            'email' => 'salarie@' . $company->slug . '.test',
            'position' => 'Agent', 'department' => 'Test', 'status' => 'active',
            'monthly_salary' => $salary, 'hire_date' => now()->subYears(3)->toDateString(),
        ]);
    }

    private function bankAccount(Entreprise $company): BankAccount
    {
        return BankAccount::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $company->id, 'name' => 'Compte principal',
            'bank_name' => 'Banque Test', 'account_number' => 'CI0001', 'current_balance' => 10000000,
        ]);
    }

    private array $admins = [];

    private function generate(Entreprise $company, Employee $employee, array $overrides = []): Payroll
    {
        $admin = $this->admins[$company->id] ??= $this->makeAdmin($company);

        $response = $this->actingAs($admin)->post(route('admin.rh.payroll.generate'), array_merge([
            'employee_id' => $employee->id,
            'month' => 6, 'year' => 2026,
            'payment_mode' => 'bank',
            'bank_account_id' => $this->bankAccount($company)->id,
        ], $overrides));

        $response->assertSessionHasNoErrors();

        return Payroll::withoutGlobalScope('entreprise')
            ->where('employee_id', $employee->id)->firstOrFail();
    }

    public function test_le_brut_est_le_salaire_de_base_plus_les_primes(): void
    {
        $company = $this->makeCompany();
        $employee = $this->employee($company, 500000);

        $payroll = $this->generate($company, $employee, [
            'transport_allowance' => 30000,
            'bonus' => 50000,
        ]);

        $this->assertSame(500000.0, (float) $payroll->base_salary);
        $this->assertSame(80000.0, (float) $payroll->allowances);
        $this->assertSame(580000.0, (float) $payroll->gross_salary);
    }

    public function test_le_net_est_le_brut_moins_les_retenues_salariales(): void
    {
        $company = $this->makeCompany();
        $employee = $this->employee($company, 500000);

        $payroll = $this->generate($company, $employee);

        $expected = (float) $payroll->gross_salary - (float) $payroll->total_employee_deductions;

        $this->assertSame(round($expected, 2), round((float) $payroll->net_salary, 2));
    }

    public function test_la_cotisation_retraite_suit_le_taux_salarial(): void
    {
        $company = $this->makeCompany();
        $employee = $this->employee($company, 500000);

        $payroll = $this->generate($company, $employee, ['transport_allowance' => 30000]);

        // L'indemnite de transport est exclue de l'assiette sociale.
        $socialGross = (float) $payroll->gross_salary - 30000;

        $this->assertSame(round($socialGross, 2), round((float) $payroll->social_gross, 2));
        $this->assertSame(
            round($socialGross * self::CNPS_RATE, 2),
            round((float) $payroll->cnps_employee, 2)
        );
    }

    /** Au-dela du plafond, la cotisation cesse d'augmenter. */
    public function test_la_cotisation_retraite_est_plafonnee(): void
    {
        $company = $this->makeCompany();
        $employee = $this->employee($company, 5000000);

        $payroll = $this->generate($company, $employee);

        $this->assertSame(
            round(self::CNPS_CEILING * self::CNPS_RATE, 2),
            round((float) $payroll->cnps_employee, 2),
            'Au-delà du plafond, la cotisation est figée.'
        );
    }

    public function test_l_indemnite_de_transport_est_exoneree_jusqu_au_plafond(): void
    {
        $company = $this->makeCompany();

        $exempte = $this->generate($company, $this->employee($company, 500000), [
            'transport_allowance' => self::TRANSPORT_EXEMPTION,
        ]);

        $this->assertSame(500000.0, (float) $exempte->fiscal_gross,
            "Sous le plafond, l'indemnité n'entre pas dans le brut fiscal.");
    }

    public function test_la_part_excedentaire_du_transport_devient_imposable(): void
    {
        $company = $this->makeCompany();
        $employee = $this->employee($company, 500000);

        $payroll = $this->generate($company, $employee, [
            'transport_allowance' => self::TRANSPORT_EXEMPTION + 20000,
        ]);

        $this->assertSame(520000.0, (float) $payroll->fiscal_gross,
            'Seule la fraction au-dessus du plafond est imposable.');
    }

    public function test_la_couverture_maladie_depend_du_nombre_d_enfants(): void
    {
        $company = $this->makeCompany();

        $sansEnfant = $this->generate($company, $this->employee($company, 400000));
        $this->assertSame(500.0, (float) $sansEnfant->cmu);

        $autre = $this->makeCompany('avec-enfants');
        $avecEnfants = $this->generate($autre, $this->employee($autre, 400000), ['children_count' => 3]);
        $this->assertSame(1500.0, (float) $avecEnfants->cmu);
    }

    public function test_le_net_ne_peut_jamais_etre_negatif(): void
    {
        $company = $this->makeCompany();
        $employee = $this->employee($company, 100000);

        $payroll = $this->generate($company, $employee, ['deductions' => 5000000]);

        $this->assertGreaterThanOrEqual(0, (float) $payroll->net_salary);
    }

    public function test_les_retenues_salariales_sont_la_somme_de_leurs_composantes(): void
    {
        $company = $this->makeCompany();
        $employee = $this->employee($company, 600000);

        // Attention : le calcul lit la clé « deductions ». La clé
        // « other_deductions », pourtant acceptée par la validation, est
        // ignorée. Ce test fige le comportement réel.
        $payroll = $this->generate($company, $employee, ['deductions' => 10000]);

        $expected = (float) $payroll->cnps_employee + (float) $payroll->income_tax
            + (float) $payroll->cmu + 10000;

        $this->assertSame(round($expected, 2), round((float) $payroll->total_employee_deductions, 2));
    }

    public function test_un_bulletin_est_unique_par_salarie_et_par_mois(): void
    {
        $company = $this->makeCompany();
        $employee = $this->employee($company, 500000);

        $this->generate($company, $employee);
        $this->generate($company, $employee, ['bonus' => 25000]);

        $this->assertSame(1, Payroll::withoutGlobalScope('entreprise')
            ->where('employee_id', $employee->id)->where('month', 6)->where('year', 2026)->count());
    }
}
