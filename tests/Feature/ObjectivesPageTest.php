<?php

namespace Tests\Feature;

use App\Models\CommercialDelivery;
use App\Models\CommercialInvoice;
use App\Models\CommercialObjective;
use App\Models\CommercialObjectiveAssignment;
use App\Models\CommercialOrder;
use App\Models\CommercialQuote;
use App\Models\CustomInvoice;
use App\Models\Employee;
use App\Models\Entreprise;
use App\Models\PosSale;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Services\SalesRevenueService;
use App\Services\VatDeclarationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Objectifs commerciaux : un objectif annuel de chiffre d'affaires HT,
 * réparti entre les commerciaux et suivi à partir des ventes réelles.
 */
class ObjectivesPageTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    protected function setUp(): void
    {
        parent::setUp();
        // Milieu d'exercice : 182 jours écoulés sur 365.
        Carbon::setTestNow('2026-07-01 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Employé commercial, avec ou sans compte utilisateur. */
    private function seller(Entreprise $entreprise, string $name, bool $withAccount = true, string $status = 'active'): Employee
    {
        $user = $withAccount ? User::create([
            'name' => $name, 'email' => str($name)->slug() . '@' . $entreprise->slug . '.test', 'password' => Hash::make('password'),
            'entreprise_id' => $entreprise->id, 'role_id' => Role::firstOrCreate(['name' => 'employee'], ['label' => 'Employé'])->id, 'is_active' => true,
        ]) : null;

        return Employee::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'user_id' => $user?->id, 'full_name' => $name, 'position' => 'Commercial',
            'department' => 'Commercial', 'hire_date' => '2025-01-01', 'status' => $status, 'email' => str($name)->slug() . '@rh.test',
        ]);
    }

    /** Facture de ventes issue d'un devis du commercial (devis, commande, livraison). */
    private function salesInvoice(Entreprise $entreprise, ?Employee $seller, float $ht, string $issuedAt): CommercialInvoice
    {
        $lines = [['item_name' => 'Prestation', 'quantity' => 1, 'unit_price' => $ht]];
        $common = ['entreprise_id' => $entreprise->id, 'created_by_user_id' => $seller?->user_id, 'client_name' => 'Client test', 'lines' => $lines];
        $quote = CommercialQuote::withoutGlobalScope('entreprise')->create($common + ['quote_date' => $issuedAt, 'total_ht' => $ht, 'total_ttc' => $ht, 'status' => 'accepted']);
        $order = CommercialOrder::withoutGlobalScope('entreprise')->create($common + ['quote_id' => $quote->id, 'total_ttc' => $ht, 'status' => 'delivered']);
        $delivery = CommercialDelivery::withoutGlobalScope('entreprise')->create($common + ['order_id' => $order->id, 'delivery_type' => 'complete', 'status' => 'delivered']);

        return CommercialInvoice::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'created_by_user_id' => $seller?->user_id, 'delivery_id' => $delivery->id, 'client_name' => 'Client test',
            'amount' => $ht, 'total_ht' => $ht, 'tax_amount' => 0, 'paid_amount' => 0, 'status' => 'unpaid', 'issued_at' => $issuedAt,
        ]);
    }

    private function customInvoice(Entreprise $entreprise, ?Employee $seller, float $ht, ?string $issuedAt): CustomInvoice
    {
        $invoice = CustomInvoice::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'created_by_user_id' => $seller?->user_id,
            'reference' => app(DocumentNumberService::class)->next($entreprise->id, 'custom_invoice'),
            'client_name' => 'Client', 'items' => [], 'total_ht' => $ht, 'tax_amount' => 0, 'tax_rate' => 0,
            'tax_regime' => 'exempt', 'total_ttc' => $ht, 'status' => $issuedAt ? 'issued' : 'draft',
        ]);
        $invoice->forceFill(['issued_at' => $issuedAt])->save();

        return $invoice;
    }

    private function posSale(Entreprise $entreprise, float $ht, string $date, string $status = 'completed'): void
    {
        PosSale::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'client_name' => 'Comptoir', 'lines' => [], 'total' => $ht, 'total_ht' => $ht,
            'tax_amount' => 0, 'paid_amount' => $ht, 'change_amount' => 0, 'payment_method' => 'cash', 'status' => $status,
        ])->forceFill(['created_at' => $date])->save();
    }

    private function objective(Entreprise $entreprise, float $amount, int $year = 2026): CommercialObjective
    {
        return CommercialObjective::withoutGlobalScope('entreprise')->create(['entreprise_id' => $entreprise->id, 'amount' => $amount, 'objective_date' => "{$year}-01-01"]);
    }

    private function assign(CommercialObjective $objective, Employee $employee, float $amount, string $from = '2026-01-01', string $to = '2026-12-31'): CommercialObjectiveAssignment
    {
        return CommercialObjectiveAssignment::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $objective->entreprise_id, 'objective_id' => $objective->id, 'employee_id' => $employee->id,
            'amount' => $amount, 'starts_at' => $from, 'ends_at' => $to,
        ]);
    }

    public function test_le_realise_suit_les_ventes_de_l_exercice_et_de_chaque_commercial(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $awa = $this->seller($alpha, 'Awa Koné');
        $koffi = $this->seller($alpha, 'Koffi Yao');
        $ali = $this->seller($alpha, 'Ali Traoré', withAccount: false);

        $this->salesInvoice($alpha, $awa, 300000, '2026-03-10');
        $cancelled = $this->salesInvoice($alpha, $awa, 100000, '2026-04-05');
        $this->salesInvoice($alpha, $awa, 400000, '2025-12-20');      // exercice précédent
        $this->customInvoice($alpha, $koffi, 150000, '2026-05-02');   // avant la période de Koffi
        $this->customInvoice($alpha, $koffi, 999000, null);           // brouillon : jamais compté
        $this->posSale($alpha, 50000, '2026-06-15');
        $this->posSale($alpha, 70000, '2026-06-16', 'cancelled');

        $this->actingAs($admin)
            ->post(route('admin.commercial.invoices.cancel', $cancelled), ['cancel_invoice_id' => $cancelled->id, 'reason' => 'Commande annulée par le client'])
            ->assertSessionHasNoErrors();

        $objective = $this->objective($alpha, 1000000);
        $this->assign($objective, $awa, 600000);
        $this->assign($objective, $koffi, 300000, '2026-06-01');
        $this->assign($objective, $ali, 100000);

        $progress = app(SalesRevenueService::class)->objectiveProgress($objective->fresh());
        // 300 000 + 100 000 (facture annulée) − 100 000 (son avoir) + 150 000 + 50 000.
        $this->assertSame(500000.0, $progress['realized']);
        $this->assertSame(400000.0, $progress['sources']['invoice']['amount']);
        $this->assertSame(-100000.0, $progress['sources']['credit_note']['amount']);
        $this->assertSame(round(1000000 * 182 / 365, 2), $progress['expected']);
        $this->assertSame('on_track', $progress['state']);
        // Hors objectifs individuels : le comptoir et la facture de Koffi émise avant sa période.
        $this->assertSame(200000.0, $progress['unassigned']);

        [$awaLine, $koffiLine, $aliLine] = $progress['lines'];
        $this->assertSame(300000.0, $awaLine['realized']);
        $this->assertSame('on_track', $awaLine['state']);  // 300 000 ≥ 600 000 × 182/365 = 299 178
        $this->assertSame(0.0, $koffiLine['realized']);
        $this->assertSame('late', $koffiLine['state']);
        $this->assertFalse($aliLine['measurable']);
        $this->assertSame('unmeasured', $aliLine['state']);

        $this->actingAs($admin)
            ->get(route('admin.commercial.module', 'objectifs'))
            ->assertOk()
            ->assertSee('Objectifs commerciaux')
            ->assertSeeInOrder(['Objectif 2026', money(1000000), 'Réalisé', money(500000), '50 % de l’objectif', 'Reste à réaliser', money(500000), 'Réparti', money(1000000)])
            ->assertSee('Dans le rythme')
            // Triés par début de période : Ali (1er janvier) avant Koffi (1er juin).
            ->assertSeeInOrder(['Awa Koné', money(600000), money(300000), 'Dans le rythme', 'Ali Traoré', 'sans compte utilisateur', 'Non mesurable', 'Koffi Yao', money(300000), money(0), 'En retard'])
            ->assertSee(money(-100000));
    }

    public function test_un_seul_objectif_par_exercice_et_des_refus_lisibles(): void
    {
        $alpha = $this->makeCompany('alpha');
        $this->actingAs($this->makeAdmin($alpha));
        $awa = $this->seller($alpha, 'Awa Koné');
        $koffi = $this->seller($alpha, 'Koffi Yao');

        $this->post(route('admin.commercial.objectives.store'), ['_objective_form' => 1, 'year' => 2026, 'amount' => 1000000])
            ->assertSessionHasNoErrors();
        $objective = CommercialObjective::firstOrFail();
        $this->assertSame('2026-01-01', $objective->objective_date->toDateString());
        $this->post(route('admin.commercial.objectives.store'), ['year' => 2026, 'amount' => 500000])
            ->assertSessionHasErrors(['year' => 'Un objectif existe déjà pour l’exercice 2026 : modifiez-le plutôt que d’en créer un second.']);

        // Répartition au-delà de l'objectif : refus lisible, rien n'est enregistré.
        $row = fn (Employee $employee, float $amount, string $from = '2026-01-01', string $to = '2026-12-31') => ['employee_id' => $employee->id, 'amount' => $amount, 'starts_at' => $from, 'ends_at' => $to];
        $this->post(route('admin.commercial.objectives.assignments.store', $objective), ['assignments' => [$row($awa, 700000), $row($koffi, 400000)]])
            ->assertSessionHasErrors('assignments');
        $this->assertSame(0, $objective->assignments()->count());
        $this->post(route('admin.commercial.objectives.assignments.store', $objective), ['assignments' => [$row($awa, 700000, '2025-12-01')]])
            ->assertSessionHasErrors(['assignments.0.starts_at' => 'La période doit rester dans l’exercice 2026.']);

        $this->post(route('admin.commercial.objectives.assignments.store', $objective), ['assignments' => [$row($awa, 700000)]])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.commercial.module', ['objectifs', 'objectif' => $objective->id]));
        $this->post(route('admin.commercial.objectives.assignments.store', $objective), ['assignments' => [$row($awa, 100000)]])
            ->assertSessionHasErrors(['assignments.0.employee_id' => 'Awa Koné a déjà un objectif sur cet exercice : modifiez son attribution.']);

        // L'objectif ne descend pas sous le réparti, ni ne change d'exercice une fois réparti.
        $this->put(route('admin.commercial.objectives.update', $objective), ['year' => 2026, 'amount' => 600000])
            ->assertSessionHasErrors('amount');
        $this->put(route('admin.commercial.objectives.update', $objective), ['year' => 2027, 'amount' => 1000000])
            ->assertSessionHasErrors('year');
        $this->assertSame(1000000.0, (float) $objective->fresh()->amount);

        // Modifier une attribution vers un commercial déjà attribué est refusé.
        $this->post(route('admin.commercial.objectives.assignments.store', $objective), ['assignments' => [$row($koffi, 200000)]])->assertSessionHasNoErrors();
        $koffiAssignment = $objective->assignments()->where('employee_id', $koffi->id)->firstOrFail();
        $this->put(route('admin.commercial.objectives.assignments.update', $koffiAssignment), $row($awa, 200000))
            ->assertSessionHasErrors('employee_id');
        $this->put(route('admin.commercial.objectives.assignments.update', $koffiAssignment), $row($koffi, 400000))
            ->assertSessionHasErrors('amount');
        $this->assertSame(200000.0, (float) $koffiAssignment->fresh()->amount);
    }

    public function test_l_attribution_d_un_employe_parti_se_modifie_sans_changer_de_commercial(): void
    {
        $alpha = $this->makeCompany('alpha');
        $this->actingAs($this->makeAdmin($alpha));
        $awa = $this->seller($alpha, 'Awa Koné');
        $this->seller($alpha, 'Bintou Diallo');
        $objective = $this->objective($alpha, 1000000);
        $assignment = $this->assign($objective, $awa, 300000);
        $awa->update(['status' => 'inactive']);

        // L'ancien écran ne proposait que les actifs : la fenêtre sélectionnait
        // alors un autre employé, et « Enregistrer » lui transférait l'objectif.
        $this->get(route('admin.commercial.module', 'objectifs'))
            ->assertOk()
            ->assertSee('a quitté l’entreprise')
            ->assertSee('<option value="' . $awa->id . '" data-assigned="1" data-active="0"', false);

        $this->put(route('admin.commercial.objectives.assignments.update', $assignment), [
            'employee_id' => $awa->id, 'amount' => 250000, 'starts_at' => '2026-01-01', 'ends_at' => '2026-06-30',
        ])->assertSessionHasNoErrors();
        $assignment->refresh();
        $this->assertSame($awa->id, $assignment->employee_id);
        $this->assertSame(250000.0, (float) $assignment->amount);
    }

    public function test_les_objectifs_d_une_autre_entreprise_restent_inaccessibles(): void
    {
        $beta = $this->makeCompany('beta');
        $betaObjective = $this->objective($beta, 8000000);

        $this->actingAs($this->makeAdmin($this->makeCompany('alpha')))
            ->get(route('admin.commercial.module', ['objectifs', 'objectif' => $betaObjective->id]))
            ->assertOk()
            ->assertSee('Aucun objectif commercial')
            ->assertDontSee(money(8000000));

        $this->put(route('admin.commercial.objectives.update', $betaObjective), ['year' => 2026, 'amount' => 1])
            ->assertNotFound();
        $this->assertSame(8000000.0, (float) $betaObjective->fresh()->amount);
    }

    public function test_une_facture_annulee_n_est_deduite_qu_une_fois_de_la_tva(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $invoice = $this->salesInvoice($alpha, null, 100000, '2026-03-10');
        $invoice->update(['amount' => 118000, 'tax_amount' => 18000, 'tax_rate' => 18, 'tax_regime' => 'standard']);

        $this->actingAs($admin)
            ->post(route('admin.commercial.invoices.cancel', $invoice), ['cancel_invoice_id' => $invoice->id, 'reason' => 'Commande annulée par le client'])
            ->assertSessionHasNoErrors();

        // La facture reste déclarée à son émission, l'avoir la neutralise :
        // l'ancien calcul l'écartait ET retranchait l'avoir, soit −18 000.
        $vat = app(VatDeclarationService::class)->declare($alpha->id, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));
        $this->assertSame(18000.0, $vat['collected']['tax']);
        $this->assertSame(18000.0, $vat['credit_notes']['tax']);
        $this->assertSame(0.0, $vat['collected_net']);
    }
}
