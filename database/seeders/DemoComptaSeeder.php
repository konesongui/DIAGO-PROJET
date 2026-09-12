<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\CommercialClient;
use App\Models\CommercialDelivery;
use App\Models\CommercialInvoice;
use App\Models\CommercialObjective;
use App\Models\CommercialOrder;
use App\Models\CommercialProforma;
use App\Models\CommercialQuote;
use App\Models\CommercialService;
use App\Models\CommercialSupplier;
use App\Models\CustomInvoice;
use App\Models\Employee;
use App\Models\Entreprise;
use App\Models\ExpenseCategory;
use App\Models\FixedAsset;
use App\Models\PosSale;
use App\Models\Role;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Services\AccountingPoster;
use App\Services\AnnualReportService;
use App\Services\DocumentNumberService;
use App\Services\InvoiceIntegrityService;
use App\Services\PaymentRecorder;
use App\Services\TaxService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Données de démonstration de la Comptabilité, pour tester l'interface à la main.
 *
 *   php artisan db:seed --class=DemoComptaSeeder              crée (ou recrée) les données
 *   DEMO_PURGE=1 php artisan db:seed --class=DemoComptaSeeder retire les données sans en recréer
 *
 * Tout ce que le seeder crée est noté dans storage/app/demo-compta.json. Une
 * nouvelle exécution retire d'abord ces enregistrements, et eux seuls : les
 * données saisies à la main ne sont jamais touchées. Les paramètres de
 * l'entreprise ne sont complétés que s'ils sont vides.
 *
 * Entreprise ciblée : DEMO_ENTREPRISE (slug), sinon « diagoma-demo », sinon la
 * première entreprise active. Les écritures passent par les services de
 * l'application (TVA, journal, règlements) pour rester cohérentes.
 */
class DemoComptaSeeder extends Seeder
{
    private const MANIFEST = 'demo-compta.json';

    /** @var array<int, array{0: string, 1: int}> table et identifiant de chaque enregistrement créé */
    private array $created = [];

    /** @var array<int, string> fichiers PDF déposés sur le disque public */
    private array $files = [];

    /** @var array<string, string> paramètres de l'entreprise complétés par le seeder */
    private array $settingsAdded = [];

    private Entreprise $entreprise;

    private User $user;

    private CashAccount $cashBox;

    private CashAccount $mobileMoney;

    private BankAccount $bank;

    /** @var array<string, ExpenseCategory> */
    private array $categories = [];

    public function run(): void
    {
        $this->purge();

        if (env('DEMO_PURGE')) {
            $this->command?->info('Données de démonstration retirées.');

            return;
        }

        $this->entreprise = Entreprise::where('slug', env('DEMO_ENTREPRISE', 'diagoma-demo'))->first()
            ?? Entreprise::where('is_active', true)->orderBy('id')->firstOrFail();
        $this->user = User::where('entreprise_id', $this->entreprise->id)->orderBy('id')->firstOrFail();

        // Les services et les portées globales lisent l'entreprise de l'utilisateur connecté.
        Auth::setUser($this->user);

        Event::listen('eloquent.created: *', function (string $event, array $models) {
            foreach ($models as $model) {
                if ($model instanceof Model) {
                    $this->created[] = [$model->getTable(), $model->getKey()];
                }
            }
        });

        DB::transaction(function () {
            $this->completeCompanySettings();
            $this->treasury();
            $this->services();
            $this->expenses();
            $this->sales();
            $this->purchases();
            $this->suppliers();
            $this->customInvoices();
            $this->proformas();
            $this->posSales();
            $this->objectives();
            $this->stockEntries();
            $this->stockExits();
            // Inventaire de la semaine : 2 ramettes manquantes, une clé USB en trop.
            app(\App\Services\StockService::class)->applyInventory($this->entreprise->id, Carbon::today()->subDay()->toDateString(), 'Inventaire de fin de semaine',
                ['ramette papier a4 80 g|papeterie · pap-a4|ramette' => 18, 'clé usb 32 go|informatique|pièce' => 26, 'classeur à levier|papeterie|pièce' => 40], $this->user->id);
            $this->fixedAssets();
            // Bilan de l'exercice clos, alimenté par les ventes et achats de l'année précédente.
            app(AnnualReportService::class)->reportFor($this->entreprise->id, $this->closedYear());
            $this->cashBox->save();
            $this->mobileMoney->save();
            $this->bank->save();
        });

        Storage::put(self::MANIFEST, json_encode([
            'entreprise_id' => $this->entreprise->id,
            'created_at' => now()->toDateTimeString(),
            'records' => $this->created,
            'files' => $this->files,
            'settings' => $this->settingsAdded,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->command?->info(sprintf(
            'Données de démonstration créées pour « %s » (%d enregistrements). Connexion : %s',
            $this->entreprise->name, count($this->created), $this->user->email
        ));
    }

    /** Retire ce qu'une exécution précédente a créé, dans l'ordre inverse de création. */
    private function purge(): void
    {
        if (! Storage::exists(self::MANIFEST)) {
            return;
        }

        $manifest = json_decode(Storage::get(self::MANIFEST), true) ?: [];

        DB::transaction(function () use ($manifest) {
            foreach (array_reverse($manifest['records'] ?? []) as [$table, $id]) {
                DB::table($table)->where('id', $id)->delete();
            }

            $entreprise = Entreprise::find($manifest['entreprise_id'] ?? null);
            if ($entreprise && ! empty($manifest['settings'])) {
                $settings = $entreprise->settings ?? [];
                foreach ($manifest['settings'] as $key => $value) {
                    // Un paramètre modifié depuis par l'utilisateur est conservé.
                    if (($settings[$key] ?? null) === $value) {
                        unset($settings[$key]);
                    }
                }
                $entreprise->update(['settings' => $settings]);
            }
        });

        Storage::disk('public')->delete($manifest['files'] ?? []);
        Storage::delete(self::MANIFEST);
    }

    /** Mentions légales et coordonnées, pour des documents imprimés complets. */
    private function completeCompanySettings(): void
    {
        $defaults = [
            'address' => 'Plateau, avenue Chardy, immeuble Alpha 2000, Abidjan',
            'po_box' => '01 BP 1234 Abidjan 01',
            'phone' => '+225 27 20 30 40 50',
            'email' => 'contact@diagoma-demo.ci',
            'legal_form' => 'SARL',
            'nccm_rccm' => 'CI-ABJ-2019-B-12345',
            'taxpayer_account' => '1912345 A',
            'tax_regime' => 'Réel normal',
            'tax_center' => 'Plateau 1',
            'bank_name' => 'NSIA Banque',
            'bank_account' => 'CI092 01001 0039281',
        ];

        $settings = $this->entreprise->settings ?? [];
        foreach ($defaults as $key => $value) {
            if (blank($settings[$key] ?? null)) {
                $settings[$key] = $value;
                $this->settingsAdded[$key] = $value;
            }
        }
        $this->entreprise->update(['settings' => $settings]);
    }

    private function treasury(): void
    {
        $this->cashBox = CashAccount::create([
            'entreprise_id' => $this->entreprise->id, 'name' => 'Caisse principale', 'account_type' => 'caisse',
            'description' => 'Caisse de démonstration', 'currency' => 'XOF', 'initial_balance' => 400000, 'balance' => 400000, 'is_active' => true,
        ]);
        $this->mobileMoney = CashAccount::create([
            'entreprise_id' => $this->entreprise->id, 'name' => 'Orange Money', 'account_type' => 'mobile_money',
            'description' => 'Compte mobile money de démonstration', 'currency' => 'XOF', 'initial_balance' => 150000, 'balance' => 150000, 'is_active' => true,
        ]);
        $this->bank = BankAccount::create([
            'entreprise_id' => $this->entreprise->id, 'name' => 'Compte courant NSIA', 'bank_name' => 'NSIA Banque', 'short_name' => 'NSIA',
            'account_number' => 'CI092 01001 0039281', 'account_type' => 'compte_courant', 'current_balance' => 6500000, 'status' => 'credit',
            'description' => 'Compte de démonstration',
        ]);

        foreach ([
            'loyer' => ['Loyer & charges', 'Loyer des bureaux et charges locatives'],
            'fournitures' => ['Fournitures de bureau', 'Papeterie, consommables informatiques'],
            'transport' => ['Transport & missions', 'Déplacements, carburant, billets'],
            'energie' => ['Électricité & eau', 'Factures CIE et SODECI'],
            'telecom' => ['Télécommunications', 'Internet, téléphonie, crédit mobile'],
        ] as $key => [$name, $description]) {
            $this->categories[$key] = ExpenseCategory::create([
                'entreprise_id' => $this->entreprise->id, 'name' => $name, 'description' => $description, 'is_active' => true,
            ]);
        }
    }

    /** Catalogue : les libellés sont ceux des factures de démonstration ; deux forfaits pour la facture personnalisée. */
    private function services(): void
    {
        foreach ([
            [null, 'Audit des comptes annuels', 'Revue des comptes, contrôle interne et rapport de recommandations.', 4500000, 'forfait', true, false],
            [null, 'Accompagnement fiscal', 'Déclarations mensuelles et assistance lors des contrôles.', 1250000, 'mois', true, false],
            [null, 'Formation des équipes comptables', null, 350000, 'jour', true, false],
            [null, 'Paramétrage du logiciel de gestion', 'Installation, reprise des données et formation des utilisateurs.', 250000, 'jour', true, false],
            ['redaction_statuts', 'Rédaction des statuts', 'Statuts de SARL ou de SAS, prêts à l’enregistrement.', 150000, 'forfait', true, true],
            ['domiciliation', 'Domiciliation annuelle', 'Adresse du siège et réception du courrier pendant un an.', 300000, 'an', true, true],
            [null, 'Tenue de caisse (ancienne offre)', null, 75000, 'mois', false, false],
        ] as [$code, $name, $description, $price, $unit, $active, $global]) {
            CommercialService::create([
                'entreprise_id' => $this->entreprise->id, 'code' => $code, 'name' => $name, 'description' => $description,
                'price' => $price, 'unit' => $unit, 'is_active' => $active, 'is_global' => $global,
            ]);
        }
    }

    /** Six mois de dépenses courantes et d'encaissements comptoir. */
    private function expenses(): void
    {
        for ($month = 5; $month >= 0; $month--) {
            $start = Carbon::today()->startOfMonth()->subMonths($month);
            $day = fn (int $d) => $start->copy()->addDays(min($d, $start->daysInMonth) - 1)->min(Carbon::today());

            $this->bankMove('debit', 350000, 'Loyer ' . $start->copy()->locale('fr')->translatedFormat('F Y'), $day(5), 'Virement bailleur SCI Plateau');
            $this->cashMove($this->cashBox, 'exit', 40000 + $month * 3500, 'Facture CIE', $day(12), 'energie');
            $this->cashMove($this->cashBox, 'exit', 25000 + ($month % 3) * 18000, 'Fournitures de bureau', $day(9), 'fournitures');
            $this->cashMove($this->cashBox, 'exit', 30000 + ($month % 2) * 15000, 'Carburant et déplacements', $day(18), 'transport');
            $this->cashMove($this->mobileMoney, 'exit', 20000, 'Forfait internet et téléphonie', $day(3), 'telecom', 'mobile_money');
            $this->cashMove($this->cashBox, 'entry', 180000 + ($month % 3) * 45000, 'Ventes comptoir', $day(20));
            $this->cashMove($this->mobileMoney, 'entry', 60000 + $month * 8000, 'Encaissements Orange Money', $day(22), null, 'mobile_money');
        }

        $this->bankMove('credit', 1500000, 'Apport en compte courant d’associé', Carbon::today()->subMonths(4)->startOfMonth()->addDays(1), 'Apport de démonstration');
    }

    /** Clients, devis, livraisons complètes et factures, avec leurs règlements. */
    private function sales(): void
    {
        $clients = collect([
            ['Société Générale CI', 'M. Kouamé', '+225 27 20 20 10 10', 'achats@sgci.demo.ci', 'Abidjan', '8801234 B', '5-7 avenue Joseph Anoma, Plateau'],
            ['Orange Côte d’Ivoire', 'Mme Diabaté', '+225 27 20 31 00 00', 'fournisseurs@orange.demo.ci', 'Abidjan', '9012345 C', 'Immeuble Orange, Marcory'],
            ['Sotra BTP & Construction', 'M. Yao', '+225 07 08 09 10 11', 'contact@sotrabtp.demo.ci', 'Yamoussoukro', null, 'Zone industrielle, Yamoussoukro'],
            ['Cabinet Kouassi & Associés', 'Me Kouassi', '+225 05 06 07 08 09', 'secretariat@kouassi.demo.ci', 'Abidjan', '1122334 D', 'Cocody Riviera 3'],
        ])->map(fn ($c) => CommercialClient::create([
            'entreprise_id' => $this->entreprise->id, 'name' => $c[0], 'responsible_name' => $c[1], 'phone' => $c[2],
            'email' => $c[3], 'city' => $c[4], 'tax_id' => $c[5], 'address' => $c[6],
        ]));

        $audit = ['item_type' => 'service', 'item_name' => 'Audit des comptes annuels', 'category_name' => 'Prestations de conseil', 'unit' => 'forfait'];
        $conseil = ['item_type' => 'service', 'item_name' => 'Accompagnement fiscal', 'category_name' => 'Prestations de conseil', 'unit' => 'mois'];
        $formation = ['item_type' => 'service', 'item_name' => 'Formation des équipes comptables', 'category_name' => 'Formation', 'unit' => 'jour'];
        $logiciel = ['item_type' => 'service', 'item_name' => 'Paramétrage du logiciel de gestion', 'category_name' => 'Intégration', 'unit' => 'jour'];

        // Payée par virement, certifiée FNE.
        $invoice = $this->sale($clients[0], [$audit + ['quantity' => 1, 'unit_price' => 4500000], $conseil + ['quantity' => 2, 'unit_price' => 1250000]], 250000, 118, 'BC-2026-118');
        $this->pay($invoice, (float) $invoice->amount, 'bank', Carbon::today()->subDays(95));
        $invoice->update(['fne_status' => 'certified', 'fne_reference' => 'DEMO-9606-0000019', 'fne_token' => 'https://verification.exemple.ci/fne/DEMO-9606-0000019', 'fne_certified_at' => Carbon::today()->subDays(117)->setTime(10, 30), 'fne_balance_sticker' => 185]);

        // Partiellement payée en espèces.
        $invoice = $this->sale($clients[1], [$formation + ['quantity' => 4, 'unit_price' => 350000], $logiciel + ['quantity' => 3, 'unit_price' => 250000]], 0, 64, 'OCI-4471');
        $this->pay($invoice, 800000, 'cash', Carbon::today()->subDays(40));

        // Impayées.
        $this->sale($clients[2], [$audit + ['quantity' => 1, 'unit_price' => 2800000]], 0, 38, null);
        $this->sale($clients[3], [$conseil + ['quantity' => 3, 'unit_price' => 900000]], 150000, 9, 'CK-09');

        // Payée en deux règlements (espèces puis virement).
        $invoice = $this->sale($clients[3], [$formation + ['quantity' => 2, 'unit_price' => 350000]], 0, 150, 'CK-02');
        $this->pay($invoice, 300000, 'cash', Carbon::today()->subDays(140));
        $this->pay($invoice, (float) $invoice->fresh()->amount - 300000, 'bank', Carbon::today()->subDays(120));

        // Annulée par un avoir total.
        $invoice = $this->sale($clients[1], [$logiciel + ['quantity' => 2, 'unit_price' => 250000]], 0, 25, 'OCI-4502');
        $integrity = app(InvoiceIntegrityService::class);
        $integrity->credit($invoice, $integrity->creditableAmount($invoice), 'Commande annulée par le client (démonstration)', $this->user->id);
        $invoice->update(['status' => 'cancelled']);

        // Devis validé dont la livraison reste à faire : la valider crée la facture.
        $this->orderToDeliver($clients[0], [$conseil + ['quantity' => 3, 'unit_price' => 1250000], $formation + ['quantity' => 2, 'unit_price' => 350000]], 4, 'BC-2026-131');

        // Devis encore en attente : l'un valable, l'autre expiré (date limite dépassée).
        $this->pendingQuote($clients[2], [$audit + ['quantity' => 1, 'unit_price' => 3200000], $formation + ['quantity' => 2, 'unit_price' => 350000]], 5, 30);
        $this->pendingQuote($clients[3], [$logiciel + ['quantity' => 4, 'unit_price' => 250000]], 45, 15);

        // Exercice précédent, clos : une créance reste ouverte à la clôture.
        $ago = fn (int $month, int $day) => (int) Carbon::create($this->closedYear(), $month, $day)->diffInDays(Carbon::today());
        $invoice = $this->sale($clients[0], [$audit + ['quantity' => 1, 'unit_price' => 6000000], $formation + ['quantity' => 3, 'unit_price' => 350000]], 0, $ago(10, 14), 'BC-' . $this->closedYear() . '-087');
        $this->pay($invoice, (float) $invoice->amount, 'bank', Carbon::create($this->closedYear(), 11, 20));
        $invoice = $this->sale($clients[1], [$logiciel + ['quantity' => 5, 'unit_price' => 250000], $conseil + ['quantity' => 2, 'unit_price' => 1250000]], 0, $ago(11, 18), 'OCI-3980');
        $this->pay($invoice, 2000000, 'bank', Carbon::create($this->closedYear(), 12, 15));
        $this->sale($clients[3], [$conseil + ['quantity' => 1, 'unit_price' => 900000]], 0, $ago(12, 8), 'CK-01');
    }

    /** Dernier exercice clos, celui du bilan annuel. */
    private function closedYear(): int
    {
        return app(AnnualReportService::class)->latestClosedYear();
    }

    /** Devis validé, commande et livraison en attente : la livraison n'est pas encore faite. */
    private function orderToDeliver(CommercialClient $client, array $lines, int $daysAgo, string $orderCode): void
    {
        $quote = $this->pendingQuote($client, $lines, $daysAgo, 30);
        $quote->update(['status' => 'validated', 'customer_order_code' => $orderCode]);
        $order = CommercialOrder::create([
            'entreprise_id' => $quote->entreprise_id, 'created_by_user_id' => $this->user->id, 'quote_id' => $quote->id, 'client_name' => $client->name,
            'reference' => app(DocumentNumberService::class)->next($quote->entreprise_id, 'order', $quote->quote_date),
            'customer_order_code' => $orderCode, 'lines' => $lines, 'total_ttc' => $quote->total_ttc, 'total_ht' => $quote->net_ht,
            'tax_amount' => $quote->tax_amount, 'tax_rate' => $quote->tax_rate, 'tax_regime' => $quote->tax_regime,
            'tax_rate_id' => $quote->tax_rate_id, 'currency' => 'XOF', 'status' => 'pending_delivery',
        ]);
        CommercialDelivery::create([
            'entreprise_id' => $quote->entreprise_id, 'created_by_user_id' => $this->user->id, 'order_id' => $order->id,
            'client_name' => $client->name, 'lines' => $lines, 'delivery_type' => 'partial', 'status' => 'pending_validation',
        ]);
    }

    /** Devis en attente de validation, sans commande. */
    private function pendingQuote(CommercialClient $client, array $lines, int $daysAgo, int $validityDays): CommercialQuote
    {
        $id = $this->entreprise->id;
        $date = Carbon::today()->subDays($daysAgo);
        $taxService = app(TaxService::class);
        $totalHt = collect($lines)->sum(fn ($line) => $line['quantity'] * $line['unit_price']);
        $breakdown = $taxService->breakdown($totalHt, $taxService->resolveRate($id, null, $date->toDateString()), 'XOF');

        return CommercialQuote::create([
            'entreprise_id' => $id, 'created_by_user_id' => $this->user->id,
            'reference' => app(DocumentNumberService::class)->next($id, 'quote', $date),
            'client_id' => $client->id, 'client_name' => $client->name, 'quote_date' => $date->toDateString(),
            'due_date' => $date->copy()->addDays($validityDays)->toDateString(),
            'payment_terms' => '30 jours net à réception de facture', 'payment_method' => 'Virement',
            'delivery_location' => $client->address, 'subject' => 'Proposition ' . mb_strtolower($lines[0]['item_name']),
            'total_ht' => $totalHt, 'total_discount' => 0, 'net_ht' => $totalHt,
            'tax_rate' => $breakdown['rate_value'], 'tax_amount' => $breakdown['tax_amount'], 'total_ttc' => $breakdown['total_ttc'],
            'tax_rate_id' => $breakdown['rate_id'], 'tax_regime' => $breakdown['regime'], 'currency' => 'XOF',
            'lines' => $lines, 'status' => 'pending_validation',
        ]);
    }

    /** Chaîne complète devis → commande → livraison → facture, comme dans l'application. */
    private function sale(CommercialClient $client, array $lines, float $discount, int $daysAgo, ?string $orderCode): CommercialInvoice
    {
        $id = $this->entreprise->id;
        $date = Carbon::today()->subDays($daysAgo);
        $taxService = app(TaxService::class);
        $totalHt = collect($lines)->sum(fn ($line) => $line['quantity'] * $line['unit_price']);
        $netHt = $totalHt - $discount;
        $breakdown = $taxService->breakdown($netHt, $taxService->resolveRate($id, null, $date->toDateString()), 'XOF');

        $quote = CommercialQuote::create([
            'entreprise_id' => $id, 'created_by_user_id' => $this->user->id,
            'reference' => app(DocumentNumberService::class)->next($id, 'quote', $date->copy()->subDays(10)),
            'client_id' => $client->id, 'client_name' => $client->name, 'quote_date' => $date->copy()->subDays(10)->toDateString(),
            'payment_terms' => '30 jours net à réception de facture', 'payment_method' => 'Virement',
            'delivery_location' => $client->address, 'subject' => 'Mission ' . mb_strtolower($lines[0]['item_name']),
            'customer_order_code' => $orderCode, 'total_ht' => $totalHt, 'total_discount' => $discount, 'net_ht' => $netHt,
            'tax_rate' => $breakdown['rate_value'], 'tax_amount' => $breakdown['tax_amount'], 'total_ttc' => $breakdown['total_ttc'],
            'tax_rate_id' => $breakdown['rate_id'], 'tax_regime' => $breakdown['regime'], 'currency' => 'XOF',
            'lines' => $lines, 'status' => 'validated',
        ]);
        $order = CommercialOrder::create([
            'entreprise_id' => $id, 'created_by_user_id' => $this->user->id, 'quote_id' => $quote->id, 'client_name' => $client->name,
            'reference' => app(DocumentNumberService::class)->next($id, 'order', $date),
            'customer_order_code' => $orderCode, 'lines' => $lines, 'total_ttc' => $breakdown['total_ttc'], 'total_ht' => $netHt,
            'tax_amount' => $breakdown['tax_amount'], 'tax_rate' => $breakdown['rate_value'], 'tax_regime' => $breakdown['regime'],
            'tax_rate_id' => $breakdown['rate_id'], 'currency' => 'XOF', 'status' => 'delivered',
        ]);
        $delivery = CommercialDelivery::create([
            'entreprise_id' => $id, 'created_by_user_id' => $this->user->id, 'order_id' => $order->id, 'client_name' => $client->name,
            'lines' => $lines, 'delivery_type' => 'complete', 'status' => 'validated',
        ]);
        $invoice = CommercialInvoice::create([
            'entreprise_id' => $id, 'created_by_user_id' => $this->user->id, 'delivery_id' => $delivery->id, 'client_name' => $client->name,
            'amount' => $breakdown['total_ttc'], 'paid_amount' => 0, 'status' => 'unpaid',
            'total_ht' => $netHt, 'tax_amount' => $breakdown['tax_amount'], 'tax_rate' => $breakdown['rate_value'],
            'tax_regime' => $breakdown['regime'], 'tax_rate_id' => $breakdown['rate_id'], 'currency' => 'XOF', 'issued_at' => $date->copy()->setTime(9, 0),
        ]);
        app(AccountingPoster::class)->postSaleInvoice($invoice, $this->user->id);

        return $invoice;
    }

    /** Règlement client, avec les mêmes effets que la fenêtre de paiement. */
    private function pay(CommercialInvoice $invoice, float $amount, string $method, Carbon $date): void
    {
        if ($method === 'cash') {
            $this->cashMove($this->cashBox, 'entry', $amount, 'Paiement facture #' . $invoice->id, $date, null, 'cash', 'FACTURE-' . $invoice->id, 'Encaissement client');
        } else {
            $this->bankMove('credit', $amount, 'Paiement facture #' . $invoice->id, $date, 'Encaissement client');
        }

        app(PaymentRecorder::class)->record($invoice, $amount, $method, $date, $this->user->id);
        app(AccountingPoster::class)->postCustomerPayment($invoice, $amount, $method, $date, $this->user->id);

        $paid = (float) $invoice->paid_amount + $amount;
        $invoice->update([
            'paid_amount' => $paid, 'payment_method' => $method,
            'cash_account_id' => $method === 'cash' ? $this->cashBox->id : null,
            'bank_account_id' => $method === 'bank' ? $this->bank->id : null,
            'status' => $paid >= (float) $invoice->amount ? 'paid' : 'partially_paid',
        ]);
    }

    /** Factures fournisseurs, avec un PDF justificatif généré pour chacune. */
    private function purchases(): void
    {
        $invoices = [
            ['MacStore Abidjan', '1504321 C', 'FV-2026-0452', 42, [['MacBook Air 13" M3 16 Go', 'MBA13-M3', 1, 1150000], ['Écran Dell 27" UltraSharp', 'U2724D', 2, 185000], ['Clavier et souris sans fil', 'MK295', 2, 20000]], 'standard', 'certified'],
            ['Librairie de France', '0712456 A', 'LDF-88213', 20, [['Ramettes papier A4 (carton de 5)', 'PAP-A4', 6, 17500], ['Classeurs à levier', 'CL-75', 20, 1800], ['Cartouches d’encre HP 305', 'HP305', 4, 12500]], 'standard', null],
            ['Air Côte d’Ivoire', null, null, 6, [], null, null],
            // Exercice précédent.
            ['Compagnie Ivoirienne d’Électricité', '0000123 E', 'CIE-' . $this->closedYear() . '-11873', (int) Carbon::create($this->closedYear(), 11, 15)->diffInDays(Carbon::today()), [['Consommation électrique d’octobre', 'BT-OCT', 1, 185000]], 'standard', null],
            ['Ivoire Bureau', '0654321 B', 'IB-' . $this->closedYear() . '-0931', (int) Carbon::create($this->closedYear(), 12, 5)->diffInDays(Carbon::today()), [['Fauteuils de bureau ergonomiques', 'FB-ERGO', 6, 95000], ['Table de réunion 8 places', 'TR-8P', 1, 420000]], 'standard', null],
        ];

        foreach ($invoices as $i => [$supplier, $ncc, $number, $daysAgo, $items, $regime, $fne]) {
            $date = Carbon::today()->subDays($daysAgo);
            $items = collect($items)->map(fn ($item) => [
                'description' => $item[0], 'reference' => $item[1], 'quantity' => $item[2], 'unit_price' => $item[3], 'total' => $item[2] * $item[3],
            ]);
            $ht = $items->sum('total') ?: 360000;
            $tax = round($ht * 0.18);
            $filename = 'demo-' . \Illuminate\Support\Str::slug($supplier) . '.pdf';
            $path = 'supplier-invoices/' . $filename;
            Storage::disk('public')->put($path, Pdf::loadHTML($this->supplierPdfHtml($supplier, $number, $date, $items->all(), $ht, $tax))->output());
            $this->files[] = $path;

            $invoice = SupplierInvoice::create([
                'entreprise_id' => $this->entreprise->id,
                'invoice_number' => $number, 'supplier_name' => $supplier, 'supplier_tax_id' => $ncc,
                // Le billet d'avion simule une extraction incomplète : numéro, date et HT manquent.
                'invoice_date' => $number ? $date->toDateString() : null, 'due_date' => $number ? $date->copy()->addDays(30)->toDateString() : null,
                'subtotal' => $number ? $ht : null, 'total_ht' => $number ? $ht : null, 'tax_amount' => $number ? $tax : null,
                'total_amount' => $ht + $tax, 'tax_regime' => $regime ?? 'unknown', 'currency' => 'XOF',
                'status' => $number ? 'imported' : 'needs_review', 'file_path' => $path, 'original_filename' => $filename,
                'extracted_data' => ['items' => $items->all()],
                'fne_status' => $fne ?? 'not_certified',
                'fne_reference' => $fne ? 'DEMO-9606-0000044' : null,
                // Adresse de vérification fictive : la vraie est renvoyée par la FNE à la certification.
                'fne_token' => $fne ? 'https://verification.exemple.ci/fne/DEMO-9606-0000044' : null,
                'fne_certified_at' => $fne ? $date->copy()->addDays(2)->setTime(15, 0) : null,
                'fne_balance_sticker' => $fne ? 184 : null,
            ]);

            // Régime confirmé : la facture est passée au journal, comme depuis sa fiche.
            if ($regime) {
                app(AccountingPoster::class)->postSupplierInvoice($invoice, $this->user->id);
            }
        }
    }

    /**
     * Factures personnalisées : brouillon avec caractéristiques d'impression, émise
     * impayée corrigée par un avoir, partiellement payée en deux fois et échue,
     * payée en espèces.
     */
    private function customInvoices(): void
    {
        $id = $this->entreprise->id;
        $taxService = app(TaxService::class);
        $book = ['book_type' => 'livre', 'book_format' => 'a5_14x21', 'page_count' => 240, 'paper_type' => 'bouffant_creme',
            'printing_type' => 'noir_blanc', 'cover_type' => 'couche_300g', 'lamination' => 'mat', 'binding_type' => 'dos_carre_colle'];
        foreach ([
            ['client' => 'Éditions du Plateau', 'phone' => '+225 07 11 22 33 44', 'email' => 'contact@editionsduplateau.ci', 'subject' => 'Mise en page et impression du roman « Lagune »',
                'items' => [['Mise en page intérieure', 'autre', 'page', 240, 1500], ['Impression du roman « Lagune »', 'livre', 'exemplaire', 300, 2400, $book]],
                'forfaits' => [], 'daysAgo' => 2, 'dueDays' => 30, 'issued' => false, 'payments' => [], 'credit' => null,
                'location' => 'Cocody, Riviera 3', 'delivery' => 'Livraison sous 15 jours ouvrés après validation du bon à tirer'],
            ['client' => 'Kouadio Jean-Marc', 'phone' => '+225 05 44 33 22 11', 'email' => 'jm.kouadio@gmail.com', 'subject' => 'Correction d’un manuscrit',
                'items' => [['Correction orthographique et grammaticale', 'autre', 'page', 180, 1200]],
                'forfaits' => [], 'daysAgo' => 12, 'dueDays' => 30, 'issued' => true, 'payments' => [],
                'credit' => [21240, 'Quinze pages facturées en trop : le manuscrit final compte 165 pages.']],
            ['client' => 'Librairie Carrefour', 'phone' => '+225 27 22 00 11 22', 'email' => null, 'subject' => 'Aménagement du rayon jeunesse',
                'items' => [['Conseil en merchandising', 'autre', 'jour', 3, 150000]],
                'forfaits' => ['domiciliation'], 'daysAgo' => 26, 'dueDays' => 20, 'issued' => true, 'payments' => [[300000, 'bank', 3], [150000, 'cash', 10]], 'credit' => null],
            ['client' => 'Mme Traoré Aminata', 'phone' => '+225 01 23 45 67 89', 'email' => null, 'subject' => 'Accompagnement à l’autoédition',
                'items' => [['Accompagnement éditorial', 'autre', 'séance', 4, 37500]],
                'forfaits' => ['redaction_statuts'], 'daysAgo' => 35, 'dueDays' => 30, 'issued' => true, 'payments' => [[null, 'cash', 3]], 'credit' => null],
        ] as $row) {
            $date = Carbon::today()->subDays($row['daysAgo']);
            $items = collect($row['items'])->map(fn ($item) => ['item_name' => $item[0], 'item_category' => $item[1], 'unit' => $item[2], 'quantity' => $item[3], 'price' => $item[4]] + ($item[5] ?? []))->all();
            $services = collect($row['forfaits'])->map(function ($code) {
                $service = CommercialService::where('entreprise_id', $this->entreprise->id)->where('code', $code)->first();

                return ['key' => $code, 'label' => $service?->name ?? $code, 'price' => (float) ($service?->price ?? 0)];
            })->all();
            $ht = collect($items)->sum(fn ($item) => $item['quantity'] * $item['price']) + collect($services)->sum('price');
            $breakdown = $taxService->breakdown($ht, $taxService->resolveRate($id, null, $date->toDateString()), 'XOF');

            $invoice = CustomInvoice::create([
                'entreprise_id' => $id, 'created_by_user_id' => $this->user->id,
                'reference' => app(DocumentNumberService::class)->next($id, 'custom_invoice', $date),
                'client_name' => $row['client'], 'client_phone' => $row['phone'], 'client_email' => $row['email'],
                'quote_date' => $date->toDateString(), 'valid_until' => $date->copy()->addDays($row['dueDays'])->toDateString(),
                'payment_terms' => 'Paiement à réception', 'delivery_terms' => $row['delivery'] ?? null, 'delivery_location' => $row['location'] ?? null,
                'payment_method' => ($row['payments'][0][1] ?? 'bank') === 'bank' ? 'Virement' : 'Espèces', 'subject' => $row['subject'],
                'items' => $items, 'global_services' => $services, 'total_ht' => $ht, 'total_discount' => 0, 'subtotal_after_discount' => $ht,
                'tax_amount' => $breakdown['tax_amount'], 'tax_rate_id' => $breakdown['rate_id'], 'tax_rate' => $breakdown['rate_value'],
                'tax_regime' => $breakdown['regime'], 'total_ttc' => $breakdown['total_ttc'], 'paid_amount' => 0, 'status' => 'draft',
            ]);

            if ($row['issued']) {
                $invoice->forceFill(['issued_at' => $date->copy()->setTime(10, 0)])->save();
                app(AccountingPoster::class)->postCustomInvoice($invoice->refresh(), $this->user->id);
            }

            if ($row['credit']) {
                app(InvoiceIntegrityService::class)->credit($invoice, $row['credit'][0], $row['credit'][1], $this->user->id);
                $invoice->refresh();
            }

            foreach ($row['payments'] as [$amount, $method, $daysAfter]) {
                $amount ??= $invoice->remainingAmount();
                $paidOn = $date->copy()->addDays($daysAfter);
                if ($method === 'cash') {
                    $this->cashMove($this->cashBox, 'entry', $amount, 'Paiement facture ' . $invoice->reference, $paidOn, null, 'cash', 'CUST-INV-' . $invoice->reference . '-' . $paidOn->format('Ymd'), 'Paiement d’une facture personnalisée');
                } else {
                    $this->bankMove('credit', $amount, 'Paiement facture ' . $invoice->reference, $paidOn, 'Paiement d’une facture personnalisée');
                }
                app(PaymentRecorder::class)->record($invoice, $amount, $method, $paidOn, $this->user->id);
                app(AccountingPoster::class)->postCustomerPayment($invoice, $amount, $method, $paidOn, $this->user->id);
                $paid = (float) $invoice->paid_amount + $amount;
                $invoice->update([
                    'paid_amount' => $paid, 'paid_at' => $invoice->paid_at ?? $paidOn,
                    'cash_account_id' => $method === 'cash' ? $this->cashBox->id : null,
                    'bank_account_id' => $method === 'bank' ? $this->bank->id : null,
                    'status' => $paid >= $invoice->amountDue() ? 'paid' : 'draft',
                ]);
            }
        }
    }

    /** Proformas : brouillon, envoyée, expirée, et une sans date limite avec remise. */
    private function proformas(): void
    {
        $client = fn (string $name) => CommercialClient::where('entreprise_id', $this->entreprise->id)->where('name', $name)->firstOrFail();
        foreach ([
            [$client('Société Générale CI'), 'Audit des systèmes d’information et sécurité informatique', 1, 30, 'draft', [
                ['service', 'Prestations de conseil', 'Audit des systèmes d’information', 'forfait', 1, 6500000, 0],
                ['service', 'Formation', 'Sensibilisation des équipes à la sécurité', 'jour', 2, 450000, 0]]],
            [$client('Orange Côte d’Ivoire'), 'Accompagnement fiscal et conformité', 9, 21, 'sent', [
                ['service', 'Prestations de conseil', 'Accompagnement fiscal', 'mois', 3, 520000, 5]]],
            [$client('Sotra BTP & Construction'), 'Paramétrage du logiciel de gestion', 40, 30, 'sent', [
                ['service', 'Intégration', 'Paramétrage du logiciel de gestion', 'jour', 4, 250000, 0],
                ['product', 'Matériel', 'Licence annuelle du logiciel', 'licence', 1, 350000, 0]]],
            [$client('Cabinet Kouassi & Associés'), 'Formation des équipes comptables', 0, null, 'draft', [
                ['service', 'Formation', 'Formation des équipes comptables', 'jour', 3, 350000, 10]]],
        ] as [$customer, $subject, $daysAgo, $validity, $status, $lines]) {
            $date = Carbon::today()->subDays($daysAgo);
            $prepared = collect($lines)->map(function ($line) {
                [$type, $category, $name, $unit, $quantity, $price, $discount] = $line;
                $gross = $quantity * $price;
                $net = $gross - $gross * $discount / 100;

                return ['type' => $type, 'category' => $category, 'item_name' => $name, 'unit' => $unit, 'quantity' => $quantity, 'unit_price' => $price,
                    'discount' => $discount, 'discount_type' => 'percent', 'net_unit_price' => $net / $quantity, 'line_total' => $net, 'gross' => $gross];
            });
            $totalHt = $prepared->sum('gross');
            $netHt = $prepared->sum('line_total');
            // Même calcul que l'éditeur : taux par défaut de l'entreprise, avec son régime.
            $taxes = app(TaxService::class);
            $breakdown = $taxes->breakdown($netHt, $taxes->resolveRate($this->entreprise->id, null, $date->toDateString()), 'XOF');
            $proforma = CommercialProforma::create([
                'entreprise_id' => $this->entreprise->id, 'reference' => app(DocumentNumberService::class)->next($this->entreprise->id, 'proforma', $date),
                'client_id' => $customer->id, 'client_name' => $customer->name, 'client_phone' => $customer->phone,
                'creation_date' => $date->toDateString(), 'due_date' => $validity ? $date->copy()->addDays($validity)->toDateString() : null,
                'payment_terms' => '50 % à la commande, solde à la livraison', 'payment_method' => 'Virement', 'subject' => $subject,
                'total_ht' => $totalHt, 'total_discount' => $totalHt - $netHt, 'net_ht' => $breakdown['base_ht'], 'tax_rate' => $breakdown['rate_value'],
                'tax_rate_id' => $breakdown['rate_id'], 'tax_regime' => $breakdown['regime'], 'currency' => 'XOF',
                'tax_amount' => $breakdown['tax_amount'], 'total_ttc' => $breakdown['total_ttc'], 'status' => $status,
            ]);
            $proforma->lines()->createMany($prepared->map(fn ($line) => collect($line)->except('gross')->all())->all());
        }
    }

    /** Ventes au comptoir du mois : espèces, banque, et une vente annulée (contrepassée au journal). */
    private function posSales(): void
    {
        $taxes = app(TaxService::class);
        $kouassi = CommercialClient::where('entreprise_id', $this->entreprise->id)->where('name', 'Cabinet Kouassi & Associés')->first();
        $daysInMonth = (int) Carbon::today()->format('j') - 1;
        foreach ([
            [0, null, [['Déclaration fiscale mensuelle', 1, 59000]], 'cash', 60000, false],
            [0, $kouassi, [['Domiciliation annuelle', 1, 354000]], 'bank', 354000, false],
            [1, null, [['Rédaction des statuts', 1, 177000], ['Photocopies certifiées', 10, 500]], 'cash', 200000, false],
            [3, null, [['Conseil juridique (1 h)', 2, 35400]], 'cash', 70800, true],
            [5, null, [['Formation au logiciel de gestion', 1, 118000]], 'bank', 118000, false],
        ] as [$daysAgo, $customer, $lines, $method, $paid, $cancel]) {
            $date = Carbon::today()->subDays(min($daysAgo, $daysInMonth))->setTime(10 + $daysAgo, 20);
            $lines = collect($lines)->map(fn ($l) => ['item_name' => $l[0], 'quantity' => $l[1], 'unit_price' => $l[2]])->all();
            $total = collect($lines)->sum(fn ($l) => $l['quantity'] * $l['unit_price']);
            $breakdown = $taxes->breakdownFromTtc($total, $taxes->resolveRate($this->entreprise->id), 'XOF');
            $reference = app(DocumentNumberService::class)->next($this->entreprise->id, 'pos_sale', $date);
            if ($method === 'cash') {
                $this->cashMove($this->cashBox, 'entry', $total, 'Vente caisse ' . $reference, $date, null, 'cash', $reference, 'Vente au point de vente');
            } else {
                $this->bankMove('credit', $total, 'Vente point de vente ' . $reference, $date, 'Vente au point de vente');
            }
            $sale = new PosSale([
                'entreprise_id' => $this->entreprise->id, 'reference' => $reference, 'client_id' => $customer?->id, 'client_name' => $customer?->name,
                'lines' => $lines, 'total' => $total, 'paid_amount' => $paid, 'change_amount' => $paid - $total, 'payment_method' => $method,
                'cash_account_id' => $method === 'cash' ? $this->cashBox->id : null, 'bank_account_id' => $method === 'bank' ? $this->bank->id : null,
                'status' => 'completed', 'total_ht' => $breakdown['base_ht'], 'tax_amount' => $breakdown['tax_amount'], 'tax_rate' => $breakdown['rate_value'],
                'tax_regime' => $breakdown['regime'], 'tax_rate_id' => $breakdown['rate_id'], 'currency' => 'XOF',
            ]);
            $sale->created_at = $date;
            $sale->save();
            app(AccountingPoster::class)->postPosSale($sale, $this->user->id);

            if ($cancel) {
                $this->cashMove($this->cashBox, 'exit', $total, 'Annulation vente ' . $reference, $date->copy()->addHour(), null, 'cash', $reference . '-ANN', 'Annulation de la vente au point de vente');
                $sale->update(['status' => 'cancelled']);
                app(AccountingPoster::class)->syncPosSale($sale, 'Annulation vente ' . $reference, $this->user->id);
            }
        }
    }

    /** Réceptions de stock, chacune rattachée au fournisseur qui l'a livrée. */
    /**
     * Objectifs commerciaux : trois commerciaux, dont un sans compte utilisateur.
     * Une partie des factures de l'année leur est attribuée ; la plus ancienne
     * reste à l'administrateur et apparaît « hors objectifs individuels ».
     */
    private function objectives(): void
    {
        $id = $this->entreprise->id;
        $year = Carbon::today()->year;
        $role = Role::firstOrCreate(['name' => 'sales'], ['label' => 'Commercial']);
        $sellers = [];
        foreach ([['Awa Koné', 'Commerciale senior', true], ['Koffi Yao', 'Chargé d’affaires', true], ['Ali Traoré', 'Commercial junior', false]] as $index => [$name, $position, $account]) {
            $slug = Str::slug($name);
            // Compte désactivé : il relie les ventes au commercial, sans ouvrir de connexion.
            $user = $account ? User::create([
                'name' => $name, 'email' => $slug . '.demo-' . $id . '@diago.local', 'password' => Hash::make(Str::random(40)),
                'entreprise_id' => $id, 'role_id' => $role->id, 'is_active' => false,
            ]) : null;
            $sellers[] = Employee::create([
                'entreprise_id' => $id, 'user_id' => $user?->id, 'full_name' => $name, 'position' => $position,
                'department' => 'Commercial', 'hire_date' => Carbon::create($year - 2, 3 + $index, 1)->toDateString(),
                'status' => 'active', 'email' => $slug . '.rh-' . $id . '@diago.local', 'contract_type' => 'CDI',
            ]);
        }
        [$awa, $koffi, $ali] = $sellers;

        $created = fn (string $table) => collect($this->created)->where(0, $table)->pluck(1);
        CommercialInvoice::whereIn('id', $created('commercial_invoices'))->orderBy('issued_at')->get()
            ->filter(fn ($invoice) => $invoice->issued_at->year === $year)->values()
            ->each(function ($invoice, $index) use ($awa, $koffi) {
                if ($index > 0) {
                    $invoice->update(['created_by_user_id' => ($index % 2 ? $awa : $koffi)->user_id]);
                }
            });
        CustomInvoice::whereIn('id', $created('custom_invoices'))->whereNotNull('issued_at')->update(['created_by_user_id' => $koffi->user_id]);

        $objective = CommercialObjective::create(['entreprise_id' => $id, 'amount' => 30000000, 'objective_date' => "{$year}-01-01"]);
        foreach ([[$awa, 12000000, "{$year}-01-01"], [$koffi, 8000000, "{$year}-03-01"], [$ali, 3000000, "{$year}-01-01"]] as [$employee, $amount, $from]) {
            $objective->assignments()->create(['entreprise_id' => $id, 'employee_id' => $employee->id, 'amount' => $amount, 'starts_at' => $from, 'ends_at' => "{$year}-12-31"]);
        }
        CommercialObjective::create(['entreprise_id' => $id, 'amount' => 12000000, 'objective_date' => $this->closedYear() . '-01-01']);
    }

    private function stockEntries(): void
    {
        foreach ([
            [45, 'Ivoire Bureau', 'BL-IB-2207', [['Ramette papier A4 80 g', 'Papeterie · PAP-A4', 'ramette', 50, 2800, 700], ['Cartouche d’encre noire', 'Consommables · HP 305', 'pièce', 12, 9500, 3000],
                ['Classeur à levier', 'Papeterie', 'pièce', 40, 1200, 600], ['Chemise cartonnée', 'Papeterie', 'paquet de 50', 10, 4500, 1500]]],
            [18, 'MacStore Abidjan', 'MS-58841', [['Clé USB 32 Go', 'Informatique', 'pièce', 25, 3500, 2000], ['Ramette papier A4 80 g', 'Papeterie · PAP-A4', 'ramette', 30, 2900, 600]]],
            [3, 'Librairie de France', null, [['Registre du personnel', 'Registres légaux', 'pièce', 5, 15000, 5000], ['Registre des procès-verbaux', 'Registres légaux', 'pièce', 5, 12000, 4000]]],
        ] as [$daysAgo, $supplierName, $reference, $lines]) {
            $supplier = CommercialSupplier::where('entreprise_id', $this->entreprise->id)->where('name', $supplierName)->first();
            $prepared = collect($lines)->map(fn ($l) => ['designation' => $l[0], 'article' => $l[1], 'unit' => $l[2], 'quantity' => $l[3], 'purchase_price' => $l[4], 'profit_per_unit' => $l[5],
                'total_purchase' => $l[3] * $l[4], 'total_profit' => $l[3] * $l[5]]);
            $entry = StockEntry::create([
                'entreprise_id' => $this->entreprise->id, 'entry_date' => Carbon::today()->subDays($daysAgo)->toDateString(),
                'supplier_id' => $supplier?->id, 'supplier_name' => $supplier?->name, 'supplier_reference' => $reference,
                'total_purchase' => $prepared->sum('total_purchase'), 'total_profit' => $prepared->sum('total_profit'),
            ]);
            $entry->lines()->createMany($prepared->all());
        }
    }

    /** Sorties manuelles : 60 ramettes puisent dans deux réceptions (la plus ancienne d'abord), et une casse. */
    private function stockExits(): void
    {
        $stock = app(\App\Services\StockService::class);
        foreach ([
            [10, 'internal_use', 'Fournitures du service comptable', [['Ramette papier A4 80 g', 60]]],
            [2, 'damage', 'Cartouches abîmées à la réception', [['Cartouche d’encre noire', 2]]],
        ] as [$daysAgo, $reason, $note, $articles]) {
            $lots = $stock->lots($this->entreprise->id);
            $taken = [];
            $lines = [];
            foreach ($articles as [$name, $quantity]) {
                array_push($lines, ...$stock->allocate($lots, fn ($line) => $line->designation === $name, $quantity, $name, 'lines', $taken));
            }
            $exit = StockExit::create([
                'entreprise_id' => $this->entreprise->id, 'reason' => $reason, 'note' => $note,
                'exit_date' => Carbon::today()->subDays($daysAgo)->toDateString(), 'total_value' => collect($lines)->sum('total_value'),
            ]);
            $exit->lines()->createMany($lines);
        }
    }

    /** Carnet des fournisseurs : les NCC correspondent à ceux des factures importées. */
    private function suppliers(): void
    {
        foreach ([
            ['MacStore Abidjan', 'M. Bamba', '1504321 C', '+225 27 22 44 55 66', 'ventes@macstore.demo.ci', 'Boulevard Latrille, Cocody'],
            ['Librairie de France', 'Mme Aka', '0712456 A', '+225 27 20 21 22 23', 'pro@librairie.demo.ci', 'Avenue Chardy, Plateau'],
            ['Compagnie Ivoirienne d’Électricité', 'Service entreprises', '0000123 E', '179', 'entreprises@cie.demo.ci', 'Avenue Christiani, Treichville'],
            ['Ivoire Bureau', 'M. N’Guessan', '0654321 B', '+225 07 11 22 33 44', 'contact@ivoirebureau.demo.ci', 'Zone 4, Marcory'],
            ['SDMO Côte d’Ivoire', 'M. Koné', '0445566 D', '+225 27 21 35 00 00', 'service@sdmo.demo.ci', 'Zone industrielle de Vridi'],
        ] as [$name, $responsible, $ncc, $phone, $email, $address]) {
            CommercialSupplier::create([
                'entreprise_id' => $this->entreprise->id, 'name' => $name, 'responsible_name' => $responsible,
                'tax_id' => $ncc, 'phone' => $phone, 'email' => $email, 'address' => $address,
            ]);
        }
    }

    private function supplierPdfHtml(string $supplier, ?string $number, Carbon $date, array $items, float $ht, float $tax): string
    {
        $rows = collect($items)->map(fn ($item) => sprintf(
            '<tr><td>%s</td><td>%s</td><td style="text-align:right">%s</td><td style="text-align:right">%s</td></tr>',
            e($item['description']), $item['quantity'], number_format($item['unit_price'], 0, ',', ' '), number_format($item['total'], 0, ',', ' ')
        ))->implode('') ?: '<tr><td colspan="4">Billet Abidjan - San-Pédro, aller-retour</td></tr>';

        return '<html><body style="font-family:DejaVu Sans,sans-serif;font-size:12px">'
            . '<h2>' . e($supplier) . '</h2><p>Facture ' . e($number ?? '') . ' du ' . $date->format('d/m/Y') . '</p>'
            . '<p><em>Document de démonstration généré par DIAGO.</em></p>'
            . '<table width="100%" border="1" cellspacing="0" cellpadding="6"><tr><th>Désignation</th><th>Qté</th><th>PU</th><th>Total</th></tr>' . $rows . '</table>'
            . '<p style="text-align:right">Total HT : ' . number_format($ht, 0, ',', ' ') . ' FCFA<br>TVA 18 % : ' . number_format($tax, 0, ',', ' ')
            . ' FCFA<br><strong>Total TTC : ' . number_format($ht + $tax, 0, ',', ' ') . ' FCFA</strong></p></body></html>';
    }

    private function fixedAssets(): void
    {
        foreach ([
            ['Toyota Hilux double cabine', 'Véhicule', 'VEH-001', 'Parc Abidjan', 'CFAO Motors', 39, 18500000, 1500000, 5, 'active'],
            ['Serveur Dell PowerEdge', 'Informatique', 'INF-014', 'Salle serveur', 'MacStore Abidjan', 15, 4200000, 0, 3, 'active'],
            ['Mobilier open space', 'Mobilier', 'MOB-003', 'Plateau, 2e étage', 'Ivoire Bureau', 51, 2750000, 0, 10, 'active'],
            ['Groupe électrogène 20 kVA', 'Matériel', 'MAT-003', 'Local technique', 'SDMO CI', 75, 6800000, 300000, 5, 'retired'],
            ['Bureaux Cocody', 'Immobilier', 'IMM-001', 'Cocody Riviera', null, 27, 95000000, 20000000, 25, 'active'],
            ['Photocopieur Ricoh', 'Autre', 'DIV-007', 'Accueil', 'Ricoh CI', 63, 1450000, 0, 4, 'sold'],
            ['Climatiseurs split (x4)', 'Matériel', 'MAT-011', 'Plateau, 2e étage', 'Frigo Ivoire', 1, 1920000, 0, 5, 'active'],
        ] as [$name, $category, $reference, $location, $supplier, $monthsAgo, $value, $residual, $life, $status]) {
            FixedAsset::create([
                'entreprise_id' => $this->entreprise->id, 'name' => $name, 'asset_category' => $category, 'reference' => $reference,
                'location' => $location, 'supplier' => $supplier, 'acquisition_date' => Carbon::today()->subMonths($monthsAgo)->toDateString(),
                'acquisition_value' => $value, 'residual_value' => $residual, 'useful_life_years' => $life, 'status' => $status,
                'description' => 'Immobilisation de démonstration',
            ]);
        }
    }

    private function cashMove(CashAccount $account, string $type, float $amount, string $label, Carbon $date, ?string $category = null, string $mode = 'cash', ?string $reference = null, ?string $description = null): void
    {
        CashMovement::create([
            'entreprise_id' => $this->entreprise->id, 'cash_account_id' => $account->id,
            'expense_category_id' => $category ? $this->categories[$category]->id : null,
            'movement_type' => $type, 'label' => $label, 'amount' => $amount, 'currency' => 'XOF', 'payment_mode' => $mode,
            'is_transfer' => false, 'reference' => $reference, 'description' => $description, 'movement_date' => $date->toDateString(),
        ]);
        $account->balance = (float) $account->balance + ($type === 'entry' ? $amount : -$amount);
    }

    private function bankMove(string $type, float $amount, string $label, Carbon $date, ?string $description = null): void
    {
        BankTransaction::create([
            'entreprise_id' => $this->entreprise->id, 'bank_account_id' => $this->bank->id, 'transaction_type' => $type,
            'is_transfer' => false, 'label' => $label, 'amount' => $amount, 'description' => $description, 'transaction_date' => $date->toDateString(),
        ]);
        $this->bank->current_balance = (float) $this->bank->current_balance + ($type === 'credit' ? $amount : -$amount);
        $this->bank->status = $type;
        $this->bank->last_transaction_label = $label;
        $this->bank->last_transaction_amount = $amount;
        $this->bank->last_transaction_direction = $type === 'credit' ? 'up' : 'down';
    }
}
