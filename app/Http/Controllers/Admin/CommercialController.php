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
use App\Services\AccountingPoster;
use App\Services\DocumentNumberService;
use App\Services\FneCertificationService;
use App\Services\InvoiceIntegrityService;
use App\Services\PaymentRecorder;
use App\Services\TaxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CommercialController extends AdminController
{
    public function modules(): array
    {
        return [
            ['key' => 'tableau', 'title' => 'Tableau commercial', 'description' => 'Pilotage des ventes, clients, encaissements et performances commerciales', 'icon' => 'bi-speedometer2', 'color' => 'blue'],
            ['key' => 'proforma', 'title' => 'Proforma', 'description' => 'Préparer et suivre les factures proforma', 'icon' => 'bi-file-earmark-richtext', 'color' => 'purple'],
            ['key' => 'devis', 'title' => 'Devis', 'description' => 'Créer et suivre les offres commerciales', 'icon' => 'bi-file-earmark-text', 'color' => 'cyan'],
            ['key' => 'facture-personnalisee', 'title' => 'Facture personnalisée', 'description' => 'Créer une facture sur mesure avec les caractéristiques du document, les services et les montants.', 'icon' => 'bi-receipt-cutoff', 'color' => 'pink'],
            ['key' => 'commandes', 'title' => 'Bons de commande', 'description' => 'Enregistrer et suivre les commandes', 'icon' => 'bi-basket', 'color' => 'indigo'],
            ['key' => 'livraisons', 'title' => 'Bons de livraison', 'description' => 'Préparer et contrôler les livraisons', 'icon' => 'bi-truck', 'color' => 'orange'],
            ['key' => 'factures', 'title' => 'Factures', 'description' => 'Suivre les factures issues des livraisons complètes', 'icon' => 'bi-receipt', 'color' => 'red'],
            ['key' => 'services', 'title' => 'Mes services', 'description' => 'Gérer le catalogue des services', 'icon' => 'bi-list-check', 'color' => 'teal'],
            ['key' => 'point-de-vente', 'title' => 'Point de vente', 'description' => 'Accéder à la vente rapide', 'icon' => 'bi-shop', 'color' => 'red'],
            ['key' => 'clients', 'title' => 'Clients', 'description' => 'Gérer les fiches et contacts clients', 'icon' => 'bi-people', 'color' => 'blue'],
            ['key' => 'fournisseurs', 'title' => 'Fournisseurs', 'description' => 'Gérer les partenaires fournisseurs', 'icon' => 'bi-truck-front', 'color' => 'teal'],
            ['key' => 'objectifs', 'title' => 'Objectifs commerciaux', 'description' => 'Suivre les objectifs et réalisations', 'icon' => 'bi-bullseye', 'color' => 'green'],
            ['key' => 'entrees-stock', 'title' => 'Entrées de stock', 'description' => 'Enregistrer les réceptions de stock', 'icon' => 'bi-box-arrow-in-down', 'color' => 'cyan'],
            ['key' => 'sorties-stock', 'title' => 'Sorties de stock', 'description' => 'Enregistrer les sorties et ventes', 'icon' => 'bi-box-arrow-up', 'color' => 'orange'],
            ['key' => 'etat-stock', 'title' => 'État de stock', 'description' => 'Consulter les quantités disponibles', 'icon' => 'bi-boxes', 'color' => 'purple'],
            ['key' => 'inventaire', 'title' => 'Inventaire', 'description' => 'Contrôler et valoriser le stock', 'icon' => 'bi-clipboard-check', 'color' => 'indigo'],
        ];
    }

    public function tableau(Request $request)
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $today = now();
        // Période : du 1er janvier à aujourd'hui par défaut. Une période
        // incohérente est signalée et remplacée par celle par défaut.
        $periodError = null;
        try {
            $from = Carbon::parse($request->input('date_debut', $today->copy()->startOfYear()->toDateString()))->startOfDay();
            $to = Carbon::parse($request->input('date_fin', $today->toDateString()))->endOfDay();
        } catch (\Throwable) {
            $from = $to = null;
        }
        if (! $from || $from->gt($to)) {
            $periodError = 'La période choisie est invalide : la date de début doit précéder la date de fin. Affichage depuis le 1er janvier.';
            $from = $today->copy()->startOfYear();
            $to = $today->copy()->endOfDay();
        }
        // Période précédente de même durée, pour la variation du chiffre d'affaires.
        $days = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
        $previousTo = $from->copy()->subDay()->endOfDay();
        $previousFrom = $previousTo->copy()->subDays($days - 1)->startOfDay();

        $sales = app(\App\Services\SalesRevenueService::class);
        $rows = $sales->rows($entrepriseId, $from, $to);
        $revenue = round((float) $rows->sum('amount'), 2);
        $previousRevenue = round((float) $sales->rows($entrepriseId, $previousFrom, $previousTo)->sum('amount'), 2);
        $collections = $sales->collections($entrepriseId, $from, $to);
        $receivables = $sales->receivables($entrepriseId);

        // Chiffre d'affaires mois par mois (24 mois au plus, les plus récents).
        $cursor = $from->copy()->startOfMonth()->max($to->copy()->startOfMonth()->subMonths(23));
        $months = collect();
        while ($cursor->lte($to)) {
            $inMonth = $rows->filter(fn ($row) => $row['date']->isSameMonth($cursor));
            $months->push(['label' => ucfirst($cursor->translatedFormat($from->year === $to->year ? 'M' : 'M y'))]
                + collect(\App\Services\SalesRevenueService::SOURCES)->map(fn ($label, $source) => round((float) $inMonth->where('source', $source)->sum('amount'), 2))->all());
            $cursor->addMonth();
        }

        $users = \App\Models\User::whereIn('id', $rows->pluck('user_id')->filter()->unique())->pluck('name', 'id');
        $sellers = $rows->groupBy(fn ($row) => $row['user_id'] ?? ($row['source'] === 'pos' ? 'pos' : 'none'))
            ->map(fn ($items, $key) => [
                'name' => $key === 'pos' ? 'Ventes au comptoir' : ($key === 'none' ? 'Vendeur non renseigné' : ($users[$key] ?? 'Utilisateur supprimé')),
                'hint' => $key === 'pos' ? 'la caisse n’enregistre pas de vendeur' : $items->where('source', '!=', 'credit_note')->count() . ' document(s)',
                'amount' => round((float) $items->sum('amount'), 2),
                'is_pos' => $key === 'pos',
            ])->sortByDesc('amount')->values();
        $clients = $rows->reject(fn ($row) => $row['source'] === 'pos' && $row['client'] === 'Client comptoir')
            ->groupBy(fn ($row) => mb_strtolower($row['client']))
            ->map(fn ($items) => ['name' => $items->first()['client'], 'amount' => round((float) $items->sum('amount'), 2), 'documents' => $items->where('source', '!=', 'credit_note')->count()])
            ->filter(fn ($client) => $client['amount'] > 0)->sortByDesc('amount')->values();

        $quotes = CommercialQuote::where('entreprise_id', $entrepriseId)->get(['id', 'reference', 'client_name', 'quote_date', 'due_date', 'net_ht', 'total_ht', 'status']);
        $pendingQuotes = $quotes->filter(fn ($quote) => $quote->status === 'pending_validation' && (! $quote->due_date || $quote->due_date->gte($today->copy()->startOfDay())))
            ->sortBy('due_date')->values();
        $periodQuotes = $quotes->filter(fn ($quote) => $quote->quote_date?->between($from, $to));
        $objective = CommercialObjective::where('entreprise_id', $entrepriseId)->whereYear('objective_date', $to->year)
            ->with('assignments.employee')->orderByDesc('id')->first();

        return $this->page('commercial-tableau', [
            'title' => 'Tableau commercial',
            'periodFrom' => $from->toDateString(),
            'periodTo' => $to->toDateString(),
            'periodError' => $periodError,
            'previousLabel' => $previousFrom->format('d/m/Y') . ' au ' . $previousTo->format('d/m/Y'),
            'revenue' => $revenue,
            'revenueTrend' => $previousRevenue > 0 ? ($revenue - $previousRevenue) / $previousRevenue * 100 : null,
            'rows' => $rows,
            'collections' => $collections,
            'receivables' => $receivables,
            'pendingQuotes' => $pendingQuotes,
            'conversion' => $periodQuotes->count() > 0 ? $periodQuotes->where('status', 'validated')->count() / $periodQuotes->count() * 100 : null,
            'periodQuotes' => $periodQuotes->count(),
            'months' => $months,
            'sellers' => $sellers,
            'clients' => $clients->take(6),
            'items' => $sales->soldItems($entrepriseId, $from, $to)->take(8),
            'objective' => $objective,
            'objectiveProgress' => $objective ? $sales->objectiveProgress($objective) : null,
            'activity' => [
                ['Devis émis', $periodQuotes->count(), 'bi-file-earmark-text', 'blue', route('admin.commercial.module', 'devis')],
                ['Devis validés', $periodQuotes->where('status', 'validated')->count(), 'bi-check2-circle', 'green', route('admin.commercial.module', 'devis')],
                ['Livraisons à valider', CommercialDelivery::where('entreprise_id', $entrepriseId)->where('status', 'pending_validation')->count(), 'bi-truck', 'orange', route('admin.commercial.module', 'livraisons')],
                ['Nouveaux clients', CommercialClient::where('entreprise_id', $entrepriseId)->whereBetween('created_at', [$from, $to])->count(), 'bi-person-plus', 'cyan', route('admin.commercial.module', 'clients')],
            ],
            'stockWatch' => app(\App\Services\StockService::class)->status($entrepriseId)->whereIn('state', ['out', 'low'])->sortBy('available')->values(),
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
        if ($module === 'commandes') {
            return $this->orders($definition);
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

        // Le tableau commercial a sa propre route ; toute autre rubrique du menu
        // possède désormais son écran, il n'y a plus de page d'attente.
        if ($module === 'tableau') {
            return redirect()->route('admin.commercial.tableau');
        }

        abort(404);
    }

    public function customInvoice(?array $definition = null)
    {
        $definition = $definition ?? [
            'key' => 'facture-personnalisee',
            'title' => 'Facture personnalisée',
            'description' => 'Créer une facture sur mesure avec les caractéristiques du document, les services et les montants.',
        ];

        $invoices = CustomInvoice::where('entreprise_id', auth()->user()->entreprise_id)->latest()->get();

        $id = auth()->user()->entreprise_id;

        return $this->page('commercial-custom-invoice-list', [
            'title' => $definition['title'],
            'subtitle' => $definition['description'],
            'module' => $definition,
            'invoices' => $invoices,
            'modules' => $this->modules(),
            'cashAccounts' => CashAccount::where('entreprise_id', $id)->where('is_active', true)->orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('entreprise_id', $id)->orderBy('name')->get(),
        ]);
    }

    public function createCustomInvoice()
    {
        $id = auth()->user()->entreprise_id;
        // Les vrais clients de l'entreprise : la liste était écrite en dur.
        $clients = CommercialClient::where('entreprise_id', $id)->orderBy('name')->get(['id', 'name', 'phone', 'email']);

        return $this->page('commercial-custom-invoice', [
            'title' => 'Créer une facture personnalisée',
            'subtitle' => 'Renseignez les informations du client, les articles, les services et le montant total.',
            'module' => ['key' => 'facture-personnalisee', 'title' => 'Facture personnalisée'],
            'clients' => $clients,
            'cashAccounts' => CashAccount::where('entreprise_id', $id)->where('is_active', true)->orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('entreprise_id', $id)->orderBy('name')->get(),
            'taxRates' => app(TaxService::class)->ratesFor($id),
            'modules' => $this->modules(),
            'globalServiceCatalog' => $this->globalServiceCatalog(),
            'invoice' => null,
        ]);
    }

    public function showCustomInvoice(CustomInvoice $invoice)
    {
        $this->authorizeWorkflow($invoice);

        $integrity = app(InvoiceIntegrityService::class);
        $id = auth()->user()->entreprise_id;
        $invoice->load(['payments', 'creditNotes', 'creator']);

        return $this->page('commercial-custom-invoice-show', [
            'title' => 'Facture personnalisée',
            'subtitle' => 'Détail de la facture',
            'module' => ['key' => 'facture-personnalisee', 'title' => 'Facture personnalisée'],
            'invoice' => $invoice,
            'modules' => $this->modules(),
            'locked' => $integrity->isLocked($invoice),
            'lockReason' => $integrity->lockReason($invoice),
            'creditableAmount' => $integrity->creditableAmount($invoice),
            'cashAccounts' => CashAccount::where('entreprise_id', $id)->where('is_active', true)->orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('entreprise_id', $id)->orderBy('name')->get(),
        ]);
    }

    public function editCustomInvoice(CustomInvoice $invoice)
    {
        $this->authorizeWorkflow($invoice);

        $id = auth()->user()->entreprise_id;
        // Les vrais clients de l'entreprise : la liste était écrite en dur.
        $clients = CommercialClient::where('entreprise_id', $id)->orderBy('name')->get(['id', 'name', 'phone', 'email']);

        return $this->page('commercial-custom-invoice', [
            'title' => 'Modifier la facture personnalisée',
            'subtitle' => 'Mettez à jour les informations de la facture.',
            'module' => ['key' => 'facture-personnalisee', 'title' => 'Facture personnalisée'],
            'clients' => $clients,
            'cashAccounts' => CashAccount::where('entreprise_id', $id)->where('is_active', true)->orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('entreprise_id', $id)->orderBy('name')->get(),
            'taxRates' => app(TaxService::class)->ratesFor($id),
            'modules' => $this->modules(),
            'globalServiceCatalog' => $this->globalServiceCatalog(),
            'invoice' => $invoice,
        ]);
    }

    public function storeCustomInvoice(Request $request)
    {
        $validated = $this->validateCustomInvoice($request);

        $items = $this->customInvoiceItems($validated['items'] ?? []);

        // Le catalogue et ses tarifs viennent de la base, par entreprise :
        // ils ne peuvent plus etre ceux d'un seul client ecrits dans le code.
        $serviceCatalog = $this->globalServiceCatalog();
        $services = collect($request->input('global_services', []))
            ->map(fn ($serviceKey) => [
                'key' => $serviceKey,
                'label' => $serviceCatalog[$serviceKey]['label'] ?? ucwords(str_replace('_', ' ', $serviceKey)),
                'price' => $serviceCatalog[$serviceKey]['price'] ?? 0,
            ])->values()->all();

        $subtotal = collect($items)->sum(fn ($item) => ((float) ($item['quantity'] ?? 0)) * ((float) ($item['price'] ?? 0)));
        $servicesTotal = collect($services)->sum(fn ($service) => (float) ($service['price'] ?? 0));
        $baseAmount = $subtotal + $servicesTotal;
        $discountType = $validated['discount_type'] ?? 'none';
        $discountValue = (float) ($validated['discount_value'] ?? 0);
        $discountAmount = $discountType === 'percent' ? $baseAmount * ($discountValue / 100) : ($discountType === 'amount' ? $discountValue : 0);
        $netAfterDiscount = max(0, $baseAmount - $discountAmount);

        // Le taux vient du parametrage de l'entreprise, jamais du code.
        $taxService = app(TaxService::class);
        $entreprise = auth()->user()->entreprise;
        $currency = $taxService->currencyFor($entreprise);
        $taxRate = $taxService->resolveRate(
            auth()->user()->entreprise_id,
            $request->input('tax_rate_id'),
            $validated['quote_date'] ?? null
        );
        $taxBreakdown = $taxService->breakdown($netAfterDiscount, $taxRate, $currency);
        $vat = $taxBreakdown['tax_amount'];
        $ttc = $taxBreakdown['total_ttc'];

        [$clientName, $clientPhone, $clientEmail] = $this->customInvoiceClient($validated);

        // Numero attribue par le compteur : deux validations simultanees ne
        // peuvent plus obtenir la meme reference.
        $reference = app(DocumentNumberService::class)->next(auth()->user()->entreprise_id, 'custom_invoice');

        $paidAmountInput = min((float) ($validated['paid_amount'] ?? 0), $ttc);
        $paymentMethod = $validated['payment_method'] ?? null;
        $channel = $validated['payment_channel'] ?? null;
        $cashAccountId = $channel === 'cash' ? ($validated['cash_account_id'] ?? null) : null;

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
            'bank_account_id' => $channel === 'bank' ? ($validated['bank_account_id'] ?? null) : null,
            'cash_payment_method' => $validated['cash_payment_method'] ?? 'caisse_principale',
            'subject' => $validated['objet'] ?? null,
            'items' => $items,
            'global_services' => $services,
            'total_ht' => $baseAmount,
            'total_discount' => $discountAmount,
            'subtotal_after_discount' => $netAfterDiscount,
            'tax_amount' => $vat,
            'tax_rate_id' => $taxBreakdown['rate_id'],
            'tax_rate' => $taxBreakdown['rate_value'],
            'tax_regime' => $taxBreakdown['regime'],
            'total_ttc' => $ttc,
            'paid_amount' => $paidAmountInput,
            'paid_at' => $paidAmountInput > 0 ? now() : null,
            'status' => $paidAmountInput >= $ttc ? 'paid' : ($paidAmountInput > 0 ? 'draft' : 'draft'),
        ]);

        if ($paidAmountInput > 0) {
            $this->collectCustomInvoicePayment($invoice, $paidAmountInput, $channel, $cashAccountId, $validated['bank_account_id'] ?? null, 'Paiement immédiat facture personnalisée');
        }

        $request->session()->flash('success', 'La facture personnalisée a bien été enregistrée.');

        return redirect()->route('admin.commercial.custom-invoice.show', $invoice);
    }

    public function updateCustomInvoice(Request $request, CustomInvoice $invoice)
    {
        $this->authorizeWorkflow($invoice);

        try {
            app(InvoiceIntegrityService::class)->assertModifiable($invoice);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        $validated = $this->validateCustomInvoice($request);

        $items = $this->customInvoiceItems($validated['items'] ?? []);

        // Le catalogue et ses tarifs viennent de la base, par entreprise :
        // ils ne peuvent plus etre ceux d'un seul client ecrits dans le code.
        $serviceCatalog = $this->globalServiceCatalog();
        $services = collect($request->input('global_services', []))
            ->map(fn ($serviceKey) => [
                'key' => $serviceKey,
                'label' => $serviceCatalog[$serviceKey]['label'] ?? ucwords(str_replace('_', ' ', $serviceKey)),
                'price' => $serviceCatalog[$serviceKey]['price'] ?? 0,
            ])->values()->all();

        $subtotal = collect($items)->sum(fn ($item) => ((float) ($item['quantity'] ?? 0)) * ((float) ($item['price'] ?? 0)));
        $servicesTotal = collect($services)->sum(fn ($service) => (float) ($service['price'] ?? 0));
        $baseAmount = $subtotal + $servicesTotal;
        $discountType = $validated['discount_type'] ?? 'none';
        $discountValue = (float) ($validated['discount_value'] ?? 0);
        $discountAmount = $discountType === 'percent' ? $baseAmount * ($discountValue / 100) : ($discountType === 'amount' ? $discountValue : 0);
        $netAfterDiscount = max(0, $baseAmount - $discountAmount);

        // Le taux vient du parametrage de l'entreprise, jamais du code.
        $taxService = app(TaxService::class);
        $entreprise = auth()->user()->entreprise;
        $currency = $taxService->currencyFor($entreprise);
        $taxRate = $taxService->resolveRate(
            auth()->user()->entreprise_id,
            $request->input('tax_rate_id'),
            $validated['quote_date'] ?? null
        );
        $taxBreakdown = $taxService->breakdown($netAfterDiscount, $taxRate, $currency);
        $vat = $taxBreakdown['tax_amount'];
        $ttc = $taxBreakdown['total_ttc'];

        // Une facture modifiable n'a encore reçu aucun paiement.
        $previousPaidAmount = (float) ($invoice->paid_amount ?? 0);
        $paidAmountInput = min($request->filled('paid_amount') ? (float) $validated['paid_amount'] : $previousPaidAmount, $ttc);
        $paymentMethod = $validated['payment_method'] ?? $invoice->payment_method;
        $channel = $validated['payment_channel'] ?? null;
        $cashAccountId = $channel === 'cash' ? ($validated['cash_account_id'] ?? null) : ($invoice->cash_account_id ?? null);
        [$clientName, $clientPhone, $clientEmail] = $this->customInvoiceClient($validated, $invoice);

        $invoice->update([
            'client_name' => $clientName,
            'client_phone' => $clientPhone,
            'client_email' => $clientEmail,
            'quote_date' => $validated['quote_date'] ?? $invoice->quote_date,
            'valid_until' => $validated['valid_until'] ?? $invoice->valid_until,
            'payment_terms' => $validated['payment_terms'] ?? $invoice->payment_terms,
            'delivery_terms' => $validated['delivery_terms'] ?? $invoice->delivery_terms,
            'delivery_location' => $validated['delivery_location'] ?? $invoice->delivery_location,
            'payment_method' => $paymentMethod,
            'cash_account_id' => $cashAccountId,
            'bank_account_id' => $channel === 'bank' ? ($validated['bank_account_id'] ?? null) : ($invoice->bank_account_id ?? null),
            'cash_payment_method' => $validated['cash_payment_method'] ?? $invoice->cash_payment_method,
            'subject' => $validated['objet'] ?? $invoice->subject,
            'items' => $items,
            'global_services' => $services,
            'total_ht' => $baseAmount,
            'total_discount' => $discountAmount,
            'subtotal_after_discount' => $netAfterDiscount,
            'tax_amount' => $vat,
            'tax_rate_id' => $taxBreakdown['rate_id'],
            'tax_rate' => $taxBreakdown['rate_value'],
            'tax_regime' => $taxBreakdown['regime'],
            'total_ttc' => $ttc,
            'paid_amount' => $paidAmountInput,
            'paid_at' => $paidAmountInput > 0 ? ($invoice->paid_at ?? now()) : null,
            'status' => $paidAmountInput >= $ttc ? 'paid' : ($paidAmountInput > 0 ? 'draft' : $invoice->status),
        ]);

        $complement = max(0, $paidAmountInput - $previousPaidAmount);
        if ($complement > 0) {
            $this->collectCustomInvoicePayment($invoice, $complement, $channel, $cashAccountId, $validated['bank_account_id'] ?? null, 'Paiement facture personnalisée');
        }

        return redirect()->route('admin.commercial.custom-invoice.show', $invoice)->with('success', 'La facture personnalisée a bien été mise à jour.');
    }

    /**
     * Prestations forfaitaires proposees sur la facture personnalisee.
     * Chaque entreprise gere les siennes depuis le module Services.
     */
    protected function globalServiceCatalog(): array
    {
        return CommercialService::where('entreprise_id', auth()->user()->entreprise_id)
            ->where('is_global', true)->where('is_active', true)
            ->whereNotNull('code')->orderBy('name')
            ->get()
            ->mapWithKeys(fn (CommercialService $service) => [
                $service->code => ['label' => $service->name, 'price' => (float) $service->price],
            ])->all();
    }

    public function destroyCustomInvoice(CustomInvoice $invoice)
    {
        $this->authorizeWorkflow($invoice);

        try {
            app(InvoiceIntegrityService::class)->assertModifiable($invoice);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        $invoice->delete();

        return redirect()->route('admin.commercial.custom-invoice.index')->with('success', 'La facture personnalisée a été supprimée.');
    }

    /**
     * Emet la facture : elle devient un document opposable et se fige.
     * Toute correction passera desormais par un avoir.
     */
    public function issueCustomInvoice(CustomInvoice $invoice)
    {
        $this->authorizeWorkflow($invoice);

        try {
            app(InvoiceIntegrityService::class)->issue($invoice);
            app(AccountingPoster::class)->postCustomInvoice($invoice->refresh(), auth()->id());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return back()->with('success', 'Facture émise. Elle ne peut plus être modifiée : utilisez un avoir pour la corriger.');
    }

    /** Emet un avoir, total ou partiel, sur une facture deja emise. */
    public function creditCustomInvoice(Request $request, CustomInvoice $invoice)
    {
        $this->authorizeWorkflow($invoice);

        $integrity = app(InvoiceIntegrityService::class);

        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'full' => ['nullable', 'boolean'],
        ], [
            'amount.*' => 'Le montant de l’avoir doit être un nombre supérieur à zéro.',
            'reason.required' => 'Indiquez le motif de l’avoir.',
            'reason.min' => 'Le motif doit contenir au moins 5 caractères.',
            'reason.max' => 'Le motif ne doit pas dépasser 500 caractères.',
        ]);

        $amount = ! empty($data['full'])
            ? $integrity->creditableAmount($invoice)
            : (float) ($data['amount'] ?? 0);

        try {
            $note = $integrity->credit($invoice, $amount, $data['reason'], auth()->id());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['credit' => $e->getMessage()]);
        }

        return back()->with('success', "Avoir {$note->reference} émis pour un montant de " . number_format((float) $note->total_ttc, 2, ',', ' ') . '.');
    }

    public function duplicateCustomInvoice(CustomInvoice $invoice)
    {
        $this->authorizeWorkflow($invoice);

        // La copie est un nouveau brouillon daté du jour : ni émission, ni avoir,
        // ni paiement de l'original ne la suivent.
        $delay = $invoice->quote_date && $invoice->valid_until ? max(0, (int) $invoice->quote_date->diffInDays($invoice->valid_until, false)) : 30;
        $clone = $invoice->replicate();
        $clone->forceFill([
            'reference' => app(DocumentNumberService::class)->next(auth()->user()->entreprise_id, 'custom_invoice'),
            'created_by_user_id' => auth()->id(),
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays($delay)->toDateString(),
            'status' => 'draft',
            'issued_at' => null,
            'credited_amount' => 0,
            'paid_amount' => 0,
            'paid_at' => null,
            'cash_account_id' => null,
            'bank_account_id' => null,
        ])->save();

        return redirect()->route('admin.commercial.custom-invoice.show', $clone)->with('success', 'La facture personnalisée a été dupliquée.');
    }

    public function emailCustomInvoice(CustomInvoice $invoice)
    {
        $this->authorizeWorkflow($invoice);

        // Le bouton annonçait « envoyée » sans rien envoyer.
        if (! filter_var($invoice->client_email, FILTER_VALIDATE_EMAIL)) {
            return back()->withErrors(['email' => 'Le client de cette facture n’a pas d’adresse e-mail valide : renseignez-la en modifiant la facture.']);
        }

        Mail::raw($this->customInvoiceMessage($invoice) . "\n\nCordialement.", function ($message) use ($invoice) {
            $message->to($invoice->client_email)->subject('Votre facture ' . $invoice->reference);
        });

        return back()->with('success', 'Facture envoyée par e-mail à ' . $invoice->client_email . '.');
    }

    /** Message au client : montant, état et reste à payer réels, dans la devise de l'entreprise. */
    protected function customInvoiceMessage(CustomInvoice $invoice): string
    {
        $state = $invoice->state();
        $message = 'Bonjour ' . ($invoice->client_name ?: 'Madame, Monsieur') . ",\n\n"
            . 'Voici votre facture ' . $invoice->reference . ' d’un montant de ' . money((float) $invoice->total_ttc) . ".\n"
            . 'Statut : ' . CustomInvoice::states()[$state][0] . '.';
        if (in_array($state, ['issued', 'partial'], true)) {
            $message .= "\nReste à payer : " . money($invoice->remainingAmount()) . '.';
        }

        return $message;
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

        $message = $this->customInvoiceMessage($invoice) . "\n\nMerci pour votre confiance.";

        return redirect()->away('https://wa.me/' . $whatsappNumber . '?text=' . rawurlencode($message));
    }

    public function printCustomInvoice(CustomInvoice $invoice)
    {
        $this->authorizeWorkflow($invoice);

        return view('admin.commercial-custom-invoice-print', [
            'invoice' => $invoice->load('creditNotes'),
            'company' => auth()->user()->entreprise,
        ]);
    }

    /** Règles communes à la création et à la modification d'une facture personnalisée. */
    protected function validateCustomInvoice(Request $request): array
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
            // Mode de règlement prévu au contrat (Espèces, Virement…), affiché sur la facture.
            'payment_method' => ['nullable', 'string', 'max:100'],
            // Encaissement immédiat : caisse ou banque, et le compte qui reçoit l'argent.
            'payment_channel' => ['nullable', 'in:cash,bank'],
            'cash_account_id' => ['nullable', 'integer'],
            'bank_account_id' => ['nullable', 'integer'],
            'cash_payment_method' => ['nullable', 'string', 'max:100'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'objet' => ['nullable', 'string', 'max:255'],
            'global_services' => ['nullable', 'array'],
            'items' => ['nullable', 'array'],
            'discount_type' => ['nullable', 'in:none,percent,amount'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'tax_rate_id' => ['nullable', 'integer'],
        ]);

        // Un montant payé doit dire où l'argent est entré, sinon il n'apparaît dans aucune trésorerie.
        if ((float) ($validated['paid_amount'] ?? 0) > 0) {
            $channel = $validated['payment_channel'] ?? null;
            if (! $channel) {
                throw \Illuminate\Validation\ValidationException::withMessages(['payment_channel' => 'Indiquez si le montant payé a été reçu en caisse ou en banque.']);
            }
            $account = $channel === 'cash' ? 'cash_account_id' : 'bank_account_id';
            if (empty($validated[$account])) {
                throw \Illuminate\Validation\ValidationException::withMessages([$account => $channel === 'cash' ? 'Choisissez la caisse qui reçoit le paiement.' : 'Choisissez le compte bancaire qui reçoit le paiement.']);
            }
        }

        return $validated;
    }

    /** Lignes d'une facture personnalisée, avec leurs caractéristiques techniques. */
    protected function customInvoiceItems(array $items): array
    {
        return collect($items)->filter(fn ($item) => is_array($item) && trim((string) ($item['item_name'] ?? '')) !== '')->map(fn ($item) => [
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
    }

    /**
     * Client d'une facture personnalisée : un client du carnet, ou une saisie libre.
     *
     * @return array{0: string, 1: ?string, 2: ?string} nom, téléphone, e-mail
     */
    protected function customInvoiceClient(array $validated, ?CustomInvoice $invoice = null): array
    {
        if (ctype_digit((string) ($validated['customer'] ?? ''))) {
            $client = CommercialClient::where('entreprise_id', auth()->user()->entreprise_id)->find((int) $validated['customer']);
            if ($client) {
                return [$client->name, $client->phone, $client->email];
            }
        }

        return [
            $validated['new_client_name'] ?? $invoice?->client_name ?? 'Client',
            $validated['new_client_phone'] ?? $invoice?->client_phone,
            $validated['new_client_email'] ?? $invoice?->client_email,
        ];
    }

    /**
     * Encaissement d'une facture personnalisée : l'argent entre en caisse ou en
     * banque, le règlement est daté (TVA sur les encaissements) et la créance
     * client est soldée au journal.
     */
    protected function collectCustomInvoicePayment(CustomInvoice $invoice, float $amount, ?string $channel, ?int $cashAccountId, ?int $bankAccountId, string $label): void
    {
        if ($amount <= 0) {
            return;
        }
        $channel = $channel === 'bank' ? 'bank' : 'cash';

        DB::transaction(function () use ($invoice, $amount, $channel, $cashAccountId, $bankAccountId, $label) {
            if ($channel === 'cash') {
                $this->registerCustomInvoiceCashPayment($invoice, $amount, 'cash', $cashAccountId, $label);
            } else {
                $this->registerCustomInvoiceBankPayment($invoice, $amount, $bankAccountId);
            }
            app(PaymentRecorder::class)->record($invoice, $amount, $channel, now(), auth()->id());
            app(AccountingPoster::class)->postCustomerPayment($invoice, $amount, $channel, now(), auth()->id());
        });
    }

    /** Encaissement par banque : le compte choisi est crédité, comme pour une facture de vente. */
    protected function registerCustomInvoiceBankPayment(CustomInvoice $invoice, float $amount, ?int $bankAccountId): void
    {
        if ($amount <= 0 || empty($bankAccountId)) {
            return;
        }

        $account = BankAccount::where('entreprise_id', $invoice->entreprise_id)->lockForUpdate()->findOrFail($bankAccountId);
        $account->increment('current_balance', $amount);
        $account->update([
            'status' => 'credit',
            'last_transaction_label' => 'Paiement facture ' . $invoice->reference,
            'last_transaction_amount' => $amount,
            'last_transaction_direction' => 'up',
        ]);

        BankTransaction::create([
            'entreprise_id' => $invoice->entreprise_id,
            'bank_account_id' => $account->id,
            'transaction_type' => 'credit',
            'is_transfer' => false,
            'label' => 'Paiement facture ' . $invoice->reference,
            'amount' => $amount,
            'description' => 'Paiement d’une facture personnalisée : ' . $invoice->reference,
            'transaction_date' => now()->toDateString(),
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
            'currency' => company_currency()['code'],
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
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'in:cash,bank'],
            'cash_account_id' => ['required_if:payment_method,cash', 'nullable', 'integer'],
            'bank_account_id' => ['required_if:payment_method,bank', 'nullable', 'integer'],
        ], [
            'amount.*' => 'Le montant reçu doit être un nombre supérieur à zéro.',
            'payment_method.*' => 'Choisissez le mode de paiement : espèces ou banque.',
            'cash_account_id.*' => 'Choisissez la caisse qui reçoit le paiement.',
            'bank_account_id.*' => 'Choisissez le compte bancaire qui reçoit le paiement.',
        ]);

        $previousPaidAmount = (float) ($invoice->paid_amount ?? 0);
        // Le montant dû tient compte des avoirs déjà émis sur la facture.
        $amountDue = max(0, (float) $invoice->total_ttc - (float) ($invoice->credited_amount ?? 0));
        $remainingAmount = max(0, $amountDue - $previousPaidAmount);
        // Une facture soldée, ou entièrement annulée par avoir, ne reçoit plus de paiement.
        if ($remainingAmount <= 0 || app(InvoiceIntegrityService::class)->creditableAmount($invoice) <= 0) {
            return back()->withErrors(['amount' => 'Cette facture ne reçoit plus de paiement : elle est soldée ou annulée par avoir.']);
        }
        $paymentAmount = min((float) $data['amount'], $remainingAmount);
        $newPaidAmount = $previousPaidAmount + $paymentAmount;
        $paymentMethod = $data['payment_method'] ?? $invoice->payment_method ?? 'cash';
        $cashAccountId = $paymentMethod === 'cash' ? ($data['cash_account_id'] ?? null) : null;
        $bankAccountId = $paymentMethod === 'bank' ? ($data['bank_account_id'] ?? null) : null;

        if ($paymentAmount > 0) {
            $this->collectCustomInvoicePayment($invoice, $paymentAmount, $paymentMethod, $cashAccountId, $bankAccountId, 'Paiement facture personnalisée');
        }

        $invoice->update([
            'paid_amount' => $newPaidAmount,
            'paid_at' => $newPaidAmount > 0 ? ($invoice->paid_at ?? now()) : null,
            'cash_account_id' => $cashAccountId,
            'bank_account_id' => $bankAccountId,
            'status' => $newPaidAmount >= $amountDue ? 'paid' : 'draft',
        ]);

        return redirect()->route('admin.commercial.custom-invoice.show', $invoice)->with('success', 'Le paiement a bien été enregistré.');
    }

    protected function inventory(array $definition)
    {
        $entrepriseId = auth()->user()->entreprise_id;

        return $this->page('commercial-inventory', [
            'title' => $definition['title'], 'subtitle' => $definition['description'],
            'module' => $definition, 'modules' => $this->modules(),
            // Le stock théorique est celui de l'instant : lots reçus moins sorties, écarts d'inventaire compris.
            'items' => app(\App\Services\StockService::class)->status($entrepriseId),
            'inventories' => \App\Models\StockInventory::where('entreprise_id', $entrepriseId)->with(['audits', 'countedBy:id,name'])
                ->orderByDesc('inventoried_at')->orderByDesc('id')->get(),
        ]);
    }

    protected function clients(array $definition)
    {
        $id = auth()->user()->entreprise_id;
        return $this->page('commercial-clients', [
            'title' => $definition['title'], 'subtitle' => $definition['description'],
            'module' => $definition, 'modules' => $this->modules(),
            'clients' => CommercialClient::where('entreprise_id', $id)->latest()->get(),
            'activity' => $this->clientActivity($id),
        ]);
    }

    /**
     * Activité de chaque client : factures de ventes (rattachées par le devis
     * d'origine) et ventes comptoir. Les factures personnalisées ne portent que
     * le nom du client : sans identifiant fiable, elles ne sont pas cumulées.
     *
     * @return array<int, array{purchases: float, due: float, last: ?string, invoices: array<int, array>, pos_count: int}>
     */
    protected function clientActivity(int $entrepriseId): array
    {
        $activity = [];
        $empty = ['purchases' => 0.0, 'due' => 0.0, 'last' => null, 'invoices' => [], 'pos_count' => 0];

        $invoices = DB::table('commercial_invoices as i')
            ->join('commercial_deliveries as d', 'd.id', '=', 'i.delivery_id')
            ->join('commercial_orders as o', 'o.id', '=', 'd.order_id')
            ->join('commercial_quotes as q', 'q.id', '=', 'o.quote_id')
            ->where('i.entreprise_id', $entrepriseId)
            ->whereNotNull('q.client_id')
            ->orderByRaw('COALESCE(i.issued_at, i.created_at) DESC')
            ->selectRaw('q.client_id, i.id, i.amount, i.paid_amount, i.status, i.fne_status, COALESCE(i.issued_at, i.created_at) AS issued_on')
            ->get();

        foreach ($invoices as $invoice) {
            $row = &$activity[$invoice->client_id];
            $row ??= $empty;
            $remaining = max(0, (float) $invoice->amount - (float) $invoice->paid_amount);
            if ($invoice->status !== 'cancelled') {
                $row['purchases'] += (float) $invoice->amount;
                $row['due'] += $remaining;
                $row['last'] = max($row['last'] ?? '', substr($invoice->issued_on, 0, 10));
            }
            $row['invoices'][] = [
                'id' => $invoice->id, 'date' => substr($invoice->issued_on, 0, 10), 'amount' => (float) $invoice->amount,
                'remaining' => $invoice->status === 'cancelled' ? 0.0 : $remaining, 'status' => $invoice->status,
                'print' => route('admin.commercial.invoices.print', $invoice->id),
            ];
            unset($row);
        }

        $sales = PosSale::where('entreprise_id', $entrepriseId)->whereNotNull('client_id')->where('status', '!=', 'cancelled')
            ->selectRaw('client_id, COUNT(*) AS sales_count, SUM(total) AS sales_total, MAX(created_at) AS last_sale')
            ->groupBy('client_id')->get();

        foreach ($sales as $sale) {
            $row = &$activity[$sale->client_id];
            $row ??= $empty;
            $row['purchases'] += (float) $sale->sales_total;
            $row['pos_count'] = (int) $sale->sales_count;
            $row['last'] = max($row['last'] ?? '', substr($sale->last_sale, 0, 10));
            unset($row);
        }

        return $activity;
    }

    protected function suppliers(array $definition)
    {
        $id = auth()->user()->entreprise_id;
        $suppliers = CommercialSupplier::where('entreprise_id', $id)->latest()->get();
        [$activity, $unmatched] = $this->supplierActivity($id, $suppliers);

        return $this->page('commercial-suppliers', [
            'title' => $definition['title'], 'subtitle' => $definition['description'],
            'module' => $definition, 'modules' => $this->modules(),
            'suppliers' => $suppliers,
            'activity' => $activity,
            'unmatchedInvoices' => $unmatched,
        ]);
    }

    /**
     * Factures fournisseurs de chaque fournisseur du carnet. Le seul lien
     * fiable est le NCC lu dans le PDF : le nom varie d'une facture à l'autre.
     *
     * @return array{0: array<int, array{purchases: float, invoices: array<int, array>, last: ?string}>, 1: int}
     */
    protected function supplierActivity(int $entrepriseId, \Illuminate\Support\Collection $suppliers): array
    {
        $normalize = fn (?string $ncc) => $ncc ? strtoupper(preg_replace('/\s+/', '', $ncc)) : null;
        $byNcc = $suppliers->filter(fn ($supplier) => $normalize($supplier->tax_id))
            ->mapWithKeys(fn ($supplier) => [$normalize($supplier->tax_id) => $supplier->id]);

        $activity = [];
        $unmatched = 0;
        $invoices = \App\Models\SupplierInvoice::where('entreprise_id', $entrepriseId)
            ->orderByRaw('COALESCE(invoice_date, created_at) DESC')->get();

        foreach ($invoices as $invoice) {
            $supplierId = $byNcc[$normalize($invoice->supplier_tax_id)] ?? null;
            if (! $supplierId) {
                $unmatched++;
                continue;
            }
            $date = ($invoice->invoice_date ?? $invoice->created_at)?->format('Y-m-d');
            $row = &$activity[$supplierId];
            $row ??= ['purchases' => 0.0, 'invoices' => [], 'last' => null];
            $row['purchases'] += (float) $invoice->total_amount;
            $row['last'] = max($row['last'] ?? '', (string) $date);
            $row['invoices'][] = [
                'number' => $invoice->invoice_number, 'date' => $date, 'amount' => $invoice->total_amount !== null ? (float) $invoice->total_amount : null,
                'verified' => $invoice->status === 'imported', 'fne' => $invoice->fne_status === 'certified',
                'url' => route('admin.comptabilite.supplierInvoices.show', $invoice),
            ];
            unset($row);
        }

        return [$activity, $unmatched];
    }

    protected function objectives(array $definition)
    {
        $id = auth()->user()->entreprise_id;
        $objectives = CommercialObjective::where('entreprise_id', $id)
            ->orderByDesc('objective_date')->orderByDesc('id')->get();
        // L'exercice affiché : celui demandé, sinon l'année en cours, sinon le plus récent.
        $selected = $objectives->firstWhere('id', (int) request('objectif'))
            ?? $objectives->first(fn ($objective) => $objective->objective_date->year === now()->year)
            ?? $objectives->first();
        $selected?->load(['assignments' => fn ($query) => $query->orderBy('starts_at')->orderBy('id'), 'assignments.employee']);
        // Un employé parti garde son attribution : il reste proposé dans la fenêtre de modification.
        $assignedIds = $selected?->assignments->pluck('employee_id') ?? collect();

        return $this->page('commercial-objectives', [
            'title' => $definition['title'], 'subtitle' => 'Objectif annuel de chiffre d’affaires HT, réparti entre les commerciaux et suivi au fil des ventes.',
            'module' => $definition, 'modules' => $this->modules(),
            'objectives' => $objectives,
            'objective' => $selected,
            'progress' => $selected ? app(\App\Services\SalesRevenueService::class)->objectiveProgress($selected) : null,
            'employees' => Employee::where('entreprise_id', $id)
                ->where(fn ($query) => $query->where('status', 'active')->orWhereIn('id', $assignedIds))
                ->orderBy('full_name')->get(['id', 'user_id', 'full_name', 'position', 'status']),
        ]);
    }

    protected function stockEntries(array $definition)
    {
        $id = auth()->user()->entreprise_id;
        return $this->page('commercial-stock-entries', [
            'title' => $definition['title'], 'subtitle' => $definition['description'],
            'module' => $definition, 'modules' => $this->modules(),
            // Les plus récentes d'abord, par date de réception (et non de saisie).
            'entries' => StockEntry::where('entreprise_id', $id)->with(['lines', 'supplier:id,name'])->orderByDesc('entry_date')->orderByDesc('id')->get(),
        ]);
    }

    protected function stockStatus(array $definition)
    {
        return $this->page('commercial-stock-status', [
            'title' => $definition['title'], 'subtitle' => 'Quantités disponibles, valeur du stock et fournisseurs de chaque article.',
            'module' => $definition, 'modules' => $this->modules(),
            // Lots et sorties par article : la valeur restante suit le prix d'achat de chaque réception.
            'items' => app(\App\Services\StockService::class)->status(auth()->user()->entreprise_id),
        ]);
    }

    public function createStockEntry()
    {
        $id = auth()->user()->entreprise_id;
        return $this->page('commercial-stock-entry-create', [
            'title' => 'Nouvelle réception',
            'subtitle' => 'Enregistrez les articles reçus, leur fournisseur et leur prix.',
            'module' => collect($this->modules())->firstWhere('key', 'entrees-stock'),
            'modules' => $this->modules(),
            'suppliers' => CommercialSupplier::where('entreprise_id', $id)->orderBy('name')->get(['id', 'name', 'phone']),
            // Articles déjà reçus : suggestions de désignation, avec leur dernier prix.
            'knownArticles' => StockEntryLine::whereHas('stockEntry', fn ($query) => $query->where('entreprise_id', $id))
                ->latest('id')->get(['designation', 'article', 'unit', 'purchase_price', 'profit_per_unit'])
                ->unique(fn ($line) => mb_strtolower($line->designation))->sortBy('designation')->values(),
        ]);
    }

    public function storeStockEntry(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer'],
            'supplier_reference' => ['nullable', 'string', 'max:100'],
            'entry_date' => ['required', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.designation' => ['required', 'string', 'max:190'],
            'lines.*.article' => ['nullable', 'string', 'max:190'],
            'lines.*.unit' => ['nullable', 'string', 'max:50'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.purchase_price' => ['required', 'numeric', 'min:0'],
            'lines.*.profit_per_unit' => ['nullable', 'numeric', 'min:0'],
        ], [
            'supplier_id.required' => 'Choisissez le fournisseur qui a livré la marchandise.',
            'entry_date.required' => 'Indiquez la date de réception.',
            'lines.required' => 'Ajoutez au moins un article reçu.',
            'lines.*.designation.required' => 'Chaque ligne doit avoir une désignation.',
            'lines.*.quantity.gt' => 'La quantité reçue de chaque ligne doit être supérieure à zéro.',
        ]);
        $id = auth()->user()->entreprise_id;
        // Chaque produit reçu doit pouvoir être suivi jusqu'à son fournisseur.
        $supplier = CommercialSupplier::where('entreprise_id', $id)->find($data['supplier_id']);
        if (! $supplier) {
            throw \Illuminate\Validation\ValidationException::withMessages(['supplier_id' => 'Choisissez le fournisseur qui a livré la marchandise.']);
        }
        $prepared = collect($data['lines'])->map(function ($line) {
            $line['profit_per_unit'] = $line['profit_per_unit'] ?? 0;
            $line['total_purchase'] = (float) $line['quantity'] * (float) $line['purchase_price'];
            $line['total_profit'] = (float) $line['quantity'] * (float) $line['profit_per_unit'];
            return $line;
        });
        DB::transaction(function () use ($id, $supplier, $data, $prepared) {
            $entry = StockEntry::create([
                'entreprise_id' => $id,
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'supplier_reference' => $data['supplier_reference'] ?? null,
                'entry_date' => $data['entry_date'],
                'total_purchase' => $prepared->sum('total_purchase'),
                'total_profit' => $prepared->sum('total_profit'),
            ]);
            $entry->lines()->createMany($prepared->all());
        });
        return redirect()->route('admin.commercial.module', 'entrees-stock')
            ->with('success', 'Réception de ' . $supplier->name . ' enregistrée : ' . $prepared->count() . ' article(s), ' . money($prepared->sum('total_purchase')) . '.');
    }

    protected function stockExits(array $definition)
    {
        $id = auth()->user()->entreprise_id;
        return $this->page('commercial-stock-exits', [
            'title' => $definition['title'], 'subtitle' => $definition['description'],
            'module' => $definition, 'modules' => $this->modules(),
            // Chaque ligne sortie garde son lot, donc la réception et le fournisseur d'origine.
            'exits' => StockExit::where('entreprise_id', $id)->with(['lines.entryLine.stockEntry', 'delivery.order'])
                ->orderByDesc('exit_date')->orderByDesc('id')->get(),
        ]);
    }

    public function createStockExit()
    {
        return $this->page('commercial-stock-exit-create', [
            'title' => 'Nouvelle sortie de stock', 'subtitle' => 'Enregistrez les articles sortis du stock et le motif.',
            'module' => collect($this->modules())->firstWhere('key', 'sorties-stock'), 'modules' => $this->modules(),
            'articles' => app(\App\Services\StockService::class)->articles(auth()->user()->entreprise_id)->values(),
        ]);
    }

    public function storeStockExit(Request $request)
    {
        $data = $request->validate([
            'exit_date' => ['required', 'date'],
            'reason' => ['required', 'in:' . implode(',', array_keys(StockExit::reasons()))],
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.article_key' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ], [
            'reason.required' => 'Indiquez le motif de la sortie : où part la marchandise ?',
            'reason.in' => 'Indiquez le motif de la sortie : où part la marchandise ?',
            'lines.required' => 'Ajoutez au moins un article sorti.',
            'lines.*.article_key.required' => 'Choisissez l’article de chaque ligne.',
            'lines.*.quantity.gt' => 'La quantité sortie de chaque ligne doit être supérieure à zéro.',
        ]);
        $id = auth()->user()->entreprise_id;
        $stockService = app(\App\Services\StockService::class);
        $lots = $stockService->lots($id);
        $taken = [];
        $prepared = [];
        foreach ($data['lines'] as $index => $line) {
            $key = $line['article_key'];
            $label = $lots->first(fn ($lot) => \App\Services\StockService::key($lot['line']->designation, $lot['line']->article, $lot['line']->unit) === $key)['line']->designation ?? 'article choisi';
            // Refus lisible sur l'écran de saisie : c'était une page d'erreur 422.
            array_push($prepared, ...$stockService->allocate($lots,
                fn ($stockLine) => \App\Services\StockService::key($stockLine->designation, $stockLine->article, $stockLine->unit) === $key,
                (float) $line['quantity'], $label, "lines.$index.quantity", $taken));
        }
        $total = collect($prepared)->sum('total_value');
        DB::transaction(function () use ($id, $data, $prepared, $total) {
            $exit = StockExit::create(['entreprise_id' => $id, 'reason' => $data['reason'], 'note' => $data['note'] ?? null, 'exit_date' => $data['exit_date'], 'total_value' => $total]);
            $exit->lines()->createMany($prepared);
        });
        return redirect()->route('admin.commercial.module', 'sorties-stock')
            ->with('success', 'Sortie enregistrée (' . mb_strtolower(StockExit::reasons()[$data['reason']]) . ') : ' . count($data['lines']) . ' article(s), ' . money($total) . ' au prix d’achat.');
    }

    private function objectivesPage(CommercialObjective $objective)
    {
        return redirect()->route('admin.commercial.module', ['objectifs', 'objectif' => $objective->id]);
    }

    private function objectiveRules(): array
    {
        return [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
        ];
    }

    private function objectiveMessages(): array
    {
        return [
            'year.required' => 'Choisissez l’exercice de l’objectif.',
            'year.*' => 'L’exercice choisi est invalide.',
            'amount.required' => 'Saisissez le montant de l’objectif.',
            'amount.*' => 'Le montant de l’objectif doit être un nombre supérieur à zéro.',
        ];
    }

    /** Un seul objectif par exercice : deux objectifs 2026 se contrediraient. */
    private function ensureYearIsFree(int $year, ?int $ignoreId = null): void
    {
        $taken = CommercialObjective::where('entreprise_id', auth()->user()->entreprise_id)
            ->whereYear('objective_date', $year)
            ->when($ignoreId, fn ($query) => $query->where('id', '<>', $ignoreId))
            ->exists();
        if ($taken) {
            throw \Illuminate\Validation\ValidationException::withMessages(['year' => "Un objectif existe déjà pour l’exercice {$year} : modifiez-le plutôt que d’en créer un second."]);
        }
    }

    public function storeObjective(Request $request)
    {
        $data = $request->validate($this->objectiveRules(), $this->objectiveMessages());
        $this->ensureYearIsFree((int) $data['year']);
        $objective = CommercialObjective::create([
            'entreprise_id' => auth()->user()->entreprise_id,
            'amount' => $data['amount'],
            'objective_date' => Carbon::create((int) $data['year'], 1, 1)->toDateString(),
        ]);

        return $this->objectivesPage($objective)->with('success', 'Objectif ' . $data['year'] . ' enregistré : ' . money((float) $data['amount']) . ' HT. Répartissez-le maintenant entre vos commerciaux.');
    }

    public function updateObjective(Request $request, CommercialObjective $objective)
    {
        $this->authorizeWorkflow($objective);
        $data = $request->validate($this->objectiveRules(), $this->objectiveMessages());
        $year = (int) $data['year'];
        $this->ensureYearIsFree($year, $objective->id);
        $assigned = (float) $objective->assignments()->sum('amount');
        if ((float) $data['amount'] < $assigned) {
            throw \Illuminate\Validation\ValidationException::withMessages(['amount' => 'L’objectif ne peut pas descendre sous le montant déjà réparti entre les commerciaux (' . money($assigned) . '). Réduisez d’abord leurs attributions.']);
        }
        if ($year !== $objective->objective_date->year && $objective->assignments()->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['year' => 'Cet objectif est déjà réparti entre des commerciaux sur l’exercice ' . $objective->objective_date->year . ' : supprimez leurs attributions avant de changer d’exercice.']);
        }
        $objective->update(['amount' => $data['amount'], 'objective_date' => Carbon::create($year, 1, 1)->toDateString()]);

        return $this->objectivesPage($objective)->with('success', 'Objectif ' . $year . ' modifié : ' . money((float) $data['amount']) . ' HT.');
    }

    public function destroyObjective(CommercialObjective $objective)
    {
        $this->authorizeWorkflow($objective);
        $year = $objective->objective_date->year;
        $objective->delete();

        return redirect()->route('admin.commercial.module', 'objectifs')->with('success', "Objectif {$year} supprimé, avec ses attributions.");
    }

    /**
     * Contrôles communs à l'ajout et à la modification d'attributions : période
     * dans l'exercice, commercial actif (ou déjà attribué), un seul objectif par
     * commercial, et répartition qui ne dépasse pas l'objectif annuel.
     *
     * @param  array<int, array{employee_id: mixed, amount: mixed, starts_at: string, ends_at: string}>  $rows
     */
    private function checkAssignments(CommercialObjective $objective, array $rows, ?CommercialObjectiveAssignment $current = null, string $prefix = ''): void
    {
        $year = $objective->objective_date->year;
        $errors = [];
        $field = fn ($index, string $name) => $prefix === '' ? $name : "{$prefix}.{$index}.{$name}";
        $others = $objective->assignments()->when($current, fn ($query) => $query->where('id', '<>', $current->id))->get();
        $employees = Employee::where('entreprise_id', auth()->user()->entreprise_id)
            ->whereIn('id', collect($rows)->pluck('employee_id')->map(fn ($id) => (int) $id))->get()->keyBy('id');

        foreach ($rows as $index => $row) {
            $employee = $employees->get((int) $row['employee_id']);
            if (! $employee || ($employee->status !== 'active' && $employee->id !== $current?->employee_id)) {
                $errors[$field($index, 'employee_id')] = 'Choisissez un employé actif de l’entreprise.';
            } elseif ($others->contains('employee_id', $employee->id)) {
                $errors[$field($index, 'employee_id')] = $employee->full_name . ' a déjà un objectif sur cet exercice : modifiez son attribution.';
            }
            if (Carbon::parse($row['starts_at'])->year !== $year || Carbon::parse($row['ends_at'])->year !== $year) {
                $errors[$field($index, 'starts_at')] = "La période doit rester dans l’exercice {$year}.";
            }
        }
        if (! $errors) {
            $total = (float) $others->sum('amount') + collect($rows)->sum(fn ($row) => (float) $row['amount']);
            if ($total > (float) $objective->amount + 0.005) {
                $available = max(0, (float) $objective->amount - (float) $others->sum('amount'));
                $errors[$prefix === '' ? 'amount' : $prefix] = 'La répartition dépasserait l’objectif annuel (' . money((float) $objective->amount) . ') : il reste ' . money($available) . ' à répartir. Augmentez d’abord l’objectif.';
            }
        }
        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
    }

    private function assignmentMessages(string $prefix = ''): array
    {
        $p = $prefix === '' ? '' : $prefix . '.*.';

        return [
            "{$prefix}.required" => 'Ajoutez au moins un commercial.',
            "{$p}employee_id.required" => 'Choisissez le commercial.',
            "{$p}employee_id.distinct" => 'Un même commercial apparaît sur deux lignes.',
            "{$p}amount.required" => 'Saisissez le montant de chaque commercial.',
            "{$p}amount.*" => 'Le montant de chaque commercial doit être supérieur à zéro.',
            "{$p}starts_at.*" => 'Indiquez une date de début valide.',
            "{$p}ends_at.after_or_equal" => 'La fin de période doit suivre son début.',
            "{$p}ends_at.*" => 'Indiquez une date de fin valide.',
        ];
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
        ], $this->assignmentMessages('assignments'));
        $this->checkAssignments($objective, $data['assignments'], null, 'assignments');
        foreach ($data['assignments'] as $assignment) {
            $objective->assignments()->create([
                'entreprise_id' => $objective->entreprise_id,
                'employee_id' => (int) $assignment['employee_id'],
                'amount' => $assignment['amount'],
                'starts_at' => $assignment['starts_at'],
                'ends_at' => $assignment['ends_at'],
            ]);
        }
        $count = count($data['assignments']);

        return $this->objectivesPage($objective)->with('success', $count > 1 ? "Objectif réparti entre {$count} commerciaux." : 'Objectif attribué au commercial.');
    }

    public function updateObjectiveAssignment(Request $request, CommercialObjectiveAssignment $assignment)
    {
        $objective = $assignment->objective;
        $this->authorizeWorkflow($objective);
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
        ], $this->assignmentMessages());
        $this->checkAssignments($objective, [$data], $assignment);
        $assignment->update([
            'employee_id' => (int) $data['employee_id'],
            'amount' => $data['amount'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
        ]);

        return $this->objectivesPage($objective)->with('success', 'Attribution de ' . $assignment->refresh()->employee->full_name . ' modifiée.');
    }

    public function destroyObjectiveAssignment(CommercialObjectiveAssignment $assignment)
    {
        $objective = $assignment->objective;
        $this->authorizeWorkflow($objective);
        $name = $assignment->employee?->full_name ?? 'ce commercial';
        $assignment->delete();

        return $this->objectivesPage($objective)->with('success', "Attribution de {$name} supprimée : son montant redevient disponible.");
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

    protected function orders(array $definition)
    {
        $id = auth()->user()->entreprise_id;

        return $this->page('commercial-orders', [
            'title' => 'Bons de commande',
            'subtitle' => 'Commandes nées des devis validés, de la livraison à la facture.',
            'module' => $definition, 'modules' => $this->modules(),
            'orders' => CommercialOrder::where('entreprise_id', $id)
                ->with(['quote:id,reference,quote_date,subject,client_id', 'delivery.invoice', 'creator:id,name'])
                ->orderByDesc('created_at')->orderByDesc('id')->get(),
        ]);
    }

    public function printOrder(CommercialOrder $order)
    {
        $this->authorizeWorkflow($order);
        $order->load(['quote', 'delivery.invoice']);

        return view('admin.commercial-order-print', [
            'order' => $order,
            'client' => CommercialClient::where('entreprise_id', $order->entreprise_id)->find($order->quote?->client_id),
            'company' => auth()->user()->entreprise,
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
                ->with(['order.quote', 'invoice', 'creator'])
                ->latest()
                ->get(),
            // Même règle qu'à la validation : une ligne sans type est un service si le catalogue la connaît.
            'serviceNames' => CommercialService::where('entreprise_id', $id)->pluck('name')
                ->map(fn ($name) => mb_strtolower(trim($name)))->values(),
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
        // Les ventes au comptoir sont nombreuses : la liste couvre une période, le mois en cours par défaut.
        // Elle se limitait aux 20 dernières ventes, sans moyen de voir les précédentes.
        $request = request();
        $date = fn (string $key) => $request->filled($key) ? rescue(fn () => \Carbon\Carbon::parse($request->query($key)), null, false) : null;
        $from = $date('du') ?? now()->startOfMonth();
        $to = $date('au') ?? now();
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return $this->page('commercial-pos', [
            'title' => $definition['title'],
            'subtitle' => 'Ventes au comptoir, encaissées en caisse ou en banque.',
            'module' => $definition,
            'modules' => $this->modules(),
            'from' => $from->copy()->startOfDay(),
            'to' => $to->copy()->endOfDay(),
            'sales' => PosSale::where('entreprise_id', $id)->with(['cashAccount:id,name', 'bankAccount:id,name'])
                ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->latest()->latest('id')->get(),
        ]);
    }

    public function createPosSale()
    {
        return $this->page('commercial-pos-create', $this->posSaleFormData() + [
            'title' => 'Nouvelle vente',
            'subtitle' => 'Enregistrez une vente comptoir.',
        ]);
    }

    /** Données communes de l'écran de saisie, en création comme en modification. */
    protected function posSaleFormData(): array
    {
        $id = auth()->user()->entreprise_id;

        return [
            'module' => collect($this->modules())->firstWhere('key', 'point-de-vente'),
            'modules' => $this->modules(),
            'services' => CommercialService::where('entreprise_id', $id)->where('is_active', true)->orderBy('name')->get(),
            'clients' => CommercialClient::where('entreprise_id', $id)->orderBy('name')->get(),
            'cashAccounts' => CashAccount::where('entreprise_id', $id)->where('is_active', true)->orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('entreprise_id', $id)->orderBy('name')->get(),
            'taxRates' => app(TaxService::class)->ratesFor($id),
        ];
    }

    protected function posSaleRules(bool $withPayment): array
    {
        return array_merge([
            'client_id' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.service_id' => ['nullable', 'integer'],
            'lines.*.item_name' => ['required', 'string', 'max:190'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_rate_id' => ['nullable', 'integer'],
        ], $withPayment ? [
            'payment_method' => ['required', 'in:cash,bank'],
            'cash_account_id' => ['required_if:payment_method,cash', 'nullable', 'integer'],
            'bank_account_id' => ['required_if:payment_method,bank', 'nullable', 'integer'],
        ] : []);
    }

    protected function posSaleMessages(): array
    {
        return [
            'lines.required' => 'Ajoutez au moins un article à la vente.',
            'lines.*.item_name.required' => 'Chaque ligne doit avoir une désignation.',
            'lines.*.quantity.gt' => 'La quantité de chaque ligne doit être supérieure à zéro.',
            'payment_method.required' => 'Choisissez le mode de paiement : espèces ou banque.',
            'cash_account_id.required_if' => 'Choisissez la caisse qui reçoit l’argent.',
            'bank_account_id.required_if' => 'Choisissez le compte bancaire qui reçoit le paiement.',
        ];
    }

    /**
     * Montants d'une vente : total TTC des lignes, taxe extraite du total (le prix
     * saisi en caisse est celui payé par le client), montant reçu et monnaie.
     */
    protected function posSaleAmounts(array $data, string $paymentMethod, $taxRateId): array
    {
        $id = auth()->user()->entreprise_id;
        $lines = collect($data['lines'])->map(fn ($line) => [
            'service_id' => $line['service_id'] ?? null,
            'item_name' => $line['item_name'],
            'quantity' => (float) $line['quantity'],
            'unit_price' => (float) $line['unit_price'],
        ])->values()->all();
        $taxService = app(TaxService::class);
        $currency = $taxService->currencyFor(auth()->user()->entreprise);
        $total = $taxService->roundMoney(collect($lines)->sum(fn ($line) => $line['quantity'] * $line['unit_price']), $currency);
        // Par banque, le client paie le montant exact : pas de monnaie à rendre.
        $paid = $paymentMethod === 'bank' ? $total : (float) ($data['paid_amount'] ?? 0);
        if ($paid < $total) {
            // Refus lisible sur l'écran de saisie : c'était une page d'erreur 422.
            throw \Illuminate\Validation\ValidationException::withMessages(['paid_amount' => 'Le montant reçu (' . money($paid) . ') est inférieur au total de la vente (' . money($total) . ').']);
        }

        return [
            'lines' => $lines,
            'total' => $total,
            'paid' => $paid,
            'change' => $paid - $total,
            'currency' => $currency,
            'tax' => $taxService->breakdownFromTtc($total, $taxService->resolveRate($id, $taxRateId), $currency),
        ];
    }

    public function storePosSale(Request $request)
    {
        $data = $request->validate($this->posSaleRules(true), $this->posSaleMessages());
        $id = auth()->user()->entreprise_id;
        $client = ! empty($data['client_id']) ? CommercialClient::where('entreprise_id', $id)->findOrFail($data['client_id']) : null;
        $amounts = $this->posSaleAmounts($data, $data['payment_method'], $data['tax_rate_id'] ?? null);
        $total = $amounts['total'];

        $sale = DB::transaction(function () use ($data, $id, $client, $amounts, $total) {
            // Numéro de ticket attribué par le compteur des documents.
            $reference = app(DocumentNumberService::class)->next($id, 'pos_sale');
            if ($data['payment_method'] === 'cash') {
                $account = CashAccount::where('entreprise_id', $id)->where('is_active', true)->lockForUpdate()->findOrFail($data['cash_account_id']);
                $account->increment('balance', $total);
                CashMovement::create(['entreprise_id'=>$id, 'cash_account_id'=>$account->id, 'movement_type'=>'entry', 'label'=>'Vente caisse ' . $reference, 'amount'=>$total, 'currency'=>$amounts['currency'], 'payment_mode'=>'cash', 'reference'=>$reference, 'description'=>'Vente au point de vente', 'movement_date'=>now()->toDateString()]);
                $cashId = $account->id;
                $bankId = null;
            } else {
                $account = BankAccount::where('entreprise_id', $id)->lockForUpdate()->findOrFail($data['bank_account_id']);
                $account->increment('current_balance', $total);
                $account->update(['status'=>'credit', 'last_transaction_label'=>'Vente point de vente ' . $reference, 'last_transaction_amount'=>$total, 'last_transaction_direction'=>'up']);
                BankTransaction::create(['entreprise_id'=>$id, 'bank_account_id'=>$account->id, 'transaction_type'=>'credit', 'is_transfer'=>false, 'label'=>'Vente point de vente ' . $reference, 'amount'=>$total, 'description'=>'Vente au point de vente', 'transaction_date'=>now()->toDateString()]);
                $cashId = null;
                $bankId = $account->id;
            }
            $sale = PosSale::create(['entreprise_id'=>$id, 'reference'=>$reference, 'client_id'=>$client?->id, 'client_name'=>$client?->name, 'lines'=>$amounts['lines'], 'total'=>$total, 'paid_amount'=>$amounts['paid'], 'change_amount'=>$amounts['change'], 'payment_method'=>$data['payment_method'], 'cash_account_id'=>$cashId, 'bank_account_id'=>$bankId, 'status'=>'completed',
                'total_ht'=>$amounts['tax']['base_ht'], 'tax_amount'=>$amounts['tax']['tax_amount'], 'tax_rate'=>$amounts['tax']['rate_value'],
                'tax_regime'=>$amounts['tax']['regime'], 'tax_rate_id'=>$amounts['tax']['rate_id'], 'currency'=>$amounts['currency']]);

            app(AccountingPoster::class)->postPosSale($sale, auth()->id());

            return $sale;
        });

        // L'écran reste prêt pour la vente suivante, avec la monnaie à rendre en évidence.
        return redirect()->route('admin.commercial.pos.create')
            ->with('success', 'Vente ' . $sale->reference . ' enregistrée : ' . money($total) . '.')
            ->with('pos_change', $amounts['change'] > 0 ? money($amounts['change']) : null)
            ->with('pos_sale_id', $sale->id);
    }

    public function editPosSale(PosSale $sale)
    {
        $this->authorizeWorkflow($sale);
        if ($sale->status === 'cancelled') {
            return redirect()->route('admin.commercial.pos.show', $sale)->withErrors(['sale' => 'Une vente annulée ne peut pas être modifiée.']);
        }

        return $this->page('commercial-pos-create', $this->posSaleFormData() + [
            'title' => 'Modifier la vente ' . $sale->reference,
            'subtitle' => 'Corrigez les articles ou le client ; la caisse et le journal suivent.',
            'sale' => $sale->load(['cashAccount:id,name', 'bankAccount:id,name']),
        ]);
    }

    public function destroyPosSale(PosSale $sale)
    {
        $this->authorizeWorkflow($sale);
        if ($sale->status !== 'cancelled') {
            throw \Illuminate\Validation\ValidationException::withMessages(['sale' => 'Annulez la vente avant de la supprimer : l’argent doit d’abord ressortir de la caisse ou de la banque.']);
        }
        $sale->delete();
        // Retour à la liste : depuis la fiche, « retour » mènerait à une vente qui n'existe plus.
        return redirect()->route('admin.commercial.module', 'point-de-vente')->with('success', 'Vente ' . $sale->reference . ' supprimée.');
    }

    /**
     * Modification d'une vente : le mode de paiement et la caisse restent ceux
     * de l'encaissement ; la différence de total entre ou sort de la caisse,
     * la TVA est recalculée et le journal suit.
     */
    public function updatePosSale(Request $request, PosSale $sale)
    {
        $this->authorizeWorkflow($sale);
        if ($sale->status === 'cancelled') {
            throw \Illuminate\Validation\ValidationException::withMessages(['sale' => 'Une vente annulée ne peut pas être modifiée.']);
        }
        $data = $request->validate($this->posSaleRules(false), $this->posSaleMessages());
        $id = auth()->user()->entreprise_id;
        $client = ! empty($data['client_id']) ? CommercialClient::where('entreprise_id', $id)->findOrFail($data['client_id']) : null;
        $amounts = $this->posSaleAmounts($data, $sale->payment_method, $data['tax_rate_id'] ?? $sale->tax_rate_id);
        $delta = round($amounts['total'] - (float) $sale->total, 2);
        $label = 'Correction vente ' . ($sale->reference ?: $sale->id);

        DB::transaction(function () use ($sale, $client, $amounts, $delta, $id, $label) {
            if ($delta != 0) {
                if ($sale->payment_method === 'cash') {
                    $account = CashAccount::where('entreprise_id', $id)->lockForUpdate()->findOrFail($sale->cash_account_id);
                    $delta > 0 ? $account->increment('balance', $delta) : $account->decrement('balance', abs($delta));
                    CashMovement::create(['entreprise_id'=>$id, 'cash_account_id'=>$account->id, 'movement_type'=>$delta > 0 ? 'entry' : 'exit', 'label'=>$label, 'amount'=>abs($delta), 'currency'=>$amounts['currency'], 'payment_mode'=>'cash', 'reference'=>($sale->reference ?: 'POS') . '-COR', 'description'=>'Correction après modification de vente', 'movement_date'=>now()->toDateString()]);
                } else {
                    $account = BankAccount::where('entreprise_id', $id)->lockForUpdate()->findOrFail($sale->bank_account_id);
                    $delta > 0 ? $account->increment('current_balance', $delta) : $account->decrement('current_balance', abs($delta));
                    BankTransaction::create(['entreprise_id'=>$id, 'bank_account_id'=>$account->id, 'transaction_type'=>$delta > 0 ? 'credit' : 'debit', 'is_transfer'=>false, 'label'=>$label, 'amount'=>abs($delta), 'description'=>'Correction après modification de vente', 'transaction_date'=>now()->toDateString()]);
                }
            }
            // La TVA était figée à celle de la saisie initiale, quel que soit le nouveau total.
            $sale->update(['client_id'=>$client?->id, 'client_name'=>$client?->name, 'lines'=>$amounts['lines'], 'total'=>$amounts['total'], 'paid_amount'=>$amounts['paid'], 'change_amount'=>$amounts['change'],
                'total_ht'=>$amounts['tax']['base_ht'], 'tax_amount'=>$amounts['tax']['tax_amount'], 'tax_rate'=>$amounts['tax']['rate_value'],
                'tax_regime'=>$amounts['tax']['regime'], 'tax_rate_id'=>$amounts['tax']['rate_id']]);
            // Le journal ne suivait pas la modification.
            app(AccountingPoster::class)->syncPosSale($sale, $label, auth()->id());
        });

        return redirect()->route('admin.commercial.module', 'point-de-vente')->with('success', 'Vente ' . $sale->reference . ' modifiée'
            . ($delta != 0 ? ' : ' . money(abs($delta)) . ($delta > 0 ? ' encaissés en plus.' : ' rendus au client.') : '.'));
    }

    public function cancelPosSale(PosSale $sale)
    {
        $this->authorizeWorkflow($sale);
        if ($sale->status === 'cancelled') {
            throw \Illuminate\Validation\ValidationException::withMessages(['sale' => 'Cette vente est déjà annulée.']);
        }
        $id = auth()->user()->entreprise_id;
        $label = 'Annulation vente ' . ($sale->reference ?: $sale->id);
        DB::transaction(function () use ($sale, $id, $label) {
            if ($sale->payment_method === 'cash') {
                $account = CashAccount::where('entreprise_id', $id)->lockForUpdate()->findOrFail($sale->cash_account_id);
                $account->decrement('balance', $sale->total);
                CashMovement::create(['entreprise_id'=>$id, 'cash_account_id'=>$account->id, 'movement_type'=>'exit', 'label'=>$label, 'amount'=>$sale->total, 'currency'=>company_currency()['code'], 'payment_mode'=>'cash', 'reference'=>($sale->reference ?: 'POS') . '-ANN', 'description'=>'Annulation de la vente au point de vente', 'movement_date'=>now()->toDateString()]);
            } else {
                $account = BankAccount::where('entreprise_id', $id)->lockForUpdate()->findOrFail($sale->bank_account_id);
                $account->decrement('current_balance', $sale->total);
                $account->update(['status'=>'debit', 'last_transaction_label'=>$label, 'last_transaction_amount'=>$sale->total, 'last_transaction_direction'=>'down']);
                BankTransaction::create(['entreprise_id'=>$id, 'bank_account_id'=>$account->id, 'transaction_type'=>'debit', 'is_transfer'=>false, 'label'=>$label, 'amount'=>$sale->total, 'description'=>'Annulation de la vente au point de vente', 'transaction_date'=>now()->toDateString()]);
            }
            $sale->update(['status' => 'cancelled']);
            // La vente sort aussi du journal : son solde y est remis à zéro.
            app(AccountingPoster::class)->syncPosSale($sale, $label, auth()->id());
        });
        return back()->with('success', 'Vente ' . $sale->reference . ' annulée : ' . money((float) $sale->total) . ' sont ressortis de ' . ($sale->payment_method === 'cash' ? 'la caisse' : 'la banque') . '.');
    }

    public function showPosSale(PosSale $sale)
    {
        $this->authorizeWorkflow($sale);
        return $this->page('commercial-pos-show', [
            'title' => 'Vente ' . ($sale->reference ?: $sale->id),
            'sale' => $sale->load(['cashAccount:id,name', 'bankAccount:id,name']),
            'module' => collect($this->modules())->firstWhere('key', 'point-de-vente'),
            'modules' => $this->modules(),
        ]);
    }

    public function storeService(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'is_global' => ['nullable', 'boolean'],
        ]);
        $service = new CommercialService(array_merge($data, [
            'entreprise_id' => auth()->user()->entreprise_id,
            'is_active' => true,
            'is_global' => (bool) ($data['is_global'] ?? false),
        ]));
        $this->ensureServiceCode($service);
        $service->save();
        return redirect()->route('admin.commercial.module', 'services')->with('success', 'Service enregistré.');
    }

    public function updateService(Request $request, CommercialService $service)
    {
        $this->authorizeWorkflow($service);
        $service->fill($request->validate([
            'name' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'is_global' => ['nullable', 'boolean'],
        ]));
        $this->ensureServiceCode($service);
        $service->save();
        return back()->with('success', 'Service modifié.');
    }

    /**
     * Un forfait de facture personnalisée est identifié par son code. Il est
     * généré depuis le nom à la première activation, puis ne change plus :
     * les factures déjà émises le référencent.
     */
    protected function ensureServiceCode(CommercialService $service): void
    {
        if (! $service->is_global || $service->code) {
            return;
        }

        $base = \Illuminate\Support\Str::limit(\Illuminate\Support\Str::slug($service->name, '_'), 50, '') ?: 'service';
        $code = $base;
        for ($i = 2; CommercialService::where('entreprise_id', $service->entreprise_id)->where('code', $code)->exists(); $i++) {
            $code = $base . '_' . $i;
        }
        $service->code = $code;
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
            'taxRates' => app(TaxService::class)->ratesFor($id),
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
            'tax_rate_id' => ['nullable','integer'],
            'lines' => ['required','array','min:1'],
            'lines.*.item_name' => ['required','string','max:190'],
            'lines.*.quantity' => ['required','numeric','gt:0'],
            'lines.*.unit_price' => ['required','numeric','min:0'],
            // Le type décide si la ligne sort du stock à la livraison : il doit être conservé.
            'lines.*.item_type' => ['nullable','in:article,service'],
            'lines.*.unit' => ['nullable','string','max:50'],
        ];
    }

    protected function saveQuote(array $data, ?CommercialQuote $quote = null): CommercialQuote
    {
        $id = auth()->user()->entreprise_id;
        $client = CommercialClient::where('entreprise_id', $id)->findOrFail($data['client_id']);
        $totalHt = collect($data['lines'])->sum(fn ($line) => (float) $line['quantity'] * (float) $line['unit_price']);
        $discount = min($totalHt, (float) ($data['total_discount'] ?? 0));
        $netHt = $totalHt - $discount;
        // Le taux est resolu depuis le parametrage de l'entreprise : c'est lui
        // qui porte le regime fiscal, jamais la valeur du montant.
        $taxService = app(TaxService::class);
        $currency = $taxService->currencyFor(auth()->user()->entreprise);
        $rate = $taxService->resolveRate($id, $data['tax_rate_id'] ?? null, $data['quote_date'] ?? null);
        $breakdown = $taxService->breakdown($netHt, $rate, $currency);
        $taxRate = $breakdown['rate_value'];
        $taxAmount = $breakdown['tax_amount'];
        $quote = $quote ?: new CommercialQuote();
        $quote->fill([
            'entreprise_id'=>$id, 'client_id'=>$client->id, 'client_name'=>$client->name,
            'quote_date'=>$data['quote_date'], 'due_date'=>$data['due_date'] ?? null,
            'payment_terms'=>$data['payment_terms'] ?? null, 'delivery_terms'=>$data['delivery_terms'] ?? null,
            'delivery_location'=>$data['delivery_location'] ?? null, 'payment_method'=>$data['payment_method'] ?? null,
            'subject'=>$data['subject'] ?? null, 'total_ht'=>$totalHt, 'total_discount'=>$discount,
            'net_ht'=>$netHt, 'tax_rate'=>$taxRate, 'tax_amount'=>$taxAmount, 'total_ttc'=>$breakdown['total_ttc'],
            'tax_rate_id'=>$breakdown['rate_id'], 'tax_regime'=>$breakdown['regime'], 'currency'=>$currency,
            'lines'=>$data['lines'], 'status'=>'pending_validation',
        ]);
        if (! $quote->exists) {
            $quote->created_by_user_id = auth()->id();
            $quote->reference = app(DocumentNumberService::class)->next($id, 'quote');
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
            'taxRates' => app(TaxService::class)->ratesFor(auth()->user()->entreprise_id),
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

        // Un devis validé porte sa commande, ses livraisons et ses factures :
        // les clés étrangères les supprimeraient en cascade, écritures et
        // certification FNE comprises.
        if ($quote->status !== 'pending_validation') {
            return back()->withErrors(['quote' => 'Un devis validé ne peut pas être supprimé : sa commande, ses livraisons et ses factures en dépendent.']);
        }

        $quote->delete();
        return back()->with('success', 'Devis supprimé.');
    }

    public function duplicateQuote(CommercialQuote $quote)
    {
        $this->authorizeWorkflow($quote);
        $copy = $quote->replicate();
        $copy->status = 'pending_validation';
        $copy->reference = app(DocumentNumberService::class)->next(auth()->user()->entreprise_id, 'quote');
        $copy->created_by_user_id = auth()->id();
        $copy->customer_order_code = null;
        $copy->subject = trim(($quote->subject ?: '') . ' (copie)');
        // La copie est un nouveau devis : datée du jour, avec la même durée de validité.
        $copy->quote_date = now()->toDateString();
        $copy->due_date = $quote->due_date && $quote->quote_date
            ? now()->addDays($quote->quote_date->diffInDays($quote->due_date))->toDateString()
            : null;
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
                'reference'=>app(DocumentNumberService::class)->next($quote->entreprise_id, 'order'),
                'customer_order_code'=>$quote->customer_order_code,
                'lines'=>$quote->lines, 'total_ttc'=>$quote->total_ttc, 'status'=>'pending_delivery',
                // La ventilation fiscale suit le document, elle n'est plus perdue ici.
                'total_ht'=>$quote->net_ht, 'tax_amount'=>$quote->tax_amount, 'tax_rate'=>$quote->tax_rate,
                'tax_regime'=>$quote->tax_regime, 'tax_rate_id'=>$quote->tax_rate_id,
                'currency'=>$quote->currency ?: app(TaxService::class)->currencyFor($quote->entreprise),
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
        // Une livraison est toujours complète : la livraison partielle, qui sortait
        // tout le stock sans jamais pouvoir être facturée, a été retirée.
        $request->validate(['delivery_type' => ['nullable', 'in:complete']]);
        $data = ['delivery_type' => 'complete'];
        // Une ancienne livraison partielle, déjà sortie du stock mais jamais
        // facturée, peut être terminée : la facture est créée, le stock ne bouge plus.
        $completingPartial = $delivery->status === 'validated' && $delivery->delivery_type === 'partial' && ! $delivery->invoice()->exists();
        DB::transaction(function () use ($delivery, $data, $completingPartial) {
            // Refus renvoyés comme erreurs de saisie : l'utilisateur reste sur la liste et lit le motif.
            $refuse = fn (string $message) => throw \Illuminate\Validation\ValidationException::withMessages(['delivery' => $message]);
            if ($delivery->status === 'validated' && ! $completingPartial) {
                $refuse('Cette livraison est déjà validée.');
            }
            if (! $completingPartial && StockExit::where('entreprise_id', $delivery->entreprise_id)->where('delivery_id', $delivery->id)->exists()) {
                $refuse('La sortie de stock de cette livraison existe déjà.');
            }
            // Les articles puisent dans leurs lots du plus ancien au plus récent : une quantité
            // répartie sur plusieurs réceptions ne fait plus refuser la livraison.
            $stockService = app(\App\Services\StockService::class);
            $lots = $stockService->lots($delivery->entreprise_id);
            $taken = [];
            // Un service n'est pas stocké : il ne sort pas du stock. Les lignes
            // anciennes, sans type, sont reconnues par le catalogue des services.
            $serviceNames = CommercialService::where('entreprise_id', $delivery->entreprise_id)->pluck('name')
                ->map(fn ($serviceName) => mb_strtolower(trim($serviceName)));
            $exitLines = [];
            foreach ($completingPartial ? [] : ($delivery->lines ?: []) as $line) {
                $name = trim((string) ($line['item_name'] ?? $line['designation'] ?? ''));
                $quantity = (float) ($line['quantity'] ?? 0);
                if ($name === '' || $quantity <= 0) {
                    $refuse('Une ligne de livraison est invalide.');
                }
                $isService = ($line['item_type'] ?? null) === 'service'
                    || (! isset($line['item_type']) && $serviceNames->contains(mb_strtolower($name)));
                if ($isService) {
                    continue;
                }
                $matches = fn ($stockLine) => strcasecmp($stockLine->designation, $name) === 0
                    || ($stockLine->article && strcasecmp($stockLine->article, $name) === 0);
                array_push($exitLines, ...$stockService->allocate($lots, $matches, $quantity, $name, 'delivery', $taken,
                    'Enregistrez une entrée de stock, ou indiquez « Service » sur la ligne du devis s\'il s\'agit d\'une prestation.'));
            }
            if ($exitLines) {
                $exit = StockExit::create([
                    'entreprise_id' => $delivery->entreprise_id, 'delivery_id' => $delivery->id,
                    'exit_date' => now()->toDateString(), 'total_value' => collect($exitLines)->sum('total_value'),
                ]);
                $exit->lines()->createMany($exitLines);
            }
            $delivery->update(['delivery_type'=>$data['delivery_type'], 'status'=>'validated']);
            $delivery->order()->update([
                'status' => $data['delivery_type'] === 'complete' ? 'delivered' : 'partially_delivered',
            ]);
            if ($data['delivery_type'] === 'complete') {
                $createdInvoice = CommercialInvoice::firstOrCreate(
                    ['entreprise_id'=>$delivery->entreprise_id, 'delivery_id'=>$delivery->id],
                    [
                        'created_by_user_id'=>$delivery->created_by_user_id, 'client_name'=>$delivery->client_name,
                        'amount'=>$delivery->order->total_ttc, 'paid_amount'=>0, 'status'=>'unpaid',
                        // La facture porte enfin sa propre ventilation, elle est
                        // le document transmis a l'administration fiscale.
                        'total_ht'=>$delivery->order->total_ht, 'tax_amount'=>$delivery->order->tax_amount,
                        'tax_rate'=>$delivery->order->tax_rate, 'tax_regime'=>$delivery->order->tax_regime,
                        'tax_rate_id'=>$delivery->order->tax_rate_id, 'currency'=>$delivery->order->currency,
                        'issued_at'=>now(),
                    ]
                );

                // La facture alimente desormais le journal comptable.
                app(AccountingPoster::class)->postSaleInvoice($createdInvoice, auth()->id());
            }
        });
        return back()->with('success', $completingPartial
            ? 'Livraison terminée : facture créée pour paiement.'
            : 'Livraison validée : facture créée pour paiement.');
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
                    'currency' => company_currency()['code'],
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
            // Chaque reglement est trace avec sa date : c'est la base du
            // calcul de la TVA sur les encaissements.
            app(PaymentRecorder::class)->record(
                $invoice, (float) $data['paid_amount'], $data['payment_method'], now(), auth()->id()
            );

            // Le reglement solde la creance client au journal comptable.
            app(AccountingPoster::class)->postCustomerPayment(
                $invoice, (float) $data['paid_amount'], $data['payment_method'], now(), auth()->id()
            );

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

    /**
     * Annulation d'une facture de vente.
     *
     * Une facture de vente nait de la validation d'une livraison : elle est
     * emise des sa creation. Passer son statut a "annulee" effacait la trace
     * du montant facture. On emet donc un avoir total, qui conserve les deux
     * documents, puis on marque la facture annulee.
     */
    public function cancelInvoice(Request $request, CommercialInvoice $invoice)
    {
        $this->authorizeWorkflow($invoice);
        abort_if($invoice->status === 'cancelled', 422, 'Cette facture est déjà annulée.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $integrity = app(InvoiceIntegrityService::class);

        try {
            $note = $integrity->credit($invoice, $integrity->creditableAmount($invoice), $data['reason'], auth()->id());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        $invoice->update(['status' => 'cancelled']);

        return back()->with('success', "Facture annulée par l'avoir {$note->reference}.");
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
        $taxService = app(TaxService::class);

        // Un document dont le regime fiscal est inconnu ne part pas au fisc.
        if (! $taxService->isDeclarable($invoice->tax_regime)) {
            return back()->withErrors(['fne' => "Le régime fiscal de cette facture n'est pas renseigné. Reprenez le devis d'origine avant de certifier."]);
        }

        // Le code declare depend du REGIME du taux applique, pas du montant.
        $fiscalCode = $taxService->fiscalCodeFor($invoice->tax_regime);

        $lines = collect($invoice->delivery?->lines ?: [])->map(fn ($line) => [
            'taxes' => [$fiscalCode],
            'reference' => $line['reference'] ?? ('REF-' . ($line['product_id'] ?? 'SERVICE')),
            'description' => $line['description'] ?? $line['name'] ?? 'Article',
            'quantity' => (float) ($line['quantity'] ?? 1),
            'amount' => (float) ($line['unit_price'] ?? $line['price'] ?? 0),
            'discount' => (float) ($line['discount'] ?? 0),
            'measurementUnit' => $line['unit'] ?? 'pcs',
        ])->values()->all();
        if (!$lines) {
            return back()->withErrors(['fne' => 'La facture ne contient aucune ligne certifiable.']);
        }

        // La remise reellement accordee doit etre declaree : sans elle, le
        // montant transmis ne correspond pas au montant facture.
        $documentDiscount = (float) ($quote?->total_discount ?? 0);
        try {
            $fne->certify($invoice, 'sale', [
                'invoiceType' => 'sale', 'paymentMethod' => $this->fnePaymentMethod($invoice->payment_method),
                'template' => $client?->tax_id ? 'B2B' : 'B2C', 'isRne' => false,
                'clientCompanyName' => $client?->name ?: $invoice->client_name ?: 'Client',
                'clientPhone' => $client?->phone ?: '', 'clientEmail' => $client?->email ?: '',
                'clientNcc' => $client?->tax_id, 'pointOfSale' => config('fne.point_of_sale'),
                'establishment' => config('fne.establishment'), 'commercialMessage' => 'Merci pour votre confiance',
                'footer' => 'Service client: ' . (auth()->user()->entreprise->email ?? ''), 'items' => $lines,
                'discount' => $documentDiscount,
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
            // Le client lié fournit l'adresse e-mail proposée à l'envoi.
            'proformas' => CommercialProforma::where('entreprise_id', $entrepriseId)->with('client:id,email')->withCount('lines')
                ->latest('creation_date')->latest('id')->get(),
        ]);
    }

    public function createProforma()
    {
        $entrepriseId = auth()->user()->entreprise_id;

        return $this->page('commercial-proforma-create', [
            'title' => 'Créer une proforma',
            'subtitle' => 'Saisissez le client, les lignes, les remises et les taxes',
            'module' => collect($this->modules())->firstWhere('key', 'proforma'),
            'modules' => $this->modules(),
            'clients' => CommercialClient::where('entreprise_id', $entrepriseId)->orderBy('name')->get(),
            'taxRates' => app(TaxService::class)->ratesFor($entrepriseId),
            // Suggestions de désignation, comme sur le devis : articles du stock et services du catalogue.
            'catalogArticles' => $this->quoteArticles($entrepriseId),
            'catalogServices' => CommercialService::where('entreprise_id', $entrepriseId)->where('is_active', true)->orderBy('name')->get(['name', 'price', 'unit']),
        ]);
    }

    public function storeProforma(Request $request)
    {
        $proforma = $this->saveProforma($request->validate($this->proformaRules(), $this->proformaMessages()));
        return redirect()->route('admin.commercial.module', 'proforma')->with('success', 'Proforma ' . $proforma->reference . ' enregistrée.');
    }

    protected function proformaRules(): array
    {
        return [
            // Un client du carnet (identifiant) ou « new » avec les champs du nouveau client.
            'client_id' => ['nullable', 'regex:/^(\d+|new)$/'],
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
            'tax_rate_id' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            // « item_type » était exigé sans que le formulaire l'envoie : aucun proforma ne pouvait être enregistré.
            'lines.*.type' => ['nullable', 'in:product,service'],
            'lines.*.category' => ['nullable', 'string', 'max:190'],
            'lines.*.item_name' => ['required', 'string', 'max:190'],
            'lines.*.unit' => ['nullable', 'string', 'max:50'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_type' => ['nullable', 'in:percent,amount'],
        ];
    }

    /** Messages des erreurs que la saisie d'une proforma rencontre le plus souvent. */
    protected function proformaMessages(): array
    {
        return [
            'creation_date.required' => 'Indiquez la date de la proforma.',
            'due_date.after_or_equal' => 'La date limite ne peut pas précéder la date de la proforma.',
            'lines.required' => 'Ajoutez au moins une ligne à la proforma.',
            'lines.*.item_name.required' => 'Chaque ligne doit avoir une désignation.',
            'lines.*.quantity.gt' => 'La quantité de chaque ligne doit être supérieure à zéro.',
            'new_client_email.email' => 'L’adresse e-mail du nouveau client n’est pas valide.',
        ];
    }

    protected function saveProforma(array $data, ?CommercialProforma $proforma = null): CommercialProforma
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $clientId = (string) ($data['client_id'] ?? '');
        $client = ctype_digit($clientId) ? CommercialClient::where('entreprise_id', $entrepriseId)->find((int) $clientId) : null;
        if (! $client && ! filled($data['new_client_name'] ?? null)) {
            // Refus lisible sur l'écran de saisie, et non une page d'erreur 422.
            throw \Illuminate\Validation\ValidationException::withMessages(['client_id' => 'Choisissez un client, ou saisissez le nom du nouveau client.']);
        }

        $preparedLines = [];
        $totalHt = $totalDiscount = 0;
        foreach ($data['lines'] as $line) {
            $quantity = (float) $line['quantity'];
            $gross = $quantity * (float) $line['unit_price'];
            $type = ($line['discount_type'] ?? 'percent') === 'amount' ? 'amount' : 'percent';
            $value = (float) ($line['discount'] ?? 0);
            // Une remise ne dépasse jamais le montant de sa ligne.
            $discount = min($gross, $type === 'amount' ? $value : $gross * min($value, 100) / 100);
            $lineTotal = $gross - $discount;
            $totalHt += $gross;
            $totalDiscount += $discount;
            $preparedLines[] = [
                'type' => ($line['type'] ?? 'product') === 'service' ? 'service' : 'product',
                'category' => $line['category'] ?? null,
                'item_name' => $line['item_name'],
                'unit' => $line['unit'] ?? null,
                'quantity' => $quantity,
                'unit_price' => (float) $line['unit_price'],
                'discount' => $value,
                'discount_type' => $type,
                'net_unit_price' => $lineTotal / $quantity,
                'line_total' => $lineTotal,
            ];
        }
        $netHt = $totalHt - $totalDiscount;

        // Le taux vient du paramétrage de l'entreprise : il porte le régime fiscal
        // (exonéré, export…), qu'un simple pourcentage ne distingue pas.
        $taxService = app(TaxService::class);
        $currency = $taxService->currencyFor(auth()->user()->entreprise);
        $breakdown = $taxService->breakdown($netHt, $taxService->resolveRate($entrepriseId, $data['tax_rate_id'] ?? null, $data['creation_date']), $currency);

        return DB::transaction(function () use ($data, $client, $entrepriseId, $preparedLines, $totalHt, $totalDiscount, $breakdown, $currency, $proforma) {
            $client ??= CommercialClient::create([
                'entreprise_id' => $entrepriseId,
                'name' => $data['new_client_name'],
                'phone' => $data['new_client_phone'] ?? null,
                'email' => $data['new_client_email'] ?? null,
            ]);
            $proforma = $proforma ?: new CommercialProforma(['entreprise_id' => $entrepriseId]);
            // Numéro attribué une fois, à la création, par le compteur des documents.
            $proforma->reference ??= app(DocumentNumberService::class)->next($entrepriseId, 'proforma', \Carbon\Carbon::parse($data['creation_date']));
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
                'net_ht' => $breakdown['base_ht'],
                'tax_amount' => $breakdown['tax_amount'],
                'tax_rate' => $breakdown['rate_value'],
                'tax_rate_id' => $breakdown['rate_id'],
                'tax_regime' => $breakdown['regime'],
                'currency' => $currency,
                'total_ttc' => $breakdown['total_ttc'],
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
            'title' => 'Modifier la proforma',
            'subtitle' => 'Modifiez les informations et les lignes de la proforma',
            'module' => collect($this->modules())->firstWhere('key', 'proforma'),
            'modules' => $this->modules(),
            'clients' => CommercialClient::where('entreprise_id', auth()->user()->entreprise_id)->orderBy('name')->get(),
            'proforma' => $proforma,
            'taxRates' => app(TaxService::class)->ratesFor(auth()->user()->entreprise_id),
            'catalogArticles' => $this->quoteArticles(auth()->user()->entreprise_id),
            'catalogServices' => CommercialService::where('entreprise_id', auth()->user()->entreprise_id)->where('is_active', true)->orderBy('name')->get(['name', 'price', 'unit']),
        ]);
    }

    public function updateProforma(Request $request, CommercialProforma $proforma)
    {
        $this->authorizeProforma($proforma);
        $this->saveProforma($request->validate($this->proformaRules(), $this->proformaMessages()), $proforma);
        return redirect()->route('admin.commercial.module', 'proforma')->with('success', 'Proforma ' . $proforma->reference . ' modifiée.');
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
        return view('admin.commercial-proforma-print', [
            'proforma' => $proforma->load(['lines', 'client']),
            'company' => auth()->user()->entreprise,
        ]);
    }

    public function emailProforma(Request $request, CommercialProforma $proforma)
    {
        $this->authorizeProforma($proforma);
        $data = $request->validate(['email' => ['required', 'email', 'max:190']], [
            'email.*' => 'Indiquez une adresse e-mail valide pour le destinataire.',
        ]);
        // Montant formaté dans la devise de l'entreprise : il partait brut, en « XOF » écrit en dur.
        $message = 'Bonjour ' . ($proforma->client_name ?: 'Madame, Monsieur') . ",\n\n"
            . 'Veuillez trouver notre proforma ' . $proforma->reference . ' d’un montant de ' . money((float) $proforma->total_ttc) . ' TTC'
            . ($proforma->due_date ? ', valable jusqu’au ' . $proforma->due_date->format('d/m/Y') : '') . ".\n\nCordialement.";
        Mail::raw($message, function ($mail) use ($data, $proforma) {
            $mail->to($data['email'])->subject('Votre proforma ' . $proforma->reference);
        });
        $proforma->update(['status' => 'sent']);
        return back()->with('success', 'Proforma envoyé à ' . $data['email'] . '.');
    }

    /**
     * Valide un inventaire : chaque article compté est comparé au stock
     * théorique recalculé ici, et l'écart est passé au stock. Un manquant sort
     * des lots les plus anciens ; un surplus entre au dernier prix d'achat.
     */
    public function storeInventory(Request $request)
    {
        $data = $request->validate([
            'inventoried_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.key' => ['required', 'string', 'max:500'],
            'items.*.counted' => ['nullable', 'numeric', 'min:0'],
        ], [
            'inventoried_at.required' => 'Indiquez la date de l’inventaire.',
            'items.required' => 'Aucun article à compter : enregistrez d’abord une réception.',
            'items.*.counted.min' => 'Une quantité comptée ne peut pas être négative.',
        ]);
        $entrepriseId = auth()->user()->entreprise_id;
        $stockService = app(\App\Services\StockService::class);
        // Le théorique n'est plus lu dans le formulaire : il est recalculé, au moment de valider.
        $status = $stockService->status($entrepriseId)->keyBy('key');
        $counted = collect($data['items'])
            ->filter(fn ($item) => isset($item['counted']) && $item['counted'] !== '' && $status->has($item['key']))
            ->mapWithKeys(fn ($item) => [$item['key'] => round((float) $item['counted'], 3)]);
        if ($counted->isEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Saisissez la quantité comptée d’au moins un article.']);
        }

        $inventory = $stockService->applyInventory($entrepriseId, $data['inventoried_at'], $data['note'] ?? null, $counted->all(), auth()->id());

        return back()->with('success', 'Inventaire validé : ' . $inventory->counted_items . ' article(s) compté(s), '
            . ($inventory->variance_items ? $inventory->variance_items . ' écart(s) passé(s) au stock (manquants ' . money((float) $inventory->shortage_value) . ', surplus ' . money((float) $inventory->surplus_value) . ').' : 'aucun écart : le stock est conforme.'));
    }
}
