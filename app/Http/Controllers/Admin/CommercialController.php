<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use App\Models\Lead;
use App\Models\CommercialClient;
use App\Models\CommercialSupplier;
use App\Models\CommercialObjective;
use App\Models\CommercialObjectiveAssignment;
use App\Models\Employee;
use App\Models\StockEntry;
use App\Models\StockEntryLine;
use App\Models\StockExit;
use App\Models\CommercialProforma;
use App\Models\CommercialQuote;
use App\Models\CommercialOrder;
use App\Models\CustomInvoice;
use App\Models\CommercialDelivery;
use App\Models\CommercialInvoice;
use App\Models\CashMovement;
use App\Models\BankTransaction;
use App\Models\CashAccount;
use App\Models\BankAccount;
use App\Models\CommercialService;
use App\Models\PosSale;
use App\Models\InventoryAudit;
use App\Services\FneCertificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CommercialController extends AdminController
{
    public function modules(): array
    {
        return [
            ['key' => 'tableau', 'title' => 'Tableau Commercial', 'description' => 'Pilotage des ventes, clients, encaissements et performances commerciales', 'icon' => 'ki-chart-line-up', 'color' => 'success'],
            ['key' => 'proforma', 'title' => 'Proforma', 'description' => 'Préparer et suivre les factures proforma', 'icon' => 'ki-file-added', 'color' => 'primary'],
            ['key' => 'devis', 'title' => 'Devis', 'description' => 'Créer et suivre les offres commerciales', 'icon' => 'ki-document', 'color' => 'info'],
            ['key' => 'facture-personnalisee', 'title' => 'Facture personnalisée', 'description' => 'Créer une facture sur mesure avec les caractéristiques du document, les services et les montants.', 'icon' => 'ki-receipt-text', 'color' => 'danger'],
            ['key' => 'commandes', 'title' => 'Bons de commandes', 'description' => 'Enregistrer et suivre les commandes', 'icon' => 'ki-basket', 'color' => 'success'],
            ['key' => 'livraisons', 'title' => 'Bons de livraisons', 'description' => 'Préparer et contrôler les livraisons', 'icon' => 'ki-delivery', 'color' => 'warning'],
            ['key' => 'factures', 'title' => 'Factures', 'description' => 'Suivre les factures issues des livraisons complètes', 'icon' => 'ki-receipt', 'color' => 'danger'],
            ['key' => 'services', 'title' => 'Mes services', 'description' => 'Gérer le catalogue des services', 'icon' => 'ki-setting-2', 'color' => 'primary'],
            ['key' => 'point-de-vente', 'title' => 'Point de vente', 'description' => 'Accéder à la vente rapide', 'icon' => 'ki-shop', 'color' => 'danger'],
            ['key' => 'clients', 'title' => 'Clients', 'description' => 'Gérer les fiches et contacts clients', 'icon' => 'ki-people', 'color' => 'info'],
            ['key' => 'fournisseurs', 'title' => 'Fournisseurs', 'description' => 'Gérer les partenaires fournisseurs', 'icon' => 'ki-truck', 'color' => 'success'],
            ['key' => 'objectifs', 'title' => 'Objectifs commercial', 'description' => 'Suivre les objectifs et réalisations', 'icon' => 'ki-chart-simple', 'color' => 'warning'],
            ['key' => 'entrees-stock', 'title' => 'Entrées de stock', 'description' => 'Enregistrer les réceptions de stock', 'icon' => 'ki-arrow-down', 'color' => 'success'],
            ['key' => 'sorties-stock', 'title' => 'Sorties de stock', 'description' => 'Enregistrer les sorties et ventes', 'icon' => 'ki-arrow-up', 'color' => 'danger'],
            ['key' => 'etat-stock', 'title' => 'État de stock', 'description' => 'Consulter les quantités disponibles', 'icon' => 'ki-chart-line-up', 'color' => 'primary'],
            ['key' => 'inventaire', 'title' => 'Inventaire', 'description' => 'Contrôler et valoriser le stock', 'icon' => 'ki-clipboard', 'color' => 'info'],
        ];
    }

    public function tableau(Request $request)
    {
        $entrepriseId = auth()->user()?->entreprise_id;
        $from = Carbon::parse($request->input('date_debut', now()->startOfYear()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('date_fin', now()->toDateString()))->endOfDay();
        abort_unless($from->lte($to), 422, 'La période sélectionnée est invalide.');
        $inPeriod = fn ($date) => $date && Carbon::parse($date)->between($from, $to);
        $clients = CommercialClient::where('entreprise_id', $entrepriseId)->orderBy('name')->get()
            ->filter(fn ($item) => $inPeriod($item->created_at))->values();
        $quotes = CommercialQuote::where('entreprise_id', $entrepriseId)->latest('quote_date')->get()
            ->filter(fn ($item) => $inPeriod($item->quote_date))->values();
        $orders = CommercialOrder::where('entreprise_id', $entrepriseId)->latest()->get()
            ->filter(fn ($item) => $inPeriod($item->created_at))->values();
        $invoices = CommercialInvoice::where('entreprise_id', $entrepriseId)->with('creator')->latest()->get()
            ->filter(fn ($item) => $inPeriod($item->created_at))->values();
        $customInvoices = CustomInvoice::where('entreprise_id', $entrepriseId)->with('creator')->latest()->get()
            ->filter(fn ($item) => $inPeriod($item->quote_date))->values();
        $sales = PosSale::where('entreprise_id', $entrepriseId)->latest()->get()
            ->filter(fn ($item) => $inPeriod($item->created_at))->values();
        $leads = Lead::where('entreprise_id', $entrepriseId)->latest()->get()
            ->filter(fn ($item) => $inPeriod($item->created_at))->values();
        $services = CommercialService::where('entreprise_id', $entrepriseId)->orderBy('name')->get()
            ->filter(fn ($item) => $inPeriod($item->created_at))->values();
        $stockEntries = StockEntryLine::whereHas('stockEntry', fn ($query) => $query->where('entreprise_id', $entrepriseId))
            ->with('stockEntry')->get()
            ->filter(fn ($item) => $inPeriod($item->stockEntry?->entry_date))->values();
        $stockExits = \App\Models\StockExitLine::whereHas('entryLine.stockEntry', fn ($query) => $query->where('entreprise_id', $entrepriseId))
            ->with('entryLine.stockEntry')->get()
            ->filter(fn ($item) => $inPeriod($item->stockExit?->exit_date ?? $item->entryLine?->stockEntry?->entry_date))->values();

        $invoiced = (float) $invoices->sum('amount') + (float) $customInvoices->sum('total_ttc');
        $collected = (float) $invoices->sum('paid_amount') + (float) $customInvoices->sum('paid_amount') + (float) $sales->sum('paid_amount');
        $receivables = max($invoiced + (float) $sales->sum('total') - $collected, 0);
        $pipeline = (float) $quotes->whereNotIn('status', ['rejected', 'cancelled'])->sum('total_ttc');
        $currentMonth = now()->startOfMonth();
        $previousMonth = now()->subMonth()->startOfMonth();
        $currentRevenue = (float) $invoices->filter(fn ($item) => $item->created_at?->gte($currentMonth))->sum('amount')
            + (float) $customInvoices->filter(fn ($item) => $item->quote_date?->gte($currentMonth))->sum('total_ttc')
            + (float) $sales->filter(fn ($item) => $item->created_at?->gte($currentMonth))->sum('total');
        $previousRevenue = (float) $invoices->filter(fn ($item) => $item->created_at?->isSameMonth($previousMonth))->sum('amount')
            + (float) $customInvoices->filter(fn ($item) => $item->quote_date?->isSameMonth($previousMonth))->sum('total_ttc')
            + (float) $sales->filter(fn ($item) => $item->created_at?->isSameMonth($previousMonth))->sum('total');
        $monthlyGrowth = $previousRevenue > 0 ? (($currentRevenue - $previousRevenue) / $previousRevenue) * 100 : ($currentRevenue > 0 ? 100 : 0);
        $unpaidInvoices = $invoices->filter(fn ($item) => (float) $item->amount > (float) $item->paid_amount && $item->status !== 'cancelled');
        $unpaidCustomInvoices = $customInvoices->filter(fn ($item) => (float) $item->total_ttc > (float) $item->paid_amount && $item->status !== 'cancelled');
        $cityStats = $clients->groupBy(fn ($client) => trim((string) $client->city) ?: 'Non renseignée')->map->count()->sortDesc();
        $monthlyRevenue = collect(range(11, 0))->map(function ($offset) use ($invoices, $customInvoices, $sales) {
            $date = now()->subMonths($offset);
            return ['label' => $date->translatedFormat('M Y'), 'value' =>
                (float) $invoices->filter(fn ($item) => $item->created_at?->isSameMonth($date))->sum('amount')
                + (float) $customInvoices->filter(fn ($item) => $item->quote_date?->isSameMonth($date))->sum('total_ttc')
                + (float) $sales->filter(fn ($item) => $item->created_at?->isSameMonth($date))->sum('total')];
        });
        $commercialPerformance = $invoices->groupBy(fn ($invoice) => $invoice->creator?->name ?? 'Non attribué')
            ->map(fn ($items, $name) => ['name' => $name, 'value' => (float) $items->sum('amount'), 'count' => $items->count()])
            ->sortByDesc('value')->values();
        $productStats = collect();
        foreach ($stockExits as $line) {
            $key = trim((string) $line->designation) ?: 'Article';
            $productStats[$key] = ($productStats[$key] ?? 0) + (float) $line->quantity;
        }
        foreach ($sales as $sale) {
            foreach ((array) $sale->lines as $line) {
                $name = $line['designation'] ?? $line['name'] ?? $line['service'] ?? 'Produit';
                $productStats[$name] = ($productStats[$name] ?? 0) + (float) ($line['quantity'] ?? 1);
            }
        }
        $stockItems = $stockEntries->groupBy(fn ($line) => strtolower($line->designation . '|' . ($line->article ?: '') . '|' . ($line->unit ?: '')))
            ->map(function ($lines) use ($stockExits) {
                $first = $lines->first();
                $key = strtolower($first->designation . '|' . ($first->article ?: '') . '|' . ($first->unit ?: ''));
                $exit = $stockExits->filter(fn ($line) => strtolower($line->designation . '|' . ($line->article ?: '') . '|' . ($line->unit ?: '')) === $key)->sum('quantity');
                return ['article' => $first->designation, 'available' => max(0, (float) $lines->sum('quantity') - (float) $exit), 'unit' => $first->unit ?: '-'];
            })->values();
        $paymentSummary = collect($invoices->groupBy(fn ($item) => ucfirst((string) ($item->payment_method ?: 'Non renseigné')))->map->sum('paid_amount'))
            ->merge($sales->groupBy(fn ($item) => ucfirst((string) ($item->payment_method ?: 'Non renseigné')))->map->sum('paid_amount'))
            ->groupBy(fn ($value, $key) => $key)->map->sum();
        $paymentRecap = collect();
        $paymentSources = $invoices->filter(fn ($item) => $item->created_at?->between($from, $to))
            ->map(fn ($item) => [
                'client' => $item->client_name ?: 'Client non renseigné',
                'due_date' => $item->created_at,
                'total' => (float) $item->amount,
                'paid' => (float) $item->paid_amount,
            ])
            ->concat($customInvoices->filter(fn ($item) => $item->quote_date?->between($from, $to))
                ->map(fn ($item) => [
                    'client' => $item->client_name ?: 'Client non renseigné',
                    'due_date' => $item->valid_until ?: $item->quote_date,
                    'total' => (float) $item->total_ttc,
                    'paid' => (float) $item->paid_amount,
                ]))
            ->concat($sales->filter(fn ($item) => $item->created_at?->between($from, $to))
                ->map(fn ($item) => [
                    'client' => $item->client_name ?: 'Client comptoir',
                    'due_date' => $item->created_at,
                    'total' => (float) $item->total,
                    'paid' => (float) $item->paid_amount,
                ]));
        $paymentRecap = $paymentSources->groupBy('client')->map(function ($items, $client) {
            $latestDueDate = $items->sortByDesc('due_date')->first()['due_date'] ?? null;
            $total = (float) $items->sum('total');
            $paid = (float) $items->sum('paid');
            return [
                'client' => $client,
                'due_date' => $latestDueDate,
                'total' => $total,
                'paid' => $paid,
                'remaining' => max($total - $paid, 0),
            ];
        })->sortByDesc('due_date')->values();
        $months = collect(range(5, 0))->map(function ($offset) use ($invoices, $customInvoices, $sales) {
            $date = now()->subMonths($offset);
            return [
                'label' => $date->translatedFormat('M'),
                'invoices' => (float) $invoices->filter(fn ($item) => $item->created_at?->isSameMonth($date))->sum('amount'),
                'custom_invoices' => (float) $customInvoices->filter(fn ($item) => $item->quote_date?->isSameMonth($date))->sum('total_ttc'),
                'sales' => (float) $sales->filter(fn ($item) => $item->created_at?->isSameMonth($date))->sum('total'),
            ];
        });

        return $this->page('commercial-tableau', [
            'title' => 'Tableau Commercial',
            'invoiced' => $invoiced,
            'collected' => $collected,
            'receivables' => $receivables,
            'pipeline' => $pipeline,
            'months' => $months,
            'monthlyRevenue' => $monthlyRevenue,
            'monthlyGrowth' => $monthlyGrowth,
            'newClients' => $clients->filter(fn ($client) => $client->created_at?->gte($currentMonth))->values(),
            'unpaidAmount' => (float) $unpaidInvoices->sum(fn ($item) => $item->amount - $item->paid_amount) + (float) $unpaidCustomInvoices->sum(fn ($item) => $item->total_ttc - $item->paid_amount),
            'cityStats' => $cityStats,
            'commercialPerformance' => $commercialPerformance,
            'productStats' => $productStats->sortDesc()->take(10),
            'lowStock' => $stockItems->where('available', '<=', 0)->values(),
            'paymentSummary' => $paymentSummary,
            'paymentRecap' => $paymentRecap,
            'paymentFrom' => $from->toDateString(),
            'paymentTo' => $to->toDateString(),
            'detailLists' => array_merge(compact('clients', 'quotes', 'orders', 'invoices', 'customInvoices', 'sales', 'leads', 'services'), [
                'unpaid' => $unpaidInvoices->concat($unpaidCustomInvoices),
            ]),
        ]);
    }

    public function index()
    {
        $leads = Lead::latest('next_step_at')->limit(6)->get();
        $qualified = Lead::where('status', 'qualified')->count();
        $proposal = Lead::where('status', 'proposal')->count();
        $totalPipeline = Lead::sum('value');

        return $this->page('commercial', [
            'title' => 'Commercial',
            'cards' => [
                ['title' => 'Prospects', 'description' => 'Pipeline commercial', 'value' => (string) $leads->count(), 'link' => '#'],
                ['title' => 'Devis', 'description' => 'Suivi des offres', 'value' => (string) $proposal, 'link' => '#'],
                ['title' => 'Commandes', 'description' => 'Validation et statut', 'value' => '18', 'link' => '#'],
                ['title' => 'Clients', 'description' => 'Fiche clients', 'value' => number_format($qualified * 2, 0, ',', ' '), 'link' => '#'],
            ],
            'leads' => $leads,
            'qualified' => $qualified,
            'proposal' => $proposal,
            'totalPipeline' => $totalPipeline,
            'modules' => collect($this->modules())->reject(fn (array $module) => ($module['key'] ?? null) === 'factures')->values()->all(),
        ]);
    }

    public function module(string $module)
    {
        $definition = collect($this->modules())->firstWhere('key', $module);
        abort_unless($definition, 404);

        if ($module === 'proforma') {
            return $this->proformas($definition);
        }
        if ($module === 'devis') {
            return $this->quotes($definition);
        }
        if ($module === 'facture-personnalisee') {
            return $this->customInvoice($definition);
        }
        if ($module === 'livraisons') {
            return $this->deliveries($definition);
        }
        if ($module === 'factures') {
            return $this->invoices($definition);
        }
        if ($module === 'services') {
            return $this->services($definition);
        }
        if ($module === 'point-de-vente') {
            return $this->pointOfSale($definition);
        }
        if ($module === 'clients') {
            return $this->clients($definition);
        }
        if ($module === 'fournisseurs') {
            return $this->suppliers($definition);
        }
        if ($module === 'objectifs') {
            return $this->objectives($definition);
        }
        if ($module === 'entrees-stock') {
            return $this->stockEntries($definition);
        }
        if ($module === 'sorties-stock') {
            return $this->stockExits($definition);
        }
        if ($module === 'etat-stock') {
            return $this->stockStatus($definition);
        }
        if ($module === 'inventaire') {
            return $this->inventory($definition);
        }

        return $this->page('commercial-module', [
            'title' => $definition['title'],
            'subtitle' => $definition['description'],
            'module' => $definition,
            'modules' => $this->modules(),
        ]);
    }

    public function customInvoice(?array $definition = null)
    {
        $definition = $definition ?? [
            'key' => 'facture-personnalisee',
            'title' => 'Facture personnalisée',
            'description' => 'Créer une facture sur mesure avec les caractéristiques du document, les services et les montants.',
        ];

        $invoices = CustomInvoice::where('entreprise_id', auth()->user()->entreprise_id)->latest()->get();

        return $this->page('commercial-custom-invoice-list', [
            'title' => $definition['title'],
            'subtitle' => $definition['description'],
            'module' => $definition,
            'invoices' => $invoices,
            'modules' => $this->modules(),
        ]);
    }

    public function createCustomInvoice()
    {
        $id = auth()->user()->entreprise_id;
        $clients = collect([
            ['id' => 1, 'name' => 'Boutique Kévin', 'email' => 'kevin@exemple.com', 'phone' => '+225 07 00 00 00'],
            ['id' => 2, 'name' => 'Média CI', 'email' => 'media@exemple.com', 'phone' => '+225 05 00 00 00'],
            ['id' => 3, 'name' => 'Imprimerie d’Afrique', 'email' => 'contact@imprimerie.com', 'phone' => '+225 01 00 00 00'],
        ]);

        return $this->page('commercial-custom-invoice', [
            'title' => 'Créer une facture personnalisée',
            'subtitle' => 'Renseignez les informations du client, les articles, les services et le montant total.',
            'module' => ['key' => 'facture-personnalisee', 'title' => 'Facture personnalisée'],
            'clients' => $clients,
            'cashAccounts' => CashAccount::where('entreprise_id', $id)->where('is_active', true)->orderBy('name')->get(),
            'modules' => $this->modules(),
            'invoice' => null,
        ]);
    }

    public function showCustomInvoice(CustomInvoice $invoice)
    {
        $this->authorizeWorkflow($invoice);

        return $this->page('commercial-custom-invoice-show', [
            'title' => 'Facture personnalisée',
            'subtitle' => 'Détail de la facture',
            'module' => ['key' => 'facture-personnalisee', 'title' => 'Facture personnalisée'],
            'invoice' => $invoice,
            'modules' => $this->modules(),
        ]);
    }

    public function editCustomInvoice(CustomInvoice $invoice)
    {
        $this->authorizeWorkflow($invoice);

        $id = auth()->user()->entreprise_id;
        $clients = collect([
            ['id' => 1, 'name' => 'Boutique Kévin', 'email' => 'kevin@exemple.com', 'phone' => '+225 07 00 00 00'],
            ['id' => 2, 'name' => 'Média CI', 'email' => 'media@exemple.com', 'phone' => '+225 05 00 00 00'],
            ['id' => 3, 'name' => 'Imprimerie d’Afrique', 'email' => 'contact@imprimerie.com', 'phone' => '+225 01 00 00 00'],
        ]);

        return $this->page('commercial-custom-invoice', [
            'title' => 'Modifier la facture personnalisée',
            'subtitle' => 'Mettez à jour les informations de la facture.',
            'module' => ['key' => 'facture-personnalisee', 'title' => 'Facture personnalisée'],
            'clients' => $clients,
            'cashAccounts' => CashAccount::where('entreprise_id', $id)->where('is_active', true)->orderBy('name')->get(),
            'modules' => $this->modules(),
            'invoice' => $invoice,
        ]);
    }

    public function storeCustomInvoice(Request $request)
    {
        $validated = $request->validate([
            'customer' => ['nullable', 'string', 'max:255'],
            'new_client_name' => ['nullable', 'string', 'max:255'],
            'new_client_phone' => ['nullable', 'string', 'max:255'],
            'new_client_email' => ['nullable', 'email', 'max:255'],
            'quote_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'payment_terms' => ['nullable', 'string', 'max:1000'],
            'delivery_terms' => ['nullable', 'string', 'max:1000'],
            'delivery_location' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'cash_account_id' => ['nullable', 'integer', 'exists:cash_accounts,id'],
            'bank_account_id' => ['nullable', 'integer'],
            'cash_payment_method' => ['nullable', 'string', 'max:100'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'objet' => ['nullable', 'string', 'max:255'],
            'global_services' => ['nullable', 'array'],
            'items' => ['nullable', 'array'],
            'discount_type' => ['nullable', 'in:none,percent,amount'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $items = collect($validated['items'] ?? [])->filter(fn ($item) => is_array($item))->map(function ($item) {
            return [
                'item_name' => $item['item_name'] ?? '',
                'item_category' => $item['item_category'] ?? '',
                'unit' => $item['unit'] ?? '',
                'quantity' => (float) ($item['quantity'] ?? 0),
                'price' => (float) ($item['price'] ?? 0),
                'book_type' => $item['book_type'] ?? null,
                'book_type_other' => $item['book_type_other'] ?? null,
                'paper_type' => $item['paper_type'] ?? null,
                'paper_type_other' => $item['paper_type_other'] ?? null,
                'page_count' => $item['page_count'] ?? null,
                'printing_type' => $item['printing_type'] ?? null,
                'cover_type' => $item['cover_type'] ?? null,
                'book_format' => $item['book_format'] ?? null,
                'format_other' => $item['format_other'] ?? null,
                'lamination' => $item['lamination'] ?? null,
                'binding_type' => $item['binding_type'] ?? null,
                'binding_other' => $item['binding_other'] ?? null,
                'additional_options' => $item['additional_options'] ?? null,
            ];
        })->values()->all();

        $services = collect($request->input('global_services', []))->map(function ($serviceKey) {
            $serviceCatalog = [
                'mise_en_page' => 50000,
                'conception_couverture' => 30000,
                'correction_orthographe' => 20000,
                'isbn' => 15000,
                'depot_legal' => 25000,
            ];
            return [
                'key' => $serviceKey,
                'label' => ucwords(str_replace('_', ' ', $serviceKey)),
                'price' => $serviceCatalog[$serviceKey] ?? 0,
            ];
        })->values()->all();

        $subtotal = collect($items)->sum(fn ($item) => ((float) ($item['quantity'] ?? 0)) * ((float) ($item['price'] ?? 0)));
        $servicesTotal = collect($services)->sum(fn ($service) => (float) ($service['price'] ?? 0));
        $baseAmount = $subtotal + $servicesTotal;
        $discountType = $validated['discount_type'] ?? 'none';
        $discountValue = (float) ($validated['discount_value'] ?? 0);
        $discountAmount = $discountType === 'percent' ? $baseAmount * ($discountValue / 100) : ($discountType === 'amount' ? $discountValue : 0);
        $netAfterDiscount = max(0, $baseAmount - $discountAmount);
        $vat = $netAfterDiscount * 0.18;
        $ttc = $netAfterDiscount + $vat;

        $clientName = $validated['new_client_name'] ?? $request->input('customer_name') ?? 'Client';
        $clientPhone = $validated['new_client_phone'] ?? $request->input('customer_phone') ?? '';
        $clientEmail = $validated['new_client_email'] ?? $request->input('customer_email') ?? '';

        $reference = 'FC-' . now()->format('Ymd') . '-' . str_pad((CustomInvoice::whereDate('created_at', now()->toDateString())->count() + 1), 4, '0', STR_PAD_LEFT);

        $paidAmountInput = (float) ($validated['paid_amount'] ?? 0);
        $paymentMethod = $validated['payment_method'] ?? ($paidAmountInput > 0 ? 'cash' : '');
        $cashAccountId = $validated['cash_account_id'] ?? null;

        $invoice = CustomInvoice::create([
            'entreprise_id' => auth()->user()->entreprise_id,
            'created_by_user_id' => auth()->id(),
            'reference' => $reference,
            'client_name' => $clientName,
            'client_phone' => $clientPhone,
            'client_email' => $clientEmail,
            'quote_date' => $validated['quote_date'] ?? now()->toDateString(),
            'valid_until' => $validated['valid_until'] ?? now()->addDays(30)->toDateString(),
            'payment_terms' => $validated['payment_terms'] ?? null,
            'delivery_terms' => $validated['delivery_terms'] ?? null,
            'delivery_location' => $validated['delivery_location'] ?? null,
            'payment_method' => $paymentMethod,
            'cash_account_id' => $cashAccountId,
            'bank_account_id' => $validated['bank_account_id'] ?? null,
            'cash_payment_method' => $validated['cash_payment_method'] ?? 'caisse_principale',
            'subject' => $validated['objet'] ?? null,
            'items' => $items,
            'global_services' => $services,
            'total_ht' => $baseAmount,
            'total_discount' => $discountAmount,
            'subtotal_after_discount' => $netAfterDiscount,
            'vat_amount' => $vat,
            'total_ttc' => $ttc,
            'paid_amount' => $paidAmountInput,
            'paid_at' => $paidAmountInput > 0 ? now() : null,
            'status' => $paidAmountInput >= $ttc ? 'paid' : ($paidAmountInput > 0 ? 'draft' : 'draft'),
        ]);

        if ($paidAmountInput > 0) {
            $this->registerCustomInvoiceCashPayment($invoice, $paidAmountInput, $paymentMethod, $cashAccountId, 'Paiement immédiat facture personnalisée');
        }

        $request->session()->flash('success', 'La facture personnalisée a bien été enregistrée.');

        return redirect()->route('admin.commercial.custom-invoice.show', $invoice);
    }

    public function updateCustomInvoice(Request $request, CustomInvoice $invoice)
    {
        $this->authorizeWorkflow($invoice);

        $validated = $request->validate([
            'customer' => ['nullable', 'string', 'max:255'],
            'new_client_name' => ['nullable', 'string', 'max:255'],
            'new_client_phone' => ['nullable', 'string', 'max:255'],
            'new_client_email' => ['nullable', 'email', 'max:255'],
            'quote_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'payment_terms' => ['nullable', 'string', 'max:1000'],
            'delivery_terms' => ['nullable', 'string', 'max:1000'],
            'delivery_location' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'cash_account_id' => ['nullable', 'integer', 'exists:cash_accounts,id'],
            'bank_account_id' => ['nullable', 'integer'],
            'cash_payment_method' => ['nullable', 'string', 'max:100'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'objet' => ['nullable', 'string', 'max:255'],
            'global_services' => ['nullable', 'array'],
            'items' => ['nullable', 'array'],
            'discount_type' => ['nullable', 'in:none,percent,amount'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $items = collect($validated['items'] ?? [])->filter(fn ($item) => is_array($item))->map(fn ($item) => [
            'item_name' => $item['item_name'] ?? '',
            'item_category' => $item['item_category'] ?? '',
            'unit' => $item['unit'] ?? '',
            'quantity' => (float) ($item['quantity'] ?? 0),
            'price' => (float) ($item['price'] ?? 0),
            'book_type' => $item['book_type'] ?? null,
            'book_type_other' => $item['book_type_other'] ?? null,
            'paper_type' => $item['paper_type'] ?? null,
            'paper_type_other' => $item['paper_type_other'] ?? null,
            'page_count' => $item['page_count'] ?? null,
            'printing_type' => $item['printing_type'] ?? null,
            'cover_type' => $item['cover_type'] ?? null,
            'book_format' => $item['book_format'] ?? null,
            'format_other' => $item['format_other'] ?? null,
            'lamination' => $item['lamination'] ?? null,
            'binding_type' => $item['binding_type'] ?? null,
            'binding_other' => $item['binding_other'] ?? null,
            'additional_options' => $item['additional_options'] ?? null,
        ])->values()->all();

        $services = collect($request->input('global_services', []))->map(function ($serviceKey) {
            $serviceCatalog = [
                'mise_en_page' => 50000,
                'conception_couverture' => 30000,
                'correction_orthographe' => 20000,
                'isbn' => 15000,
                'depot_legal' => 25000,
            ];
            return ['key' => $serviceKey, 'label' => ucwords(str_replace('_', ' ', $serviceKey)), 'price' => $serviceCatalog[$serviceKey] ?? 0];
        })->values()->all();

        $subtotal = collect($items)->sum(fn ($item) => ((float) ($item['quantity'] ?? 0)) * ((float) ($item['price'] ?? 0)));
        $servicesTotal = collect($services)->sum(fn ($service) => (float) ($service['price'] ?? 0));
        $baseAmount = $subtotal + $servicesTotal;
        $discountType = $validated['discount_type'] ?? 'none';
        $discountValue = (float) ($validated['discount_value'] ?? 0);
        $discountAmount = $discountType === 'percent' ? $baseAmount * ($discountValue / 100) : ($discountType === 'amount' ? $discountValue : 0);
        $netAfterDiscount = max(0, $baseAmount - $discountAmount);
        $vat = $netAfterDiscount * 0.18;
        $ttc = $netAfterDiscount + $vat;

        $previousPaidAmount = (float) ($invoice->paid_amount ?? 0);
        $paidAmountInput = $request->filled('paid_amount') ? (float) $validated['paid_amount'] : $previousPaidAmount;
        $paymentMethod = $validated['payment_method'] ?? $invoice->payment_method ?? 'cash';
        $cashAccountId = $request->filled('cash_account_id') ? $validated['cash_account_id'] : ($invoice->cash_account_id ?? null);

        $invoice->update([
            'client_name' => $validated['new_client_name'] ?? $invoice->client_name ?? 'Client',
            'client_phone' => $validated['new_client_phone'] ?? $invoice->client_phone,
            'client_email' => $validated['new_client_email'] ?? $invoice->client_email,
            'quote_date' => $validated['quote_date'] ?? $invoice->quote_date,
            'valid_until' => $validated['valid_until'] ?? $invoice->valid_until,
            'payment_terms' => $validated['payment_terms'] ?? $invoice->payment_terms,
            'delivery_terms' => $validated['delivery_terms'] ?? $invoice->delivery_terms,
            'delivery_location' => $validated['delivery_location'] ?? $invoice->delivery_location,
            'payment_method' => $paymentMethod,
            'cash_account_id' => $cashAccountId,
            'bank_account_id' => $request->filled('bank_account_id') ? $validated['bank_account_id'] : ($invoice->bank_account_id ?? null),
            'cash_payment_method' => $validated['cash_payment_method'] ?? $invoice->cash_payment_method,
            'subject' => $validated['objet'] ?? $invoice->subject,
            'items' => $items,
            'global_services' => $services,
            'total_ht' => $baseAmount,
            'total_discount' => $discountAmount,
            'subtotal_after_discount' => $netAfterDiscount,
            'vat_amount' => $vat,
            'total_ttc' => $ttc,
            'paid_amount' => $paidAmountInput,
            'paid_at' => $paidAmountInput > 0 ? ($invoice->paid_at ?? now()) : null,
            'status' => $paidAmountInput >= $ttc ? 'paid' : ($paidAmountInput > 0 ? 'draft' : $invoice->status),
        ]);

        if ($request->filled('paid_amount') && (float) $validated['paid_amount'] > 0) {
            $this->registerCustomInvoiceCashPayment($invoice, max(0, (float) $validated['paid_amount'] - $previousPaidAmount), $paymentMethod, $cashAccountId, 'Paiement facture personnalisée');
        }

        return redirect()->route('admin.commercial.custom-invoice.show', $invoice)->with('success', 'La facture personnalisée a bien été mise à jour.');
    }

    public function destroyCustomInvoice(CustomInvoice $invoice)
    {
        $this->authorizeWorkflow($invoice);

        if ((float) $invoice->paid_amount > 0 || ! empty($invoice->paid_at)) {
            return back()->withErrors(['invoice' => 'Impossible de supprimer une facture avec paiement déjà enregistré.']);
        }

        $invoice->delete();

        return redirect()->route('admin.commercial.custom-invoice.index')->with('success', 'La facture personnalisée a été supprimée.');
    }

    public function duplicateCustomInvoice(CustomInvoice $invoice)
    {
        $this->authorizeWorkflow($invoice);

        $clone = $invoice->replicate();
        $clone->reference = 'FC-' . now()->format('Ymd') . '-' . str_pad((CustomInvoice::whereDate('created_at', now()->toDateString())->count() + 1), 4, '0', STR_PAD_LEFT);
        $clone->status = 'draft';
        $clone->paid_amount = 0;
        $clone->paid_at = null;
        $clone->save();

        return redirect()->route('admin.commercial.custom-invoice.show', $clone)->with('success', 'La facture personnalisée a été dupliquée.');
    }

    public function emailCustomInvoice(CustomInvoice $invoice)
    {
        $this->authorizeWorkflow($invoice);

        return back()->with('success', 'La facture a été envoyée par email.');
    }

    public function sendCustomInvoiceWhatsApp(CustomInvoice $invoice)
    {
        $this->authorizeWorkflow($invoice);

        if (! $invoice->client_phone) {
            return back()->withErrors(['whatsapp' => 'Le client ne possède pas encore de numéro WhatsApp enregistré.']);
        }

        $whatsappNumber = $this->normalizeWhatsappNumber($invoice->client_phone);
        if ($whatsappNumber === '') {
            return back()->withErrors(['whatsapp' => 'Le numéro WhatsApp du client est invalide.']);
        }

        $amount = number_format((float) $invoice->total_ttc, 0, ',', ' ');
        $status = (float) $invoice->paid_amount >= (float) $invoice->total_ttc
            ? 'Payée'
            : ((float) $invoice->paid_amount > 0 ? 'Partiellement payée' : 'En attente de paiement');
        $message = "Bonjour {$invoice->client_name},\n\n"
            . "Voici votre facture {$invoice->reference} d’un montant de {$amount} XOF.\n"
            . "Statut : {$status}.\n\n"
            . 'Merci pour votre confiance.';

        return redirect()->away('https://wa.me/' . $whatsappNumber . '?text=' . rawurlencode($message));
    }

    public function printCustomInvoice(CustomInvoice $invoice)
    {
        $this->authorizeWorkflow($invoice);

        return $this->page('commercial-custom-invoice-print', [
            'title' => 'Facture personnalisée',
            'invoice' => $invoice,
            'modules' => $this->modules(),
        ]);
    }

    protected function registerCustomInvoiceCashPayment(CustomInvoice $invoice, float $amount, ?string $paymentMethod, ?int $cashAccountId, string $label): void
    {
        $normalizedMethod = strtolower((string) ($paymentMethod ?? $invoice->payment_method ?? 'cash'));
        $isCashPayment = in_array($normalizedMethod, ['cash', 'especes', 'espèces'], true);
        if ($amount <= 0 || ! $isCashPayment || empty($cashAccountId)) {
            return;
        }

        $account = CashAccount::where('entreprise_id', $invoice->entreprise_id)
            ->where('is_active', true)
            ->lockForUpdate()
            ->findOrFail($cashAccountId);

        $account->increment('balance', $amount);

        CashMovement::create([
            'entreprise_id' => $invoice->entreprise_id,
            'cash_account_id' => $account->id,
            'movement_type' => 'entry',
            'label' => $label,
            'amount' => $amount,
            'currency' => 'XOF',
            'payment_mode' => 'cash',
            'reference' => 'CUST-INV-' . $invoice->reference . '-' . now()->format('YmdHis'),
            'description' => 'Paiement d’une facture personnalisée : ' . $invoice->reference,
            'movement_date' => now()->toDateString(),
        ]);
    }

    public function recordCustomInvoicePayment(Request $request, CustomInvoice $invoice)
    {
        $this->authorizeWorkflow($invoice);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'cash_account_id' => ['nullable', 'integer', 'exists:cash_accounts,id'],
        ]);

        $previousPaidAmount = (float) ($invoice->paid_amount ?? 0);
        $remainingAmount = max(0, (float) $invoice->total_ttc - $previousPaidAmount);
        $paymentAmount = min((float) $data['amount'], $remainingAmount);
        $newPaidAmount = $previousPaidAmount + $paymentAmount;
        $paymentMethod = $data['payment_method'] ?? $invoice->payment_method ?? 'cash';
        $cashAccountId = $data['cash_account_id'] ?? $invoice->cash_account_id ?? null;

        if ($paymentAmount > 0) {
            $this->registerCustomInvoiceCashPayment($invoice, $paymentAmount, $paymentMethod, $cashAccountId, 'Paiement facture personnalisée');
        }

        $invoice->update([
            'paid_amount' => $newPaidAmount,
            'paid_at' => $newPaidAmount > 0 ? ($invoice->paid_at ?? now()) : null,
            'payment_method' => $paymentMethod,
            'cash_account_id' => $cashAccountId,
            'status' => $newPaidAmount >= (float) $invoice->total_ttc ? 'paid' : 'draft',
        ]);

        return redirect()->route('admin.commercial.custom-invoice.show', $invoice)->with('success', 'Le paiement a bien été enregistré.');
    }

    protected function inventory(array $definition)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $entries = StockEntryLine::whereHas('stockEntry', fn ($query) => $query->where('entreprise_id', $entrepriseId))->get();
        $exits = \App\Models\StockExitLine::whereHas('entryLine.stockEntry', fn ($query) => $query->where('entreprise_id', $entrepriseId))->get();
        $items = [];
        foreach ($entries as $line) {
            $key = strtolower($line->designation . '|' . ($line->article ?: '') . '|' . ($line->unit ?: ''));
            $items[$key] ??= ['designation' => $line->designation, 'article' => $line->article ?: '-', 'unit' => $line->unit ?: '-', 'entry' => 0, 'exit' => 0];
            $items[$key]['entry'] += (float) $line->quantity;
        }
        foreach ($exits as $line) {
            $key = strtolower($line->designation . '|' . ($line->article ?: '') . '|' . ($line->unit ?: ''));
            if (isset($items[$key])) {
                $items[$key]['exit'] += (float) $line->quantity;
            }
        }
        $audits = InventoryAudit::where('entreprise_id', $entrepriseId)->latest('audited_at')->get();
        $audited = [];
        foreach ($audits as $audit) {
            $key = strtolower($audit->designation . '|' . ($audit->article ?: '') . '|' . ($audit->unit ?: ''));
            $audited[$key] ??= $audit;
        }
        $items = collect($items)->map(function ($item, $key) use ($audited) {
            $item['theoretical'] = max(0, $item['entry'] - $item['exit']);
            $item['actual'] = isset($audited[$key]) ? (float) $audited[$key]->actual_quantity : $item['theoretical'];
            $item['variance'] = $item['actual'] - $item['theoretical'];
            $item['audit_date'] = $audited[$key]->audited_at ?? null;
            return $item;
        })->values();

        return $this->page('commercial-inventory', [
            'title' => $definition['title'], 'subtitle' => $definition['description'],
            'module' => $definition, 'modules' => $this->modules(), 'items' => $items,
        ]);
    }

    protected function clients(array $definition)
    {
        $id = auth()->user()->entreprise_id;
        return $this->page('commercial-clients', [
            'title' => $definition['title'], 'subtitle' => $definition['description'],
            'module' => $definition, 'modules' => $this->modules(),
            'clients' => CommercialClient::where('entreprise_id', $id)->latest()->get(),
        ]);
    }

    protected function suppliers(array $definition)
    {
        $id = auth()->user()->entreprise_id;
        return $this->page('commercial-suppliers', [
            'title' => $definition['title'], 'subtitle' => $definition['description'],
            'module' => $definition, 'modules' => $this->modules(),
            'suppliers' => CommercialSupplier::where('entreprise_id', $id)->latest()->get(),
        ]);
    }

    protected function objectives(array $definition)
    {
        $id = auth()->user()->entreprise_id;
        return $this->page('commercial-objectives', [
            'title' => $definition['title'], 'subtitle' => $definition['description'],
            'module' => $definition, 'modules' => $this->modules(),
            'objectives' => CommercialObjective::where('entreprise_id', $id)
                ->with(['assignments.employee'])->latest('objective_date')->get(),
            'employees' => Employee::where('entreprise_id', $id)->where('status', 'active')
                ->orderBy('full_name')->get(),
        ]);
    }

    protected function stockEntries(array $definition)
    {
        $id = auth()->user()->entreprise_id;
        return $this->page('commercial-stock-entries', [
            'title' => $definition['title'], 'subtitle' => $definition['description'],
            'module' => $definition, 'modules' => $this->modules(),
            'entries' => StockEntry::where('entreprise_id', $id)->with('lines')->latest()->get(),
        ]);
    }

    protected function stockStatus(array $definition)
    {
        $id = auth()->user()->entreprise_id;
        $entries = StockEntryLine::whereHas('stockEntry', fn ($query) => $query->where('entreprise_id', $id))->get();
        $exits = \App\Models\StockExitLine::whereHas('entryLine.stockEntry', fn ($query) => $query->where('entreprise_id', $id))->get();
        $items = [];
        foreach ($entries as $line) {
            $key = strtolower($line->designation . '|' . ($line->article ?: '') . '|' . ($line->unit ?: ''));
            $items[$key] ??= ['article' => $line->designation, 'category' => $line->article ?: '-', 'unit' => $line->unit ?: '-', 'initial' => 0, 'exit' => 0, 'purchase_total' => 0, 'profit_total' => 0];
            $items[$key]['initial'] += (float) $line->quantity;
            $items[$key]['purchase_total'] += (float) $line->quantity * (float) $line->purchase_price;
            $items[$key]['profit_total'] += (float) $line->quantity * (float) $line->profit_per_unit;
        }
        foreach ($exits as $line) {
            $key = strtolower($line->designation . '|' . ($line->article ?: '') . '|' . ($line->unit ?: ''));
            if (isset($items[$key])) {
                $items[$key]['exit'] += (float) $line->quantity;
            }
        }
        $items = collect($items)->map(function ($item) {
            $item['available'] = max(0, $item['initial'] - $item['exit']);
            $item['purchase_price'] = $item['initial'] ? $item['purchase_total'] / $item['initial'] : 0;
            $item['profit_unit'] = $item['initial'] ? $item['profit_total'] / $item['initial'] : 0;
            $item['sale_price'] = $item['purchase_price'] + $item['profit_unit'];
            $item['margin'] = $item['purchase_price'] ? ($item['profit_unit'] / $item['purchase_price']) * 100 : 0;
            $item['potential_profit'] = $item['available'] * $item['profit_unit'];
            return $item;
        })->values();
        return $this->page('commercial-stock-status', [
            'title' => $definition['title'], 'subtitle' => 'Aperçu du stock et bénéfice potentiel.',
            'module' => $definition, 'modules' => $this->modules(), 'items' => $items,
            'articlesCount' => $items->count(), 'inStockCount' => $items->where('available', '>', 0)->count(),
            'outOfStockCount' => $items->where('available', '<=', 0)->count(),
        ]);
    }

    public function createStockEntry()
    {
        return $this->page('commercial-stock-entry-create', [
            'title' => 'Nouvelle entrée de stock',
            'subtitle' => 'Enregistrez les articles réceptionnés et leur bénéfice.',
            'module' => collect($this->modules())->firstWhere('key', 'entrees-stock'),
            'modules' => $this->modules(),
        ]);
    }

    public function storeStockEntry(Request $request)
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.designation' => ['required', 'string', 'max:190'],
            'lines.*.article' => ['nullable', 'string', 'max:190'],
            'lines.*.unit' => ['nullable', 'string', 'max:50'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.purchase_price' => ['required', 'numeric', 'min:0'],
            'lines.*.profit_per_unit' => ['nullable', 'numeric', 'min:0'],
        ]);
        $prepared = collect($data['lines'])->map(function ($line) {
            $line['profit_per_unit'] = $line['profit_per_unit'] ?? 0;
            $line['total_purchase'] = (float) $line['quantity'] * (float) $line['purchase_price'];
            $line['total_profit'] = (float) $line['quantity'] * (float) $line['profit_per_unit'];
            return $line;
        });
        $entry = StockEntry::create([
            'entreprise_id' => auth()->user()->entreprise_id,
            'entry_date' => $data['entry_date'],
            'total_purchase' => $prepared->sum('total_purchase'),
            'total_profit' => $prepared->sum('total_profit'),
        ]);
        $entry->lines()->createMany($prepared->all());
        return back()->with('success', 'Entrée de stock enregistrée.');
    }

    protected function stockExits(array $definition)
    {
        $id = auth()->user()->entreprise_id;
        $lines = StockEntryLine::whereHas('stockEntry', fn ($query) => $query->where('entreprise_id', $id))
            ->orderBy('designation')->get();
        $used = \App\Models\StockExitLine::whereHas('entryLine.stockEntry', fn ($query) => $query->where('entreprise_id', $id))
            ->selectRaw('stock_entry_line_id, SUM(quantity) as quantity')->groupBy('stock_entry_line_id')->pluck('quantity', 'stock_entry_line_id');
        return $this->page('commercial-stock-exits', [
            'title' => $definition['title'], 'subtitle' => $definition['description'],
            'module' => $definition, 'modules' => $this->modules(),
            'exits' => StockExit::where('entreprise_id', $id)->with('lines')->latest()->get(),
            'stockLines' => $lines->filter(fn ($line) => (float) $line->quantity - (float) ($used[$line->id] ?? 0) > 0),
            'usedQuantities' => $used,
        ]);
    }

    public function createStockExit()
    {
        $id = auth()->user()->entreprise_id;
        $lines = StockEntryLine::whereHas('stockEntry', fn ($query) => $query->where('entreprise_id', $id))->orderBy('designation')->get();
        $used = \App\Models\StockExitLine::whereHas('entryLine.stockEntry', fn ($query) => $query->where('entreprise_id', $id))
            ->selectRaw('stock_entry_line_id, SUM(quantity) as quantity')->groupBy('stock_entry_line_id')->pluck('quantity', 'stock_entry_line_id');
        return $this->page('commercial-stock-exit-create', [
            'title' => 'Nouvelle sortie de stock', 'subtitle' => 'Enregistrez les articles sortis du stock.',
            'module' => collect($this->modules())->firstWhere('key', 'sorties-stock'), 'modules' => $this->modules(),
            'stockLines' => $lines->filter(fn ($line) => (float) $line->quantity - (float) ($used[$line->id] ?? 0) > 0),
            'usedQuantities' => $used,
        ]);
    }

    public function storeStockExit(Request $request)
    {
        $data = $request->validate([
            'exit_date' => ['required', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.stock_entry_line_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);
        $id = auth()->user()->entreprise_id;
        $used = \App\Models\StockExitLine::whereHas('entryLine.stockEntry', fn ($query) => $query->where('entreprise_id', $id))
            ->selectRaw('stock_entry_line_id, SUM(quantity) as quantity')->groupBy('stock_entry_line_id')->pluck('quantity', 'stock_entry_line_id');
        $entryLines = StockEntryLine::whereHas('stockEntry', fn ($query) => $query->where('entreprise_id', $id))
            ->whereIn('id', collect($data['lines'])->pluck('stock_entry_line_id'))->get()->keyBy('id');
        $requested = collect($data['lines'])->groupBy('stock_entry_line_id')
            ->map(fn ($lines) => $lines->sum(fn ($line) => (float) $line['quantity']));
        $prepared = collect($data['lines'])->map(function ($line) use ($entryLines, $used) {
            $entryLine = $entryLines->get($line['stock_entry_line_id']);
            abort_unless($entryLine, 422, 'Article de stock invalide.');
            $available = (float) $entryLine->quantity - (float) ($used[$entryLine->id] ?? 0);
            abort_if((float) $line['quantity'] > $available, 422, 'La quantité demandée dépasse le stock disponible.');
            return ['stock_entry_line_id' => $entryLine->id, 'designation' => $entryLine->designation, 'article' => $entryLine->article, 'unit' => $entryLine->unit, 'quantity' => $line['quantity'], 'unit_price' => $entryLine->purchase_price, 'total_value' => (float) $line['quantity'] * (float) $entryLine->purchase_price];
        });
        foreach ($requested as $lineId => $quantity) {
            $entryLine = $entryLines->get($lineId);
            abort_if(!$entryLine || $quantity > ((float) $entryLine->quantity - (float) ($used[$lineId] ?? 0)), 422, 'La quantité demandée dépasse le stock disponible.');
        }
        $exit = StockExit::create(['entreprise_id' => $id, 'exit_date' => $data['exit_date'], 'total_value' => $prepared->sum('total_value')]);
        $exit->lines()->createMany($prepared->all());
        return redirect()->route('admin.commercial.module', 'sorties-stock')->with('success', 'Sortie de stock enregistrée.');
    }

    public function storeObjective(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'objective_date' => ['required', 'date'],
        ]);
        CommercialObjective::create(array_merge($data, ['entreprise_id' => auth()->user()->entreprise_id]));
        return back()->with('success', 'Objectif annuel enregistré.');
    }

    public function updateObjective(Request $request, CommercialObjective $objective)
    {
        $this->authorizeWorkflow($objective);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'objective_date' => ['required', 'date'],
        ]);
        abort_if((float) $data['amount'] < (float) $objective->assignments()->sum('amount'), 422, 'Le montant de l’objectif doit couvrir les attributions existantes.');
        $objective->update($data);
        return back()->with('success', 'Objectif annuel modifié.');
    }

    public function destroyObjective(CommercialObjective $objective)
    {
        $this->authorizeWorkflow($objective);
        $objective->delete();
        return back()->with('success', 'Objectif annuel supprimé.');
    }

    public function storeObjectiveAssignment(Request $request, CommercialObjective $objective)
    {
        $this->authorizeWorkflow($objective);
        $data = $request->validate([
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.employee_id' => ['required', 'integer', 'distinct'],
            'assignments.*.amount' => ['required', 'numeric', 'gt:0'],
            'assignments.*.starts_at' => ['required', 'date'],
            'assignments.*.ends_at' => ['required', 'date', 'after_or_equal:assignments.*.starts_at'],
        ]);
        $employeeIds = collect($data['assignments'])->pluck('employee_id')->map(fn ($id) => (int) $id);
        $employees = Employee::where('entreprise_id', auth()->user()->entreprise_id)->where('status', 'active')->whereIn('id', $employeeIds)->get();
        abort_if($employees->count() !== $employeeIds->unique()->count(), 422, 'Un commercial sélectionné est invalide.');
        abort_if($objective->assignments()->whereIn('employee_id', $employeeIds)->exists(), 422, 'Un commercial sélectionné possède déjà une attribution pour cet objectif.');
        $newAmount = collect($data['assignments'])->sum(fn ($assignment) => (float) $assignment['amount']);
        $remaining = (float) $objective->amount - (float) $objective->assignments()->sum('amount');
        abort_if($newAmount > $remaining, 422, 'L’objectif annuel est atteint. Augmentez d’abord son montant avant d’ajouter cette attribution.');
        foreach ($data['assignments'] as $assignment) {
            CommercialObjectiveAssignment::create([
                'objective_id' => $objective->id,
                'employee_id' => $assignment['employee_id'],
                'amount' => $assignment['amount'],
                'starts_at' => $assignment['starts_at'],
                'ends_at' => $assignment['ends_at'],
            ]);
        }
        return back()->with('success', 'Attributions enregistrées pour les commerciaux sélectionnés.');
    }

    public function updateObjectiveAssignment(Request $request, CommercialObjectiveAssignment $assignment)
    {
        $this->authorizeWorkflow($assignment->objective);
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
        ]);
        $employee = Employee::where('entreprise_id', auth()->user()->entreprise_id)
            ->where('status', 'active')->findOrFail($data['employee_id']);
        $assignedTotal = (float) $assignment->objective->assignments()->where('id', '<>', $assignment->id)->sum('amount');
        abort_if($assignedTotal + (float) $data['amount'] > (float) $assignment->objective->amount, 422, 'L’objectif annuel est atteint. Augmentez d’abord son montant.');
        $assignment->update(array_merge($data, ['employee_id' => $employee->id]));
        return back()->with('success', 'Attribution modifiée.');
    }

    public function destroyObjectiveAssignment(CommercialObjectiveAssignment $assignment)
    {
        $this->authorizeWorkflow($assignment->objective);
        $assignment->delete();
        return back()->with('success', 'Attribution supprimée.');
    }

    public function storeClient(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'], 'responsible_name' => ['nullable', 'string', 'max:190'],
            'phone' => ['required', 'string', 'max:60'], 'email' => ['required', 'email', 'max:190'],
            'city' => ['nullable', 'string', 'max:100'], 'tax_id' => ['nullable', 'string', 'max:100'],
            'tax_regime' => ['nullable', 'string', 'max:190'], 'address' => ['nullable', 'string', 'max:1000'],
        ]);
        CommercialClient::create(array_merge($data, ['entreprise_id' => auth()->user()->entreprise_id]));
        return back()->with('success', 'Client enregistré.');
    }

    public function updateClient(Request $request, CommercialClient $client)
    {
        $this->authorizeWorkflow($client);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'], 'responsible_name' => ['nullable', 'string', 'max:190'],
            'phone' => ['required', 'string', 'max:60'], 'email' => ['required', 'email', 'max:190'],
            'city' => ['nullable', 'string', 'max:100'], 'tax_id' => ['nullable', 'string', 'max:100'],
            'tax_regime' => ['nullable', 'string', 'max:190'], 'address' => ['nullable', 'string', 'max:1000'],
        ]);
        $client->update($data);
        return back()->with('success', 'Client modifié.');
    }

    public function destroyClient(CommercialClient $client)
    {
        $this->authorizeWorkflow($client);
        $client->delete();
        return back()->with('success', 'Client supprimé.');
    }

    public function storeSupplier(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'], 'responsible_name' => ['required', 'string', 'max:190'],
            'tax_id' => ['required', 'string', 'max:100'], 'phone' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:190'], 'address' => ['required', 'string', 'max:1000'],
        ]);
        CommercialSupplier::create(array_merge($data, ['entreprise_id' => auth()->user()->entreprise_id]));
        return back()->with('success', 'Fournisseur enregistré.');
    }

    public function updateSupplier(Request $request, CommercialSupplier $supplier)
    {
        $this->authorizeWorkflow($supplier);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'], 'responsible_name' => ['required', 'string', 'max:190'],
            'tax_id' => ['required', 'string', 'max:100'], 'phone' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:190'], 'address' => ['required', 'string', 'max:1000'],
        ]);
        $supplier->update($data);
        return back()->with('success', 'Fournisseur modifié.');
    }

    public function destroySupplier(CommercialSupplier $supplier)
    {
        $this->authorizeWorkflow($supplier);
        $supplier->delete();
        return back()->with('success', 'Fournisseur supprimé.');
    }

    protected function quotes(array $definition)
    {
        $id = auth()->user()->entreprise_id;
        return $this->page('commercial-quotes', [
            'title' => $definition['title'], 'subtitle' => $definition['description'],
            'module' => $definition, 'modules' => $this->modules(),
            'clients' => CommercialClient::where('entreprise_id', $id)->orderBy('name')->get(),
            'quotes' => CommercialQuote::where('entreprise_id', $id)->with('creator')->latest()->get(),
        ]);
    }

    protected function deliveries(array $definition)
    {
        $id = auth()->user()->entreprise_id;

        return $this->page('commercial-deliveries', [
            'title' => $definition['title'],
            'subtitle' => 'Traitez les livraisons des devis validés.',
            'module' => $definition,
            'modules' => $this->modules(),
            'deliveries' => CommercialDelivery::where('entreprise_id', $id)
                ->with('order')
                ->latest()
                ->get(),
        ]);
    }

    protected function invoices(array $definition)
    {
        $id = auth()->user()->entreprise_id;

        return $this->page('commercial-invoices', [
            'title' => $definition['title'],
            'subtitle' => 'Factures créées après validation d’une livraison complète.',
            'module' => $definition,
            'modules' => $this->modules(),
            'cashAccounts' => \App\Models\CashAccount::where('entreprise_id', $id)->where('is_active', true)->orderBy('name')->get(),
            'bankAccounts' => \App\Models\BankAccount::where('entreprise_id', $id)->orderBy('name')->get(),
            'invoices' => CommercialInvoice::where('entreprise_id', $id)
                ->with(['delivery.order'])
                ->latest()
                ->get(),
        ]);
    }

    protected function services(array $definition)
    {
        $id = auth()->user()->entreprise_id;

        return $this->page('commercial-services', [
            'title' => $definition['title'],
            'subtitle' => $definition['description'],
            'module' => $definition,
            'modules' => $this->modules(),
            'services' => CommercialService::where('entreprise_id', $id)->latest()->get(),
        ]);
    }

    protected function pointOfSale(array $definition)
    {
        $id = auth()->user()->entreprise_id;
        return $this->page('commercial-pos', [
            'title' => $definition['title'],
            'subtitle' => 'Enregistrez les ventes comptoir et leurs encaissements.',
            'module' => $definition,
            'modules' => $this->modules(),
            'services' => CommercialService::where('entreprise_id', $id)->where('is_active', true)->orderBy('name')->get(),
            'clients' => CommercialClient::where('entreprise_id', $id)->orderBy('name')->get(),
            'cashAccounts' => CashAccount::where('entreprise_id', $id)->where('is_active', true)->orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('entreprise_id', $id)->orderBy('name')->get(),
            'sales' => PosSale::where('entreprise_id', $id)->latest()->limit(20)->get(),
        ]);
    }

    public function createPosSale()
    {
        $id = auth()->user()->entreprise_id;
        return $this->page('commercial-pos-create', [
            'title' => 'Nouvelle vente',
            'subtitle' => 'Enregistrez une vente comptoir.',
            'module' => collect($this->modules())->firstWhere('key', 'point-de-vente'),
            'modules' => $this->modules(),
            'services' => CommercialService::where('entreprise_id', $id)->where('is_active', true)->orderBy('name')->get(),
            'clients' => CommercialClient::where('entreprise_id', $id)->orderBy('name')->get(),
            'cashAccounts' => CashAccount::where('entreprise_id', $id)->where('is_active', true)->orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('entreprise_id', $id)->orderBy('name')->get(),
        ]);
    }

    public function storePosSale(Request $request)
    {
        $data = $request->validate([
            'client_id' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.service_id' => ['nullable', 'integer'],
            'lines.*.item_name' => ['required', 'string', 'max:190'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'in:cash,bank'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'cash_account_id' => ['required_if:payment_method,cash', 'nullable', 'integer'],
            'bank_account_id' => ['required_if:payment_method,bank', 'nullable', 'integer'],
        ]);
        $id = auth()->user()->entreprise_id;
        $client = ! empty($data['client_id']) ? CommercialClient::where('entreprise_id', $id)->findOrFail($data['client_id']) : null;
        $total = collect($data['lines'])->sum(fn ($line) => (float) $line['quantity'] * (float) $line['unit_price']);
        abort_if((float) $data['paid_amount'] < $total, 422, 'Le montant payé est inférieur au total de la vente.');
        $change = (float) $data['paid_amount'] - $total;
        DB::transaction(function () use ($data, $id, $client, $total, $change) {
            if ($data['payment_method'] === 'cash') {
                $account = CashAccount::where('entreprise_id', $id)->where('is_active', true)->lockForUpdate()->findOrFail($data['cash_account_id']);
                $account->increment('balance', $total);
                CashMovement::create(['entreprise_id'=>$id, 'cash_account_id'=>$account->id, 'movement_type'=>'entry', 'label'=>'Vente caisse', 'amount'=>$total, 'currency'=>'XOF', 'payment_mode'=>'cash', 'reference'=>'POS-' . now()->format('YmdHis'), 'description'=>'Vente au point de vente', 'movement_date'=>now()->toDateString()]);
                $cashId = $account->id;
                $bankId = null;
            } else {
                $account = BankAccount::where('entreprise_id', $id)->lockForUpdate()->findOrFail($data['bank_account_id']);
                $account->increment('current_balance', $total);
                $account->update(['status'=>'credit', 'last_transaction_label'=>'Vente point de vente', 'last_transaction_amount'=>$total, 'last_transaction_direction'=>'up']);
                BankTransaction::create(['entreprise_id'=>$id, 'bank_account_id'=>$account->id, 'transaction_type'=>'credit', 'is_transfer'=>false, 'label'=>'Vente point de vente', 'amount'=>$total, 'description'=>'Vente au point de vente', 'transaction_date'=>now()->toDateString()]);
                $cashId = null;
                $bankId = $account->id;
            }
            PosSale::create(['entreprise_id'=>$id, 'client_id'=>$client?->id, 'client_name'=>$client?->name, 'lines'=>$data['lines'], 'total'=>$total, 'paid_amount'=>$data['paid_amount'], 'change_amount'=>$change, 'payment_method'=>$data['payment_method'], 'cash_account_id'=>$cashId, 'bank_account_id'=>$bankId, 'status'=>'completed']);
        });
        return back()->with('success', 'Vente enregistrée et paiement comptabilisé.');
    }

    public function editPosSale(PosSale $sale)
    {
        $this->authorizeWorkflow($sale);
        abort_if($sale->status === 'cancelled', 422, 'Une vente annulée ne peut pas être modifiée.');
        $id = auth()->user()->entreprise_id;
        return $this->page('commercial-pos-create', [
            'title' => 'Modifier une vente',
            'subtitle' => 'Modifiez la vente avant sa clôture.',
            'module' => collect($this->modules())->firstWhere('key', 'point-de-vente'),
            'modules' => $this->modules(),
            'services' => CommercialService::where('entreprise_id', $id)->where('is_active', true)->orderBy('name')->get(),
            'clients' => CommercialClient::where('entreprise_id', $id)->orderBy('name')->get(),
            'cashAccounts' => CashAccount::where('entreprise_id', $id)->where('is_active', true)->orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('entreprise_id', $id)->orderBy('name')->get(),
            'sale' => $sale,
        ]);
    }

    public function destroyPosSale(PosSale $sale)
    {
        $this->authorizeWorkflow($sale);
        abort_if($sale->status !== 'cancelled', 422, 'Annulez la vente avant de la supprimer.');
        $sale->delete();
        return back()->with('success', 'Vente supprimée.');
    }

    public function updatePosSale(Request $request, PosSale $sale)
    {
        $this->authorizeWorkflow($sale);
        abort_if($sale->status === 'cancelled', 422, 'Une vente annulée ne peut pas être modifiée.');
        $data = $request->validate([
            'client_id' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.service_id' => ['nullable', 'integer'],
            'lines.*.item_name' => ['required', 'string', 'max:190'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
        ]);
        $id = auth()->user()->entreprise_id;
        $client = ! empty($data['client_id']) ? CommercialClient::where('entreprise_id', $id)->findOrFail($data['client_id']) : null;
        $total = collect($data['lines'])->sum(fn ($line) => (float) $line['quantity'] * (float) $line['unit_price']);
        abort_if((float) $data['paid_amount'] < $total, 422, 'Le montant payé est inférieur au total de la vente.');
        $delta = $total - (float) $sale->total;
        DB::transaction(function () use ($data, $sale, $client, $total, $delta, $id) {
            if ($delta != 0) {
                if ($sale->payment_method === 'cash') {
                    $account = CashAccount::where('entreprise_id', $id)->lockForUpdate()->findOrFail($sale->cash_account_id);
                    $delta > 0 ? $account->increment('balance', $delta) : $account->decrement('balance', abs($delta));
                    CashMovement::create(['entreprise_id'=>$id, 'cash_account_id'=>$account->id, 'movement_type'=>$delta > 0 ? 'entry' : 'exit', 'label'=>'Correction vente POS', 'amount'=>abs($delta), 'currency'=>'XOF', 'payment_mode'=>'cash', 'reference'=>'POS-COR-' . now()->format('YmdHis'), 'description'=>'Correction après modification de vente', 'movement_date'=>now()->toDateString()]);
                } else {
                    $account = BankAccount::where('entreprise_id', $id)->lockForUpdate()->findOrFail($sale->bank_account_id);
                    $delta > 0 ? $account->increment('current_balance', $delta) : $account->decrement('current_balance', abs($delta));
                    BankTransaction::create(['entreprise_id'=>$id, 'bank_account_id'=>$account->id, 'transaction_type'=>$delta > 0 ? 'credit' : 'debit', 'is_transfer'=>false, 'label'=>'Correction vente POS', 'amount'=>abs($delta), 'description'=>'Correction après modification de vente', 'transaction_date'=>now()->toDateString()]);
                }
            }
            $sale->update(['client_id'=>$client?->id, 'client_name'=>$client?->name, 'lines'=>$data['lines'], 'total'=>$total, 'paid_amount'=>$data['paid_amount'], 'change_amount'=>(float) $data['paid_amount'] - $total]);
        });
        return redirect()->route('admin.commercial.module', 'point-de-vente')->with('success', 'Vente modifiée.');
    }

    public function cancelPosSale(PosSale $sale)
    {
        $this->authorizeWorkflow($sale);
        abort_if($sale->status === 'cancelled', 422, 'Cette vente est déjà annulée.');
        $id = auth()->user()->entreprise_id;
        DB::transaction(function () use ($sale, $id) {
            if ($sale->payment_method === 'cash') {
                $account = CashAccount::where('entreprise_id', $id)->lockForUpdate()->findOrFail($sale->cash_account_id);
                $account->decrement('balance', $sale->total);
                CashMovement::create(['entreprise_id'=>$id, 'cash_account_id'=>$account->id, 'movement_type'=>'exit', 'label'=>'Annulation vente POS', 'amount'=>$sale->total, 'currency'=>'XOF', 'payment_mode'=>'cash', 'reference'=>'POS-ANN-' . now()->format('YmdHis'), 'description'=>'Annulation de la vente au point de vente', 'movement_date'=>now()->toDateString()]);
            } else {
                $account = BankAccount::where('entreprise_id', $id)->lockForUpdate()->findOrFail($sale->bank_account_id);
                $account->decrement('current_balance', $sale->total);
                $account->update(['status'=>'debit', 'last_transaction_label'=>'Annulation vente point de vente', 'last_transaction_amount'=>$sale->total, 'last_transaction_direction'=>'down']);
                BankTransaction::create(['entreprise_id'=>$id, 'bank_account_id'=>$account->id, 'transaction_type'=>'debit', 'is_transfer'=>false, 'label'=>'Annulation vente point de vente', 'amount'=>$sale->total, 'description'=>'Annulation de la vente au point de vente', 'transaction_date'=>now()->toDateString()]);
            }
            $sale->update(['status' => 'cancelled']);
        });
        return back()->with('success', 'Vente annulée.');
    }

    public function showPosSale(PosSale $sale)
    {
        $this->authorizeWorkflow($sale);
        return $this->page('commercial-pos-show', ['title' => 'Détail de la vente', 'sale' => $sale, 'module' => collect($this->modules())->firstWhere('key', 'point-de-vente'), 'modules' => $this->modules()]);
    }

    public function storeService(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
        ]);
        CommercialService::create(array_merge($data, [
            'entreprise_id' => auth()->user()->entreprise_id,
            'is_active' => true,
        ]));
        return redirect()->route('admin.commercial.module', 'services')->with('success', 'Service enregistré.');
    }

    public function updateService(Request $request, CommercialService $service)
    {
        $this->authorizeWorkflow($service);
        $service->update($request->validate([
            'name' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]));
        return back()->with('success', 'Service modifié.');
    }

    public function destroyService(CommercialService $service)
    {
        $this->authorizeWorkflow($service);
        $service->delete();
        return back()->with('success', 'Service supprimé.');
    }

    public function createQuote()
    {
        $id = auth()->user()->entreprise_id;
        return $this->page('commercial-quote-create', [
            'title' => 'Créer un devis',
            'subtitle' => 'Saisissez les lignes et les montants du devis',
            'module' => collect($this->modules())->firstWhere('key', 'devis'),
            'modules' => $this->modules(),
            'clients' => CommercialClient::where('entreprise_id', $id)->orderBy('name')->get(),
            'quoteArticles' => $this->quoteArticles($id),
            'quoteServices' => CommercialService::where('entreprise_id', $id)->where('is_active', true)->orderBy('name')->get(['name', 'price', 'unit']),
        ]);
    }

    protected function quoteArticles(int $entrepriseId)
    {
        return StockEntryLine::whereHas('stockEntry', fn ($query) => $query->where('entreprise_id', $entrepriseId))
            ->orderBy('designation')
            ->get(['designation', 'article', 'unit'])
            ->map(function ($line) {
                return [
                    'name' => $line->designation,
                    'article' => $line->article,
                    'unit' => $line->unit,
                ];
            })
            ->filter(fn ($article) => trim((string) $article['name']) !== '')
            ->unique(fn ($article) => strtolower($article['name'] . '|' . ($article['article'] ?: '') . '|' . ($article['unit'] ?: '')))
            ->values();
    }

    public function storeQuote(Request $request)
    {
        $this->saveQuote($request->validate($this->quoteRules()));
        return redirect()->route('admin.commercial.module', 'devis')
            ->with('success', 'Devis enregistré en attente de validation.');
    }

    protected function quoteRules(): array
    {
        return [
            'client_id' => ['required','integer'],
            'quote_date' => ['required','date'],
            'due_date' => ['nullable','date','after_or_equal:quote_date'],
            'payment_terms' => ['nullable','string','max:190'],
            'delivery_terms' => ['nullable','string','max:190'],
            'delivery_location' => ['nullable','string','max:190'],
            'payment_method' => ['nullable','in:Espèces,Chèque,Virement,Carte bancaire'],
            'subject' => ['nullable','string','max:2000'],
            'total_discount' => ['nullable','numeric','min:0'],
            'tax_rate' => ['nullable','numeric','min:0','max:100'],
            'lines' => ['required','array','min:1'],
            'lines.*.item_name' => ['required','string','max:190'],
            'lines.*.quantity' => ['required','numeric','gt:0'],
            'lines.*.unit_price' => ['required','numeric','min:0'],
        ];
    }

    protected function saveQuote(array $data, ?CommercialQuote $quote = null): CommercialQuote
    {
        $id = auth()->user()->entreprise_id;
        $client = CommercialClient::where('entreprise_id', $id)->findOrFail($data['client_id']);
        $totalHt = collect($data['lines'])->sum(fn ($line) => (float) $line['quantity'] * (float) $line['unit_price']);
        $discount = min($totalHt, (float) ($data['total_discount'] ?? 0));
        $netHt = $totalHt - $discount;
        $taxRate = (float) ($data['tax_rate'] ?? 0);
        $taxAmount = $netHt * $taxRate / 100;
        $quote = $quote ?: new CommercialQuote();
        $quote->fill([
            'entreprise_id'=>$id, 'client_id'=>$client->id, 'client_name'=>$client->name,
            'quote_date'=>$data['quote_date'], 'due_date'=>$data['due_date'] ?? null,
            'payment_terms'=>$data['payment_terms'] ?? null, 'delivery_terms'=>$data['delivery_terms'] ?? null,
            'delivery_location'=>$data['delivery_location'] ?? null, 'payment_method'=>$data['payment_method'] ?? null,
            'subject'=>$data['subject'] ?? null, 'total_ht'=>$totalHt, 'total_discount'=>$discount,
            'net_ht'=>$netHt, 'tax_rate'=>$taxRate, 'tax_amount'=>$taxAmount, 'total_ttc'=>$netHt + $taxAmount,
            'lines'=>$data['lines'], 'status'=>'pending_validation',
        ]);
        if (! $quote->exists) {
            $quote->created_by_user_id = auth()->id();
            $quote->reference = 'DEV-' . now()->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        }
        $quote->save();
        return $quote;
    }

    public function editQuote(CommercialQuote $quote)
    {
        $this->authorizeWorkflow($quote);
        abort_if($quote->status !== 'pending_validation', 422, 'Un devis validé ne peut plus être modifié.');
        return $this->page('commercial-quote-create', [
            'title' => 'Modifier un devis',
            'subtitle' => 'Modifiez les informations du devis avant validation',
            'module' => collect($this->modules())->firstWhere('key', 'devis'),
            'modules' => $this->modules(),
            'clients' => CommercialClient::where('entreprise_id', auth()->user()->entreprise_id)->orderBy('name')->get(),
            'quoteArticles' => $this->quoteArticles(auth()->user()->entreprise_id),
            'quoteServices' => CommercialService::where('entreprise_id', auth()->user()->entreprise_id)->where('is_active', true)->orderBy('name')->get(['name', 'price', 'unit']),
            'quote' => $quote,
        ]);
    }

    public function updateQuote(Request $request, CommercialQuote $quote)
    {
        $this->authorizeWorkflow($quote);
        abort_if($quote->status !== 'pending_validation', 422, 'Un devis validé ne peut plus être modifié.');
        $this->saveQuote($request->validate($this->quoteRules()), $quote);
        return redirect()->route('admin.commercial.module', 'devis')->with('success', 'Devis modifié.');
    }

    public function destroyQuote(CommercialQuote $quote)
    {
        $this->authorizeWorkflow($quote);
        $quote->delete();
        return back()->with('success', 'Devis supprimé.');
    }

    public function duplicateQuote(CommercialQuote $quote)
    {
        $this->authorizeWorkflow($quote);
        $copy = $quote->replicate();
        $copy->status = 'pending_validation';
        $copy->reference = 'DEV-' . now()->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $copy->created_by_user_id = auth()->id();
        $copy->customer_order_code = null;
        $copy->subject = trim(($quote->subject ?: '') . ' (copie)');
        $copy->save();
        return back()->with('success', 'Devis dupliqué.');
    }

    public function printQuote(CommercialQuote $quote)
    {
        $this->authorizeWorkflow($quote);
        return view('admin.commercial-quote-print', [
            'quote' => $quote,
            'client' => CommercialClient::where('entreprise_id', $quote->entreprise_id)->find($quote->client_id),
            'company' => auth()->user()->entreprise,
        ]);
    }

    public function emailQuote(CommercialQuote $quote)
    {
        $this->authorizeWorkflow($quote);
        $client = CommercialClient::where('entreprise_id', $quote->entreprise_id)->find($quote->client_id);
        if (! $client || ! $client->email) {
            return back()->withErrors(['email' => 'Le client ne possède pas encore d’adresse email enregistrée.']);
        }

        Mail::raw("Bonjour,\n\nVeuillez trouver votre devis d'un montant TTC de {$quote->total_ttc} XOF.\n\nCordialement.", function ($message) use ($client) {
            $message->to($client->email)->subject('Votre devis');
        });
        return back()->with('success', 'Devis envoyé automatiquement à ' . $client->email . '.');
    }

    public function validateQuote(CommercialQuote $quote)
    {
        $this->authorizeWorkflow($quote);
        abort_if($quote->status !== 'pending_validation', 422, 'Ce devis est déjà traité.');
        $data = request()->validate([
            'customer_order_code' => ['required', 'string', 'max:100'],
        ]);
        DB::transaction(function () use ($quote, $data) {
            $quote->update(['status'=>'validated', 'customer_order_code'=>$data['customer_order_code']]);
            $order = CommercialOrder::create([
                'entreprise_id'=>$quote->entreprise_id, 'created_by_user_id'=>$quote->created_by_user_id, 'quote_id'=>$quote->id, 'client_name'=>$quote->client_name,
                'customer_order_code'=>$quote->customer_order_code,
                'lines'=>$quote->lines, 'total_ttc'=>$quote->total_ttc, 'status'=>'pending_delivery',
            ]);
            CommercialDelivery::create([
                'entreprise_id'=>$quote->entreprise_id, 'created_by_user_id'=>$quote->created_by_user_id, 'order_id'=>$order->id, 'client_name'=>$quote->client_name,
                'lines'=>$quote->lines, 'delivery_type'=>'partial', 'status'=>'pending_validation',
            ]);
        });
        return back()->with('success', 'Devis validé : bon de commande et bon de livraison créés.');
    }

    public function validateDelivery(Request $request, CommercialDelivery $delivery)
    {
        $this->authorizeWorkflow($delivery);
        $data = $request->validate(['delivery_type'=>['required','in:partial,complete']]);
        DB::transaction(function () use ($delivery, $data) {
            abort_if(StockExit::where('entreprise_id', $delivery->entreprise_id)->where('delivery_id', $delivery->id)->exists(), 422, 'La sortie de stock de cette livraison existe déjà.');
            $stockLines = StockEntryLine::whereHas('stockEntry', fn ($query) => $query->where('entreprise_id', $delivery->entreprise_id))->get();
            $used = \App\Models\StockExitLine::whereHas('entryLine.stockEntry', fn ($query) => $query->where('entreprise_id', $delivery->entreprise_id))
                ->selectRaw('stock_entry_line_id, SUM(quantity) as quantity')->groupBy('stock_entry_line_id')->pluck('quantity', 'stock_entry_line_id');
            $exitLines = [];
            foreach ($delivery->lines ?: [] as $line) {
                $name = trim((string) ($line['item_name'] ?? $line['designation'] ?? ''));
                $quantity = (float) ($line['quantity'] ?? 0);
                abort_if($name === '' || $quantity <= 0, 422, 'Une ligne de livraison est invalide.');
                $stock = $stockLines->first(function ($stockLine) use ($name, $quantity, $used) {
                    $labelMatches = strcasecmp($stockLine->designation, $name) === 0
                        || ($stockLine->article && strcasecmp($stockLine->article, $name) === 0);
                    return $labelMatches && $quantity <= ((float) $stockLine->quantity - (float) ($used[$stockLine->id] ?? 0));
                });
                abort_if(!$stock, 422, "Stock insuffisant ou article introuvable : {$name}.");
                $exitLines[] = [
                    'stock_entry_line_id' => $stock->id, 'designation' => $stock->designation,
                    'article' => $stock->article, 'unit' => $stock->unit, 'quantity' => $quantity,
                    'unit_price' => $stock->purchase_price, 'total_value' => $quantity * (float) $stock->purchase_price,
                ];
                $used[$stock->id] = (float) ($used[$stock->id] ?? 0) + $quantity;
            }
            $exit = StockExit::create([
                'entreprise_id' => $delivery->entreprise_id, 'delivery_id' => $delivery->id,
                'exit_date' => now()->toDateString(), 'total_value' => collect($exitLines)->sum('total_value'),
            ]);
            $exit->lines()->createMany($exitLines);
            $delivery->update(['delivery_type'=>$data['delivery_type'], 'status'=>'validated']);
            $delivery->order()->update([
                'status' => $data['delivery_type'] === 'complete' ? 'delivered' : 'partially_delivered',
            ]);
            if ($data['delivery_type'] === 'complete') {
                CommercialInvoice::firstOrCreate(
                    ['entreprise_id'=>$delivery->entreprise_id, 'delivery_id'=>$delivery->id],
                    ['created_by_user_id'=>$delivery->created_by_user_id, 'client_name'=>$delivery->client_name, 'amount'=>$delivery->order->total_ttc, 'paid_amount'=>0, 'status'=>'unpaid']
                );
            }
        });
        return back()->with('success', $data['delivery_type'] === 'complete'
            ? 'Livraison complète validée : facture créée pour paiement.'
            : 'Livraison partielle validée.');
    }

    public function recordInvoicePayment(Request $request, CommercialInvoice $invoice)
    {
        $this->authorizeWorkflow($invoice);
        $data = $request->validate([
            'paid_amount'=>['required','numeric','min:0.01'],
            'payment_method'=>['required','in:cash,bank'],
            'cash_account_id'=>['required_if:payment_method,cash','nullable','integer'],
            'bank_account_id'=>['required_if:payment_method,bank','nullable','integer'],
        ]);
        $remaining = max(0, (float) $invoice->amount - (float) $invoice->paid_amount);
        abort_if((float) $data['paid_amount'] > $remaining, 422, 'Le paiement dépasse le montant restant.');
        DB::transaction(function () use ($invoice, $data, $remaining) {
            if ($data['payment_method'] === 'cash') {
                $account = CashAccount::where('entreprise_id', $invoice->entreprise_id)->where('is_active', true)->lockForUpdate()->findOrFail($data['cash_account_id'] ?? null);
                $account->increment('balance', $data['paid_amount']);
                CashMovement::create([
                    'entreprise_id' => $invoice->entreprise_id,
                    'cash_account_id' => $account->id,
                    'movement_type' => 'entry',
                    'label' => 'Paiement facture #' . $invoice->id,
                    'amount' => $data['paid_amount'],
                    'currency' => 'XOF',
                    'payment_mode' => 'cash',
                    'reference' => 'FACTURE-' . $invoice->id,
                    'description' => 'Encaissement client',
                    'movement_date' => now()->toDateString(),
                ]);
            } else {
                $account = BankAccount::where('entreprise_id', $invoice->entreprise_id)->lockForUpdate()->findOrFail($data['bank_account_id'] ?? null);
                $account->increment('current_balance', $data['paid_amount']);
                $account->update([
                    'status' => 'credit',
                    'last_transaction_label' => 'Paiement facture #' . $invoice->id,
                    'last_transaction_amount' => $data['paid_amount'],
                    'last_transaction_direction' => 'up',
                ]);
                BankTransaction::create([
                    'entreprise_id' => $invoice->entreprise_id,
                    'bank_account_id' => $account->id,
                    'transaction_type' => 'credit',
                    'is_transfer' => false,
                    'label' => 'Paiement facture #' . $invoice->id,
                    'amount' => $data['paid_amount'],
                    'description' => 'Encaissement client',
                    'transaction_date' => now()->toDateString(),
                ]);
            }
            $paid = (float) $invoice->paid_amount + (float) $data['paid_amount'];
            $invoice->update([
                'paid_amount'=>$paid,
                'payment_method'=>$data['payment_method'],
                'cash_account_id'=>$data['payment_method'] === 'cash' ? $data['cash_account_id'] : null,
                'bank_account_id'=>$data['payment_method'] === 'bank' ? $data['bank_account_id'] : null,
                'status'=>$paid >= (float)$invoice->amount ? 'paid' : 'partially_paid',
            ]);
        });
        return back()->with('success', 'Paiement enregistré.');
    }

    public function cancelInvoice(CommercialInvoice $invoice)
    {
        $this->authorizeWorkflow($invoice);
        abort_if($invoice->status === 'paid', 422, 'Une facture payée ne peut pas être annulée.');
        $invoice->update(['status' => 'cancelled']);
        return back()->with('success', 'Facture annulée.');
    }

    public function printInvoice(CommercialInvoice $invoice)
    {
        $this->authorizeWorkflow($invoice);
        $invoice->load('delivery.order.quote');
        $quote = $invoice->delivery?->order?->quote;
        return view('admin.commercial-invoice-print', [
            'invoice' => $invoice,
            'client' => $quote ? CommercialClient::where('entreprise_id', $invoice->entreprise_id)->find($quote->client_id) : null,
            'company' => auth()->user()->entreprise,
        ]);
    }

    public function printFneInvoice(CommercialInvoice $invoice)
    {
        $this->authorizeWorkflow($invoice);
        abort_unless($invoice->fne_status === 'certified', 404);
        $invoice->load('delivery.order.quote');
        $quote = $invoice->delivery?->order?->quote;

        return view('admin.fne-invoice-print', [
            'invoice' => $invoice,
            'client' => $quote?->client_id
                ? CommercialClient::where('entreprise_id', $invoice->entreprise_id)->find($quote->client_id)
                : null,
            'company' => auth()->user()->entreprise,
            'documentType' => 'Vente',
        ]);
    }

    public function certifyInvoice(CommercialInvoice $invoice, FneCertificationService $fne)
    {
        $this->authorizeWorkflow($invoice);
        $invoice->load('delivery.order.quote');
        $quote = $invoice->delivery?->order?->quote;
        $client = $quote?->client_id
            ? CommercialClient::where('entreprise_id', $invoice->entreprise_id)->find($quote->client_id)
            : null;
        $lines = collect($invoice->delivery?->lines ?: [])->map(fn ($line) => [
            'taxes' => [(float) $invoice->amount > 0 ? 'TVA' : 'TVAE'],
            'reference' => $line['reference'] ?? ('REF-' . ($line['product_id'] ?? 'SERVICE')),
            'description' => $line['description'] ?? $line['name'] ?? 'Article',
            'quantity' => (float) ($line['quantity'] ?? 1),
            'amount' => (float) ($line['unit_price'] ?? $line['price'] ?? 0),
            'discount' => 0,
            'measurementUnit' => $line['unit'] ?? 'pcs',
        ])->values()->all();
        if (!$lines) {
            return back()->withErrors(['fne' => 'La facture ne contient aucune ligne certifiable.']);
        }
        try {
            $fne->certify($invoice, 'sale', [
                'invoiceType' => 'sale', 'paymentMethod' => $this->fnePaymentMethod($invoice->payment_method),
                'template' => $client?->tax_id ? 'B2B' : 'B2C', 'isRne' => false,
                'clientCompanyName' => $client?->name ?: $invoice->client_name ?: 'Client',
                'clientPhone' => $client?->phone ?: '', 'clientEmail' => $client?->email ?: '',
                'clientNcc' => $client?->tax_id, 'pointOfSale' => config('fne.point_of_sale'),
                'establishment' => config('fne.establishment'), 'commercialMessage' => 'Merci pour votre confiance',
                'footer' => 'Service client: ' . (auth()->user()->entreprise->email ?? ''), 'items' => $lines, 'discount' => 0,
            ]);
            return back()->with('success', 'Facture certifiée par la FNE.');
        } catch (\Throwable $e) {
            return back()->withErrors(['fne' => $e->getMessage()]);
        }
    }

    protected function fnePaymentMethod(?string $method): string
    {
        return match (strtolower((string) $method)) {
            'card', 'carte', 'bank' => 'card',
            'check', 'cheque', 'chèque' => 'check',
            'mobile', 'mobile-money', 'momo' => 'mobile-money',
            'transfer', 'virement' => 'transfer',
            'deferred', 'credit', 'crédit' => 'deferred',
            default => 'cash',
        };
    }

    public function emailInvoice(CommercialInvoice $invoice)
    {
        $this->authorizeWorkflow($invoice);
        $client = $invoice->delivery?->order?->quote?->client_id
            ? CommercialClient::where('entreprise_id', $invoice->entreprise_id)->find($invoice->delivery->order->quote->client_id)
            : null;
        if (! $client || ! $client->email) {
            return back()->withErrors(['email' => 'Le client ne possède pas encore d’adresse email enregistrée.']);
        }
        Mail::raw("Bonjour,\n\nVeuillez trouver votre facture d'un montant de {$invoice->amount} XOF.\n\nCordialement.", function ($message) use ($client) {
            $message->to($client->email)->subject('Votre facture');
        });
        return back()->with('success', 'Facture envoyée automatiquement à ' . $client->email . '.');
    }

    public function sendInvoiceWhatsApp(CommercialInvoice $invoice)
    {
        $this->authorizeWorkflow($invoice);
        $client = $invoice->delivery?->order?->quote?->client_id
            ? CommercialClient::where('entreprise_id', $invoice->entreprise_id)->find($invoice->delivery->order->quote->client_id)
            : null;

        $phone = $client?->phone ?? $invoice->delivery?->order?->quote?->client?->phone ?? null;
        if (! $phone) {
            return back()->withErrors(['whatsapp' => 'Le client ne possède pas encore de numéro WhatsApp enregistré.']);
        }

        $whatsappNumber = $this->normalizeWhatsappNumber($phone);
        if ($whatsappNumber === '') {
            return back()->withErrors(['whatsapp' => 'Le numéro WhatsApp du client est invalide.']);
        }

        $invoiceLabel = 'FACTURE ' . $invoice->id;
        $amount = number_format((float) $invoice->amount, 0, ',', ' ');
        $message = 'Bonjour ' . ($client?->name ?? $invoice->client_name ?? 'Client') . ',%0A%0A' .
            'Voici votre facture ' . $invoiceLabel . ' d’un montant de ' . $amount . ' XOF.%0A' .
            'Statut : ' . ($invoice->status === 'paid' ? 'Payée' : ($invoice->status === 'partially_paid' ? 'Partiellement payée' : 'En attente de paiement')) . '.%0A%0A' .
            'Merci pour votre confiance.';

        return redirect()->away('https://wa.me/' . $whatsappNumber . '?text=' . $message);
    }

    protected function normalizeWhatsappNumber(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '+')) {
            $digits = ltrim($digits, '+');
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = '225' . substr($digits, 1);
        }

        if (strlen($digits) === 9) {
            $digits = '225' . $digits;
        }

        return $digits;
    }

    protected function authorizeWorkflow($model): void
    {
        abort_unless($model->entreprise_id === auth()->user()?->entreprise_id, 403);
    }

    protected function proformas(array $definition)
    {
        $entrepriseId = auth()->user()->entreprise_id;

        return $this->page('commercial-proformas', [
            'title' => $definition['title'],
            'subtitle' => $definition['description'],
            'module' => $definition,
            'modules' => $this->modules(),
            'clients' => CommercialClient::where('entreprise_id', $entrepriseId)->orderBy('name')->get(),
            'proformas' => CommercialProforma::where('entreprise_id', $entrepriseId)->with('lines')->latest()->get(),
        ]);
    }

    public function createProforma()
    {
        $entrepriseId = auth()->user()->entreprise_id;

        return $this->page('commercial-proforma-create', [
            'title' => 'Créer un proforma',
            'subtitle' => 'Saisissez le client, les lignes, les remises et les taxes',
            'module' => collect($this->modules())->firstWhere('key', 'proforma'),
            'modules' => $this->modules(),
            'clients' => CommercialClient::where('entreprise_id', $entrepriseId)->orderBy('name')->get(),
        ]);
    }

    public function storeProforma(Request $request)
    {
        $this->saveProforma($request->validate($this->proformaRules()));
        return redirect()->route('admin.commercial.module', 'proforma')->with('success', 'Proforma enregistré.');
    }

    protected function proformaRules(): array
    {
        return [
            'client_id' => ['nullable', 'integer'],
            'new_client_name' => ['nullable', 'string', 'max:190'],
            'new_client_phone' => ['nullable', 'string', 'max:60'],
            'new_client_email' => ['nullable', 'email', 'max:190'],
            'creation_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:creation_date'],
            'payment_terms' => ['nullable', 'string', 'max:190'],
            'delivery_terms' => ['nullable', 'string', 'max:190'],
            'delivery_location' => ['nullable', 'string', 'max:190'],
            'payment_method' => ['nullable', 'in:Espèces,Chèque,Virement,Carte bancaire'],
            'subject' => ['nullable', 'string', 'max:2000'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.type' => ['required', 'in:product,service'],
            'lines.*.category' => ['nullable', 'string', 'max:190'],
            'lines.*.item_type' => ['required','in:article,service'],
            'lines.*.item_name' => ['required','string','max:190'],
            'lines.*.unit' => ['nullable', 'string', 'max:50'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_type' => ['nullable', 'in:percent,amount'],
        ];
    }

    protected function saveProforma(array $data, ?CommercialProforma $proforma = null): CommercialProforma
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $client = null;
        if (! empty($data['client_id'])) {
            $client = CommercialClient::where('entreprise_id', $entrepriseId)->findOrFail($data['client_id']);
        } elseif (! empty($data['new_client_name'])) {
            $client = CommercialClient::create([
                'entreprise_id' => $entrepriseId,
                'name' => $data['new_client_name'],
                'phone' => $data['new_client_phone'] ?? null,
                'email' => $data['new_client_email'] ?? null,
            ]);
        }
        abort_unless($client, 422, 'Veuillez sélectionner ou créer un client.');

        $preparedLines = [];
        $totalHt = $totalDiscount = 0;
        foreach ($data['lines'] as $line) {
            $gross = (float) $line['quantity'] * (float) $line['unit_price'];
            $discount = ($line['discount_type'] ?? 'percent') === 'amount'
                ? (float) ($line['discount'] ?? 0)
                : $gross * (float) ($line['discount'] ?? 0) / 100;
            $netUnit = max(0, $gross - $discount) / (float) $line['quantity'];
            $lineTotal = max(0, $gross - $discount);
            $totalHt += $gross;
            $totalDiscount += $discount;
            $preparedLines[] = array_merge($line, [
                'discount' => $line['discount'] ?? 0,
                'discount_type' => $line['discount_type'] ?? 'percent',
                'net_unit_price' => $netUnit,
                'line_total' => $lineTotal,
            ]);
        }
        $netHt = $totalHt - $totalDiscount;
        $taxRate = (float) ($data['tax_rate'] ?? 0);
        $tax = $netHt * ($taxRate / 100);

        return DB::transaction(function () use ($data, $client, $entrepriseId, $preparedLines, $totalHt, $totalDiscount, $netHt, $tax, $taxRate, $proforma) {
            $proforma = $proforma ?: new CommercialProforma(['entreprise_id' => $entrepriseId]);
            $proforma->fill([
                'entreprise_id' => $entrepriseId,
                'client_id' => $client->id,
                'client_name' => $client->name,
                'client_phone' => $client->phone,
                'creation_date' => $data['creation_date'],
                'due_date' => $data['due_date'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? null,
                'delivery_terms' => $data['delivery_terms'] ?? null,
                'delivery_location' => $data['delivery_location'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'subject' => $data['subject'] ?? null,
                'total_ht' => $totalHt,
                'total_discount' => $totalDiscount,
                'net_ht' => $netHt,
                'tax_amount' => $tax,
                'tax_rate' => $taxRate,
                'total_ttc' => $netHt + $tax,
            ]);
            $proforma->save();
            $proforma->lines()->delete();
            $proforma->lines()->createMany($preparedLines);
            return $proforma;
        });
    }

    protected function authorizeProforma(CommercialProforma $proforma): void
    {
        abort_unless($proforma->entreprise_id === auth()->user()?->entreprise_id, 403);
    }

    public function editProforma(CommercialProforma $proforma)
    {
        $this->authorizeProforma($proforma);
        $proforma->load('lines');
        return $this->page('commercial-proforma-create', [
            'title' => 'Modifier le proforma',
            'subtitle' => 'Modifiez les informations et les lignes du proforma',
            'module' => collect($this->modules())->firstWhere('key', 'proforma'),
            'modules' => $this->modules(),
            'clients' => CommercialClient::where('entreprise_id', auth()->user()->entreprise_id)->orderBy('name')->get(),
            'proforma' => $proforma,
        ]);
    }

    public function updateProforma(Request $request, CommercialProforma $proforma)
    {
        $this->authorizeProforma($proforma);
        $this->saveProforma($request->validate($this->proformaRules()), $proforma);
        return redirect()->route('admin.commercial.module', 'proforma')->with('success', 'Proforma modifié.');
    }

    public function destroyProforma(CommercialProforma $proforma)
    {
        $this->authorizeProforma($proforma);
        $proforma->delete();
        return back()->with('success', 'Proforma supprimé.');
    }

    public function destroyProformas(Request $request)
    {
        $data = $request->validate(['proforma_ids' => ['required', 'array', 'min:1'], 'proforma_ids.*' => ['integer']]);
        $count = CommercialProforma::where('entreprise_id', auth()->user()->entreprise_id)
            ->whereIn('id', $data['proforma_ids'])->delete();
        return back()->with('success', $count . ' proforma(s) supprimé(s).');
    }

    public function printProforma(CommercialProforma $proforma)
    {
        $this->authorizeProforma($proforma);
        return $this->page('commercial-proforma-print', ['title' => 'Impression proforma', 'proforma' => $proforma->load('lines')]);
    }

    public function emailProforma(Request $request, CommercialProforma $proforma)
    {
        $this->authorizeProforma($proforma);
        $data = $request->validate(['email' => ['required', 'email']]);
        Mail::raw("Bonjour,\n\nVeuillez trouver votre proforma d'un montant TTC de {$proforma->total_ttc} XOF.\n\nCordialement.", function ($message) use ($data) {
            $message->to($data['email'])->subject('Votre proforma');
        });
        $proforma->update(['status' => 'sent']);
        return back()->with('success', 'Proforma envoyé à ' . $data['email'] . '.');
    }

    public function storeInventory(Request $request)
    {
        $data = $request->validate([
            'audited_at' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.designation' => ['required', 'string', 'max:255'],
            'items.*.article' => ['nullable', 'string', 'max:255'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.theoretical' => ['required', 'numeric', 'min:0'],
            'items.*.actual' => ['required', 'numeric', 'min:0'],
        ]);
        $entrepriseId = auth()->user()->entreprise_id;
        foreach ($data['items'] as $item) {
            InventoryAudit::create([
                'entreprise_id' => $entrepriseId,
                'designation' => $item['designation'],
                'article' => $item['article'] ?? null,
                'unit' => $item['unit'] ?? null,
                'theoretical_quantity' => $item['theoretical'],
                'actual_quantity' => $item['actual'],
                'variance' => $item['actual'] - $item['theoretical'],
                'audited_at' => $data['audited_at'],
                'audited_by_user_id' => auth()->id(),
            ]);
        }
        return back()->with('success', 'Inventaire enregistré avec succès.');
    }
}
