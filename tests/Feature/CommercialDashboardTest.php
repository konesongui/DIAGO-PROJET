<?php

namespace Tests\Feature;

use App\Models\CommercialClient;
use App\Models\CommercialDelivery;
use App\Models\CommercialInvoice;
use App\Models\CommercialOrder;
use App\Models\CommercialQuote;
use App\Models\CustomInvoice;
use App\Models\Entreprise;
use App\Models\PosSale;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Services\PaymentRecorder;
use App\Services\SalesRevenueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Tableau commercial : chiffre d'affaires HT, encaissements datés, créances
 * ouvertes et classements, sur la période choisie.
 */
class CommercialDashboardTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-11 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seller(Entreprise $entreprise, string $name): User
    {
        return User::create([
            'name' => $name, 'email' => str($name)->slug() . '@' . $entreprise->slug . '.test', 'password' => Hash::make('password'),
            'entreprise_id' => $entreprise->id, 'role_id' => Role::firstOrCreate(['name' => 'sales'], ['label' => 'Commercial'])->id, 'is_active' => true,
        ]);
    }

    /** Facture de ventes issue de la chaîne devis → commande → livraison. */
    private function salesInvoice(Entreprise $entreprise, ?User $seller, array $lines, string $issuedAt, float $tax = 0): CommercialInvoice
    {
        $ht = collect($lines)->sum(fn ($line) => $line['quantity'] * $line['unit_price']);
        $common = ['entreprise_id' => $entreprise->id, 'created_by_user_id' => $seller?->id, 'client_name' => 'Société Kouassi', 'lines' => $lines];
        $quote = CommercialQuote::withoutGlobalScope('entreprise')->create($common + ['quote_date' => $issuedAt, 'total_ht' => $ht, 'total_ttc' => $ht + $tax, 'status' => 'validated']);
        $order = CommercialOrder::withoutGlobalScope('entreprise')->create($common + ['quote_id' => $quote->id, 'total_ttc' => $ht + $tax, 'status' => 'delivered']);
        $delivery = CommercialDelivery::withoutGlobalScope('entreprise')->create($common + ['order_id' => $order->id, 'delivery_type' => 'complete', 'status' => 'validated']);

        return CommercialInvoice::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'created_by_user_id' => $seller?->id, 'delivery_id' => $delivery->id, 'client_name' => 'Société Kouassi',
            'amount' => $ht + $tax, 'total_ht' => $ht, 'tax_amount' => $tax, 'paid_amount' => 0, 'status' => 'unpaid', 'issued_at' => $issuedAt,
        ]);
    }

    private function customInvoice(Entreprise $entreprise, ?User $seller, float $ht, ?string $issuedAt, string $client = 'Éditions du Plateau'): CustomInvoice
    {
        $invoice = CustomInvoice::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'created_by_user_id' => $seller?->id,
            'reference' => app(DocumentNumberService::class)->next($entreprise->id, 'custom_invoice'),
            'client_name' => $client, 'items' => [['item_name' => 'Mise en page', 'quantity' => 1, 'price' => $ht]],
            'total_ht' => $ht, 'tax_amount' => 0, 'tax_rate' => 0, 'tax_regime' => 'exempt', 'total_ttc' => $ht,
            'valid_until' => $issuedAt ? Carbon::parse($issuedAt)->addDays(30)->toDateString() : null,
            'status' => $issuedAt ? 'issued' : 'draft',
        ]);
        $invoice->forceFill(['issued_at' => $issuedAt])->save();

        return $invoice;
    }

    private function posSale(Entreprise $entreprise, float $ht, string $date, string $status = 'completed'): PosSale
    {
        $sale = PosSale::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'client_name' => null, 'lines' => [['item_name' => 'Photocopies', 'quantity' => 10, 'unit_price' => $ht / 10]],
            'total' => $ht, 'total_ht' => $ht, 'tax_amount' => 0, 'paid_amount' => $ht, 'change_amount' => 0,
            'payment_method' => 'cash', 'status' => $status,
        ]);
        $sale->forceFill(['created_at' => $date])->save();

        return $sale;
    }

    public function test_les_indicateurs_comptent_les_ventes_reelles_de_la_periode(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $awa = $this->seller($alpha, 'Awa Koné');

        $line = fn (string $name, float $price) => ['item_name' => $name, 'quantity' => 1, 'unit_price' => $price];
        $this->salesInvoice($alpha, $awa, [$line('Audit des comptes', 3000000)], '2026-03-10');
        $cancelled = $this->salesInvoice($alpha, $awa, [$line('Formation', 500000)], '2026-04-05');
        $this->salesInvoice($alpha, $awa, [$line('Audit des comptes', 1000000)], '2025-11-10');  // hors période
        $this->customInvoice($alpha, null, 800000, '2026-05-02');
        $this->customInvoice($alpha, null, 9000000, null);                                        // brouillon
        $this->posSale($alpha, 200000, '2026-06-15');
        $this->posSale($alpha, 700000, '2026-06-16', 'cancelled');                                // annulée

        $this->actingAs($admin)
            ->post(route('admin.commercial.invoices.cancel', $cancelled), ['cancel_invoice_id' => $cancelled->id, 'reason' => 'Commande annulée par le client'])
            ->assertSessionHasNoErrors();

        $sales = app(SalesRevenueService::class);
        $rows = $sales->rows($alpha->id, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31')->endOfDay());
        // 3 000 000 + 500 000 (facture annulée) − 500 000 (son avoir) + 800 000 + 200 000.
        $this->assertSame(4000000.0, round((float) $rows->sum('amount'), 2));

        $this->actingAs($admin)->get(route('admin.commercial.tableau'))
            ->assertOk()
            ->assertSeeInOrder(['Chiffre d’affaires HT', money(4000000), 'Encaissements', money(200000)])
            ->assertSee('Créances clients')
            // Les créances sont celles de ce jour, toutes périodes : 3 000 000 + 800 000
            // + la facture de 2025 restée impayée. La facture annulée en est exclue.
            ->assertSee(money(4800000))
            ->assertSee('à ce jour · 3 facture(s)')
            ->assertDontSee(money(9000000));
    }

    public function test_les_encaissements_suivent_les_reglements_dates(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $invoice = $this->salesInvoice($alpha, null, [['item_name' => 'Audit', 'quantity' => 1, 'unit_price' => 1000000]], '2026-02-10');
        app(PaymentRecorder::class)->record($invoice, 400000, 'bank', Carbon::parse('2026-03-15'), $admin->id);
        app(PaymentRecorder::class)->record($invoice, 100000, 'cash', Carbon::parse('2026-08-20'), $admin->id);
        $invoice->update(['paid_amount' => 500000, 'status' => 'partially_paid']);
        $this->posSale($alpha, 250000, '2026-03-20');

        // Mars seulement : le règlement d'août n'y est pas.
        $this->actingAs($admin)
            ->get(route('admin.commercial.tableau', ['date_debut' => '2026-03-01', 'date_fin' => '2026-03-31']))
            ->assertOk()
            ->assertSee(money(650000))    // 400 000 encaissés + 250 000 au comptoir
            ->assertSee(money(500000))    // reste à encaisser sur la facture
            ->assertDontSee(money(100000));

        $collections = app(SalesRevenueService::class)->collections($alpha->id, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31')->endOfDay());
        $this->assertSame(750000.0, round((float) $collections->sum('amount'), 2));
        $this->assertSame(['Banque', 'Espèces', 'Espèces'], $collections->sortBy('date')->pluck('method')->values()->all());
    }

    public function test_les_classements_repartissent_le_ht_sur_les_lignes_vendues(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $awa = $this->seller($alpha, 'Awa Koné');
        $koffi = $this->seller($alpha, 'Koffi Yao');

        $this->salesInvoice($alpha, $awa, [
            ['item_name' => 'Audit des comptes', 'quantity' => 1, 'unit_price' => 2000000],
            ['item_name' => 'Formation', 'quantity' => 2, 'unit_price' => 500000],
        ], '2026-04-10');
        $this->customInvoice($alpha, $koffi, 1000000, '2026-05-02', 'Éditions du Plateau');
        $this->posSale($alpha, 300000, '2026-06-15');

        $service = app(SalesRevenueService::class);
        $items = $service->soldItems($alpha->id, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31')->endOfDay());
        // Formation (2 × 500 000) et Mise en page sont à égalité derrière l'audit.
        $this->assertSame(['Audit des comptes', 'Formation', 'Mise en page', 'Photocopies'], $items->pluck('name')->all());
        $this->assertSame([2000000.0, 1000000.0, 1000000.0, 300000.0], $items->pluck('amount')->all());

        $response = $this->actingAs($admin)->get(route('admin.commercial.tableau'))
            ->assertOk()
            ->assertSeeInOrder(['Performance par commercial', 'Awa Koné', money(3000000), 'Koffi Yao', money(1000000), 'Ventes au comptoir', money(300000)])
            ->assertSeeInOrder(['Meilleurs clients', 'Société Kouassi', 'Éditions du Plateau'])
            ->assertSeeInOrder(['Prestations et produits les plus vendus', 'Audit des comptes']);

        // Les ventes au comptoir anonymes ne deviennent pas un « meilleur client ».
        $this->assertSame(['Société Kouassi', 'Éditions du Plateau'], $response->viewData('clients')->pluck('name')->all());
    }

    public function test_une_periode_invalide_est_signalee_sans_page_d_erreur(): void
    {
        $alpha = $this->makeCompany('alpha');

        $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.commercial.tableau', ['date_debut' => '2026-08-01', 'date_fin' => '2026-01-01']))
            ->assertOk()
            ->assertSee('La période choisie est invalide')
            ->assertSee('Aucune vente sur la période');
    }

    public function test_le_devis_expire_n_est_plus_en_attente(): void
    {
        $alpha = $this->makeCompany('alpha');
        $common = ['entreprise_id' => $alpha->id, 'client_name' => 'Société Kouassi', 'lines' => [], 'status' => 'pending_validation'];
        CommercialQuote::withoutGlobalScope('entreprise')->create($common + ['quote_date' => '2026-08-01', 'due_date' => '2026-12-31', 'total_ht' => 1500000, 'net_ht' => 1500000, 'total_ttc' => 1500000]);
        CommercialQuote::withoutGlobalScope('entreprise')->create($common + ['quote_date' => '2026-02-01', 'due_date' => '2026-03-01', 'total_ht' => 4400000, 'net_ht' => 4400000, 'total_ttc' => 4400000]);
        CommercialQuote::withoutGlobalScope('entreprise')->create(['entreprise_id' => $alpha->id, 'client_name' => 'Société Kouassi', 'lines' => [],
            'status' => 'validated', 'quote_date' => '2026-05-01', 'total_ht' => 900000, 'net_ht' => 900000, 'total_ttc' => 900000]);

        $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.commercial.tableau'))
            ->assertOk()
            ->assertSee('Devis en attente')
            ->assertSee(money(1500000))
            ->assertDontSee(money(5900000))     // le devis expiré n'est pas compté
            ->assertSee('1 devis en cours · 33 % de devis validés sur la période');
    }

    public function test_le_tableau_d_une_autre_entreprise_reste_invisible(): void
    {
        $beta = $this->makeCompany('beta');
        $this->salesInvoice($beta, null, [['item_name' => 'Mission confidentielle beta', 'quantity' => 1, 'unit_price' => 7777000]], '2026-04-10');

        $this->actingAs($this->makeAdmin($this->makeCompany('alpha')))
            ->get(route('admin.commercial.tableau'))
            ->assertOk()
            ->assertDontSee('Mission confidentielle beta')
            ->assertDontSee(money(7777000))
            ->assertSee('Aucune vente sur la période');
    }
}
