<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use App\Models\AccountEntry;
use App\Models\AccountTransfer;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\ExpenseCategory;
use App\Models\FixedAsset;
use App\Models\SupplierInvoice;
use App\Models\CommercialInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\FneCertificationService;
use App\Services\AccountingPoster;
use App\Services\LedgerService;
use App\Services\TaxService;
use App\Services\VatDeclarationService;

class ComptabiliteController extends AdminController
{
    public function tableau(Request $request)
    {
        $entrepriseId = auth()->user()?->entreprise_id;
        $from = Carbon::parse($request->input('date_debut', now()->startOfYear()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('date_fin', now()->toDateString()))->endOfDay();
        abort_unless($from->lte($to), 422, 'La période sélectionnée est invalide.');
        $inPeriod = fn ($date) => $date && Carbon::parse($date)->between($from, $to);
        $cashAccounts = CashAccount::where('entreprise_id', $entrepriseId)->orderBy('name')->get();
        $bankAccounts = BankAccount::where('entreprise_id', $entrepriseId)->orderBy('name')->get();
        $cashMovements = CashMovement::where('entreprise_id', $entrepriseId)->where('is_transfer', false)->with('cashAccount')->latest('movement_date')->get()->filter(fn ($item) => $inPeriod($item->movement_date))->values();
        $bankTransactions = BankTransaction::where('entreprise_id', $entrepriseId)->where('is_transfer', false)->with('bankAccount')->latest('transaction_date')->get()->filter(fn ($item) => $inPeriod($item->transaction_date))->values();
        $transfers = AccountTransfer::where('entreprise_id', $entrepriseId)->latest('transfer_date')->get()->filter(fn ($item) => $inPeriod($item->transfer_date))->values();
        $supplierInvoices = SupplierInvoice::where('entreprise_id', $entrepriseId)->latest()->get()->filter(fn ($item) => $inPeriod($item->created_at))->values();
        $fixedAssets = FixedAsset::where('entreprise_id', $entrepriseId)->latest('acquisition_date')->get()->filter(fn ($item) => $inPeriod($item->acquisition_date))->values();

        $cashIn = (float) $cashMovements->where('movement_type', 'entry')->sum('amount');
        $cashOut = (float) $cashMovements->where('movement_type', 'exit')->sum('amount');
        $bankIn = (float) $bankTransactions->where('transaction_type', 'credit')->sum('amount');
        $bankOut = (float) $bankTransactions->where('transaction_type', 'debit')->sum('amount');
        $liquidity = (float) $cashAccounts->sum('balance') + (float) $bankAccounts->sum('current_balance');
        $monthlyMovements = $cashMovements->filter(fn ($item) => $item->movement_date?->isSameMonth(now()));
        $monthlyBank = $bankTransactions->filter(fn ($item) => $item->transaction_date?->isSameMonth(now()));

        $months = collect(range(5, 0))->map(function ($offset) use ($cashMovements, $bankTransactions) {
            $date = now()->subMonths($offset);
            $in = $cashMovements->filter(fn ($item) => $item->movement_date?->isSameMonth($date))->where('movement_type', 'entry')->sum('amount')
                + $bankTransactions->filter(fn ($item) => $item->transaction_date?->isSameMonth($date))->where('transaction_type', 'credit')->sum('amount');
            $out = $cashMovements->filter(fn ($item) => $item->movement_date?->isSameMonth($date))->where('movement_type', 'exit')->sum('amount')
                + $bankTransactions->filter(fn ($item) => $item->transaction_date?->isSameMonth($date))->where('transaction_type', 'debit')->sum('amount');
            return ['label' => $date->translatedFormat('M'), 'entries' => (float) $in, 'sorties' => (float) $out];
        });

        return $this->page('comptabilite-tableau', [
            'title' => 'Tableau comptable',
            'liquidity' => $liquidity,
            'cashIn' => $cashIn,
            'cashOut' => $cashOut,
            'bankIn' => $bankIn,
            'bankOut' => $bankOut,
            'monthlyTotal' => (float) $monthlyMovements->sum('amount') + (float) $monthlyBank->sum('amount'),
            'months' => $months,
            'periodFrom' => $from->toDateString(),
            'periodTo' => $to->toDateString(),
            'detailLists' => [
                'cash_accounts' => $cashAccounts,
                'bank_accounts' => $bankAccounts,
                'cash_movements' => $cashMovements,
                'bank_transactions' => $bankTransactions,
                'transfers' => $transfers,
                'supplier_invoices' => $supplierInvoices,
                'fixed_assets' => $fixedAssets,
            ],
        ]);
    }

    public function index()
    {
        // Les totaux viennent desormais du journal en partie double, et non
        // plus d'une table qui n'etait alimentee que par le jeu de demonstration.
        $entrepriseId = auth()->user()?->entreprise_id;
        $entries = \App\Models\JournalEntry::withoutGlobalScope('entreprise')
            ->where('entreprise_id', $entrepriseId)
            ->with('lines.account')->latest('entry_date')->limit(6)->get();

        $totals = \DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.entreprise_id', $entrepriseId)
            ->selectRaw('COALESCE(SUM(l.debit),0) AS d, COALESCE(SUM(l.credit),0) AS c')->first();

        $debits = (float) $totals->d;
        $credits = (float) $totals->c;

        return $this->page('comptabilite', [
            'title' => 'Espace Comptabilité',
            'hub' => $this->buildComptabiliteHub(),
            'entries' => $entries,
            'credits' => $credits,
            'debits' => $debits,
        ]);
    }

    public function caisses(Request $request)
    {
        $entrepriseId = auth()->user()?->entreprise_id;
        $dateDebut = $request->input('date_debut', now()->startOfMonth()->format('Y-m-d'));
        $dateFin = $request->input('date_fin', now()->format('Y-m-d'));

        $query = CashMovement::query()->with(['cashAccount', 'expenseCategory'])->where('entreprise_id', $entrepriseId);

        $query->whereDate('movement_date', '>=', $dateDebut)
            ->whereDate('movement_date', '<=', $dateFin);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('label', 'ilike', "%{$search}%")
                  ->orWhere('reference', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('movement_type', $request->type);
        }

        if ($request->filled('payment_mode')) {
            $query->where('payment_mode', $request->payment_mode);
        }

        if ($request->filled('cash_account_id')) {
            $query->where('cash_account_id', $request->cash_account_id);
        }

        if ($request->filled('expense_category_id')) {
            $query->where('expense_category_id', $request->expense_category_id);
        }

        $query->orderBy('movement_date', 'desc')->orderBy('created_at', 'desc');
        $movements = $query->get();

        $accounts = CashAccount::where('entreprise_id', $entrepriseId)->orderBy('name')->get();
        $expenseCategories = ExpenseCategory::where('entreprise_id', $entrepriseId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $periodMovements = CashMovement::query()
            ->where('entreprise_id', $entrepriseId)
            ->whereDate('movement_date', '>=', $dateDebut)
            ->whereDate('movement_date', '<=', $dateFin)
            ->get();
        $beforePeriodMovements = CashMovement::query()
            ->where('entreprise_id', $entrepriseId)
            ->whereDate('movement_date', '<', $dateDebut)
            ->get();
        $initialTotal = (float) $accounts->sum('initial_balance')
            + (float) $beforePeriodMovements
                ->where('is_transfer', false)
                ->sum(function ($movement) {
                    return $movement->movement_type === 'entry' ? $movement->amount : -$movement->amount;
                });
        $entriesTotal = (float) $periodMovements->where('movement_type', 'entry')->where('is_transfer', false)->sum('amount');
        $exitsTotal = (float) $periodMovements->where('movement_type', 'exit')->where('is_transfer', false)->sum('amount');
        $currentBalance = $initialTotal + $entriesTotal - $exitsTotal;
        $accounts->each(function ($account) use ($beforePeriodMovements, $periodMovements) {
            $accountBefore = $beforePeriodMovements->where('cash_account_id', $account->id)
                ->where('is_transfer', false);
            $accountDuring = $periodMovements->where('cash_account_id', $account->id)
                ->where('is_transfer', false);
            $openingBalance = (float) $account->initial_balance
                + (float) $accountBefore->sum(function ($movement) {
                    return $movement->movement_type === 'entry' ? $movement->amount : -$movement->amount;
                });
            $account->period_initial_balance = $openingBalance;
            $account->period_balance = $openingBalance
                + (float) $accountDuring->sum(function ($movement) {
                    return $movement->movement_type === 'entry' ? $movement->amount : -$movement->amount;
                });
        });

        return $this->page('comptabilite-module', [
            'title' => 'Caisses',
            'subtitle' => 'Suivi des encaisses, mouvements et soldes de caisse',
            'moduleType' => 'caisses',
            'accounts' => $accounts,
            'expenseCategories' => $expenseCategories,
            'movements' => $movements,
            'filters' => [
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'search' => $request->search ?? '',
                'type' => $request->type ?? '',
                'payment_mode' => $request->payment_mode ?? '',
                'cash_account_id' => $request->cash_account_id ?? '',
                'expense_category_id' => $request->expense_category_id ?? '',
            ],
            'summary' => [
                ['label' => 'Montant initial période', 'value' => number_format($initialTotal, 0, ',', ' ') . ' FCFA', 'change' => 'solde au début de la période'],
                ['label' => 'Total entrées', 'value' => number_format($entriesTotal, 0, ',', ' ') . ' FCFA', 'change' => '+ ventes et encaissements'],
                ['label' => 'Total sorties', 'value' => number_format($exitsTotal, 0, ',', ' ') . ' FCFA', 'change' => '- dépenses et décaissements'],
                ['label' => 'Solde réel', 'value' => number_format($currentBalance, 0, ',', ' ') . ' FCFA', 'change' => 'état général des caisses'],
            ],
            'tableColumns' => ['Caisse', 'Libellé', 'Type', 'Montant', 'Date', 'Mode', 'Solde', 'Action'],
            'tableRows' => $movements->map(function ($movement) use ($accounts) {
                $rowData = [
                    'caisse' => $movement->cashAccount?->name ?? 'Caisse',
                    'libelle' => $movement->label,
                    'type' => $movement->movement_type === 'entry' ? 'Entrée' : 'Sortie',
                    'montant' => number_format((float) $movement->amount, 0, ',', ' ') . ' FCFA',
                    'date' => $movement->movement_date?->format('d/m/Y') ?? '-',
                    'mode' => ucfirst($movement->payment_mode ?? 'cash'),
                    'reference' => $movement->reference ?? '-',
                    'description' => $movement->description ?? '-',
                ];

                $account = $movement->cashAccount;
                $previousBalance = 0;
                if ($account) {
                    $previousBalance = (float) $account->movements()
                        ->where(function ($query) use ($movement) {
                            $query->whereDate('movement_date', '<', $movement->movement_date)
                                ->orWhere(function ($sameDay) use ($movement) {
                                    $sameDay->whereDate('movement_date', $movement->movement_date)
                                        ->where('id', '<', $movement->id);
                                });
                        })
                        ->sum(DB::raw("CASE WHEN movement_type = 'entry' THEN amount ELSE -amount END"));
                    $previousBalance += (float) $account->initial_balance;
                }

                $currentBalance = $previousBalance + ($movement->movement_type === 'entry' ? (float) $movement->amount : -(float) $movement->amount);

                if (!$this->canModifyMovement($movement)) {
                    $actionHtml = '<span class="text-muted">Consultation</span>';
                } else {
                    $actionHtml = sprintf(
                    '<div class="dropdown">'
                    . '<button type="button" class="btn btn-sm btn-light action-menu-button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions"></button>'
                    . '<ul class="dropdown-menu dropdown-menu-end">'
                    . '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editMovementModal-%s">Modifier</button></li>'
                    . '<li><form method="POST" action="%s" onsubmit="return confirm(\'Supprimer ce mouvement ?\');">%s %s<button type="submit" class="dropdown-item text-danger">Supprimer</button></form></li>'
                    . '<li><hr class="dropdown-divider"></li>'
                    . '<li><button type="button" class="dropdown-item" data-print-row="%s">Imprimer</button></li>'
                    . '</ul>'
                    . '</div>',
                    $movement->id,
                    route('admin.comptabilite.caisses.destroyMovement', $movement),
                    csrf_field(),
                    method_field('DELETE'),
                    htmlspecialchars(json_encode($rowData), ENT_QUOTES, 'UTF-8'),
                    );
                }

                return [
                    $movement->cashAccount?->name ?? 'Caisse',
                    $movement->label,
                    $movement->is_transfer
                        ? 'Réapprovisionnement'
                        : ($movement->movement_type === 'entry' ? 'Entrée' : 'Sortie'),
                    number_format((float) $movement->amount, 0, ',', ' ') . ' FCFA',
                    $movement->movement_date?->format('d/m/Y') ?? '-',
                    ucfirst($movement->payment_mode ?? 'cash'),
                    number_format($currentBalance, 0, ',', ' ') . ' FCFA',
                    $actionHtml,
                ];
            })->all(),
        ]);
    }

    public function storeAccount(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', 'in:caisse,mobile_money'],
            'initial_balance' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        CashAccount::create([
            'entreprise_id' => auth()->user()->entreprise_id,
            'name' => $validated['name'],
            'account_type' => $validated['account_type'],
            'description' => $validated['description'] ?? null,
            'currency' => company_currency()['code'],
            'initial_balance' => $validated['initial_balance'] ?? 0,
            'balance' => $validated['initial_balance'] ?? 0,
            'is_active' => true,
        ]);

        return redirect()->route('admin.comptabilite.caisses')->with('success', 'Compte caisse créé avec succès.');
    }

    public function updateAccount(Request $request, CashAccount $cashAccount)
    {
        $this->authorizeAccount($cashAccount);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', 'in:caisse,mobile_money'],
            'initial_balance' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $cashAccount->update([
            'name' => $validated['name'],
            'account_type' => $validated['account_type'],
            'description' => $validated['description'] ?? null,
            'initial_balance' => $validated['initial_balance'] ?? $cashAccount->initial_balance,
            'balance' => $cashAccount->balance + (($validated['initial_balance'] ?? $cashAccount->initial_balance) - $cashAccount->initial_balance),
        ]);

        return redirect()->route('admin.comptabilite.caisses')->with('success', 'Compte caisse mis à jour.');
    }

    public function destroyAccount(CashAccount $cashAccount)
    {
        $this->authorizeAccount($cashAccount);
        $cashAccount->delete();

        return redirect()->route('admin.comptabilite.caisses')->with('success', 'Compte caisse supprimé.');
    }

    public function toggleAccountStatus(CashAccount $cashAccount)
    {
        $this->authorizeAccount($cashAccount);
        $cashAccount->is_active = ! $cashAccount->is_active;
        $cashAccount->save();

        $label = $cashAccount->is_active ? 'ouverte' : 'fermée';

        return redirect()->route('admin.comptabilite.caisses')->with('success', "La caisse a été {$label}.");
    }

    public function storeMovement(Request $request)
    {
        $validated = $request->validate([
            'cash_account_id' => ['required', 'exists:cash_accounts,id'],
            'expense_category_id' => ['nullable', 'integer'],
            'movement_type' => ['required', 'in:entry,exit'],
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_mode' => ['required', 'in:cash,mobile_money,bank,transfer'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'movement_date' => ['required', 'date'],
        ]);

        $account = CashAccount::where('entreprise_id', auth()->user()->entreprise_id)
            ->findOrFail($validated['cash_account_id']);

        $category = ! empty($validated['expense_category_id'])
            ? ExpenseCategory::where('entreprise_id', auth()->user()->entreprise_id)->where('is_active', true)->findOrFail($validated['expense_category_id'])
            : null;

        if ($validated['movement_type'] === 'exit' && ! $category) {
            return back()->withErrors(['expense_category_id' => 'Une catégorie de dépense est obligatoire pour une sortie.'])->withInput();
        }

        if (! $account->is_active) {
            return back()->withErrors(['cash_account_id' => 'Cette caisse est fermée. Ouvrez-la pour enregistrer un mouvement.']);
        }

        $movement = CashMovement::create([
            'entreprise_id' => auth()->user()->entreprise_id,
            'cash_account_id' => $account->id,
            'expense_category_id' => $category?->id,
            'movement_type' => $validated['movement_type'],
            'label' => $validated['label'],
            'amount' => $validated['amount'],
            'currency' => company_currency()['code'],
            'payment_mode' => $validated['payment_mode'],
            'reference' => $validated['reference'] ?? null,
            'description' => $validated['description'] ?? null,
            'movement_date' => $validated['movement_date'],
        ]);

        $account->balance = $account->balance + ($validated['movement_type'] === 'entry' ? $validated['amount'] : -$validated['amount']);
        $account->save();

        return redirect()->route('admin.comptabilite.caisses')->with('success', 'Mouvement enregistré avec succès.');
    }

    public function updateMovement(Request $request, CashMovement $cashMovement)
    {
        $this->authorizeMovement($cashMovement);

        $validated = $request->validate([
            'cash_account_id' => ['required', 'exists:cash_accounts,id'],
            'expense_category_id' => ['nullable', 'integer'],
            'movement_type' => ['required', 'in:entry,exit'],
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_mode' => ['required', 'in:cash,mobile_money,bank,transfer'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'movement_date' => ['required', 'date'],
        ]);

        if (!$this->isFullCashAdmin() && !$this->isSecretary()) {
            abort(403, 'Votre profil ne permet pas de modifier les mouvements de caisse.');
        }
        if (!$this->isFullCashAdmin() && $cashMovement->movement_date?->isToday() !== true) {
            abort(403, 'La modification est réservée à l’administrateur pour les mouvements antérieurs à aujourd’hui.');
        }
        if (!$this->isFullCashAdmin() && $validated['movement_date'] !== today()->toDateString()) {
            abort(403, 'La secrétaire ne peut modifier un mouvement que pour la date d’aujourd’hui.');
        }

        $targetAccount = CashAccount::where('entreprise_id', auth()->user()->entreprise_id)
            ->findOrFail($validated['cash_account_id']);

        $category = ! empty($validated['expense_category_id'])
            ? ExpenseCategory::where('entreprise_id', auth()->user()->entreprise_id)->where('is_active', true)->findOrFail($validated['expense_category_id'])
            : null;

        if ($validated['movement_type'] === 'exit' && ! $category) {
            return back()->withErrors(['expense_category_id' => 'Une catégorie de dépense est obligatoire pour une sortie.'])->withInput();
        }

        if (! $targetAccount->is_active) {
            return back()->withErrors(['cash_account_id' => 'Cette caisse est fermée. Ouvrez-la avant de modifier un mouvement.']);
        }

        $oldAccount = $cashMovement->cashAccount;

        $cashMovement->update([
            'cash_account_id' => $validated['cash_account_id'],
            'expense_category_id' => $category?->id,
            'movement_type' => $validated['movement_type'],
            'label' => $validated['label'],
            'amount' => $validated['amount'],
            'payment_mode' => $validated['payment_mode'],
            'reference' => $validated['reference'] ?? null,
            'description' => $validated['description'] ?? null,
            'movement_date' => $validated['movement_date'],
        ]);

        $this->recalculateAccountBalance($oldAccount);
        if ($oldAccount?->id !== $targetAccount->id) {
            $this->recalculateAccountBalance($targetAccount);
        }

        return redirect()->route('admin.comptabilite.caisses')->with('success', 'Mouvement mis à jour.');
    }

    public function destroyMovement(CashMovement $cashMovement)
    {
        $this->authorizeMovement($cashMovement);

        $account = $cashMovement->cashAccount;
        $cashMovement->delete();
        $this->recalculateAccountBalance($account);

        return redirect()->route('admin.comptabilite.caisses')->with('success', 'Mouvement supprimé.');
    }

    protected function authorizeAccount(CashAccount $cashAccount): void
    {
        if ($cashAccount->entreprise_id !== auth()->user()?->entreprise_id) {
            abort(403);
        }
    }

    protected function authorizeMovement(CashMovement $cashMovement): void
    {
        if ($cashMovement->entreprise_id !== auth()->user()?->entreprise_id) {
            abort(403);
        }
        if (!$this->canModifyMovement($cashMovement)) {
            abort(403, 'Ce mouvement ne peut plus être modifié par votre profil.');
        }
    }

    protected function canModifyMovement(CashMovement $cashMovement): bool
    {
        return $this->isFullCashAdmin()
            || ($this->isSecretary() && $cashMovement->movement_date?->isToday() === true);
    }

    protected function isFullCashAdmin(): bool
    {
        return auth()->user()?->hasRole('admin') === true;
    }

    protected function isSecretary(): bool
    {
        return auth()->user()?->hasRole('secretariat') === true
            || auth()->user()?->hasRole('secretary') === true;
    }

    protected function recalculateAccountBalance(?CashAccount $account): void
    {
        if (!$account) {
            return;
        }

        $balance = (float) $account->initial_balance + (float) $account->movements()
            ->sum(DB::raw("CASE WHEN movement_type = 'entry' THEN amount ELSE -amount END"));
        $account->update(['balance' => $balance]);
    }

    public function banques(Request $request)
    {
        $entrepriseId = auth()->user()?->entreprise_id;
        $dateDebut = $request->input('date_debut', now()->startOfMonth()->format('Y-m-d'));
        $dateFin = $request->input('date_fin', now()->format('Y-m-d'));

        $bankAccounts = BankAccount::where('entreprise_id', $entrepriseId)
            ->orderBy('name')
            ->get();

        if ($bankAccounts->isEmpty()) {
            $seedAccounts = [
                [
                    'name' => 'GTBank',
                    'bank_name' => 'GTCO',
                    'short_name' => 'GTCO',
                    'account_number' => '000000304075',
                    'current_balance' => 3240305,
                    'status' => 'credit',
                    'account_type' => 'compte_courant',
                    'logo_bg' => '#f74b3b',
                    'logo_text' => '#ffffff',
                    'last_transaction_label' => 'Vente produit',
                    'last_transaction_amount' => 3204305,
                    'last_transaction_direction' => 'up',
                ],
                [
                    'name' => 'UBA CI',
                    'bank_name' => 'UBA',
                    'short_name' => 'UBA',
                    'account_number' => '10859001602',
                    'current_balance' => 238500,
                    'status' => 'credit',
                    'account_type' => 'compte_courant',
                    'logo_bg' => '#fff5f3',
                    'logo_text' => '#e31b23',
                    'last_transaction_label' => 'Règlement client',
                    'last_transaction_amount' => 238500,
                    'last_transaction_direction' => 'up',
                ],
                [
                    'name' => 'Ecobank',
                    'bank_name' => 'Ecobank',
                    'short_name' => 'ECO',
                    'account_number' => '000012345678',
                    'current_balance' => 1264200,
                    'status' => 'debit',
                    'account_type' => 'epargne',
                    'logo_bg' => '#e9f3ff',
                    'logo_text' => '#0d5bd7',
                    'last_transaction_label' => 'Paiement fournisseur',
                    'last_transaction_amount' => 326000,
                    'last_transaction_direction' => 'down',
                ],
                [
                    'name' => 'Orange Money',
                    'bank_name' => 'Orange Money',
                    'short_name' => 'OM',
                    'account_number' => '0550002014',
                    'current_balance' => 680250,
                    'status' => 'credit',
                    'account_type' => 'mobile_money',
                    'logo_bg' => '#fff4e8',
                    'logo_text' => '#f59e0b',
                    'last_transaction_label' => 'Caisse mobile',
                    'last_transaction_amount' => 160250,
                    'last_transaction_direction' => 'up',
                ],
            ];

            foreach ($seedAccounts as $seed) {
                BankAccount::create([
                    'entreprise_id' => $entrepriseId,
                    'name' => $seed['name'],
                    'bank_name' => $seed['bank_name'],
                    'short_name' => $seed['short_name'],
                    'account_number' => $seed['account_number'],
                    'account_type' => $seed['account_type'],
                    'current_balance' => $seed['current_balance'],
                    'status' => $seed['status'],
                    'logo_bg' => $seed['logo_bg'],
                    'logo_text' => $seed['logo_text'],
                    'last_transaction_label' => $seed['last_transaction_label'],
                    'last_transaction_amount' => $seed['last_transaction_amount'],
                    'last_transaction_direction' => $seed['last_transaction_direction'],
                ]);
            }

            $bankAccounts = BankAccount::where('entreprise_id', $entrepriseId)->orderBy('name')->get();
        }

        $bankAccountsArray = $bankAccounts->map(function ($bank) use ($dateDebut, $dateFin) {
            $allTransactions = $bank->transactions()->get();
            $transactions = $bank->transactions()
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->get()
                ->map(function ($transaction) {
                    return [
                        'date' => $transaction->transaction_date?->format('d/m/Y') ?? '-',
                        'date_iso' => $transaction->transaction_date?->format('Y-m-d') ?? '',
                        'label' => $transaction->label,
                        'type' => $transaction->transaction_type,
                        'amount' => (float) $transaction->amount,
                        'description' => $transaction->description ?? '',
                    ];
                })
                ->values()
                ->all();
            $openingBalance = (float) $bank->current_balance - (float) $allTransactions
                ->filter(fn ($transaction) => $transaction->transaction_date?->format('Y-m-d') >= $dateDebut)
                ->sum(function ($transaction) {
                    return $transaction->transaction_type === 'credit' ? $transaction->amount : -$transaction->amount;
                });
            $periodBalance = $openingBalance + (float) $allTransactions
                ->filter(fn ($transaction) => ($transaction->transaction_date?->format('Y-m-d') ?? '') >= $dateDebut
                    && ($transaction->transaction_date?->format('Y-m-d') ?? '') <= $dateFin)
                ->sum(function ($transaction) {
                    return $transaction->transaction_type === 'credit' ? $transaction->amount : -$transaction->amount;
                });

            return [
                'id' => $bank->id,
                'name' => $bank->name,
                'short_name' => $bank->short_name ?? strtoupper(substr($bank->bank_name, 0, 2)),
                'account_number' => $bank->account_number,
                'amount' => $periodBalance,
                'current_amount' => (float) $bank->current_balance,
                'period_initial_balance' => $openingBalance,
                'status' => $bank->status,
                'bank' => $bank->bank_name,
                'logo_bg' => $bank->logo_bg ?? '#3B82F6',
                'logo_text' => $bank->logo_text ?? '#ffffff',
                'movement_label' => $bank->last_transaction_label ?? 'Solde initial',
                'movement_amount' => (float) $bank->last_transaction_amount,
                'movement_direction' => $bank->last_transaction_direction ?? 'up',
                'account_type' => $bank->account_type,
                'last_transaction' => $bank->updated_at?->format('Y-m-d') ?? now()->format('Y-m-d'),
                'transactions' => $transactions,
            ];
        })->all();

        if ($request->filled('type')) {
            $bankAccountsArray = array_values(array_filter($bankAccountsArray, fn ($account) => ($account['account_type'] ?? 'compte_courant') === $request->type));
        }

        if ($request->filled('solde')) {
            $bankAccountsArray = array_values(array_filter($bankAccountsArray, function ($account) use ($request) {
                $criterion = $request->solde;

                if ($criterion === 'credit') {
                    return (float) $account['amount'] >= 0;
                }

                if ($criterion === 'debit') {
                    return (float) $account['amount'] < 0;
                }

                if ($criterion === 'actif') {
                    return ($account['status'] ?? 'credit') === 'credit';
                }

                return true;
            }));
        }

        if ($request->filled('search')) {
            $needle = strtolower($request->search);
            $bankAccountsArray = array_values(array_filter($bankAccountsArray, function ($account) use ($needle) {
                return str_contains(strtolower(($account['name'] ?? '')), $needle)
                    || str_contains(strtolower(($account['bank'] ?? '')), $needle)
                    || str_contains(strtolower(($account['account_number'] ?? '')), $needle);
            }));
        }

        $totalBalance = array_sum(array_map(fn ($account) => (float) $account['amount'], $bankAccountsArray));
        $creditBalance = array_sum(array_map(fn ($account) => ($account['status'] ?? 'credit') === 'credit' ? (float) $account['amount'] : 0, $bankAccountsArray));
        $debitBalance = array_sum(array_map(fn ($account) => ($account['status'] ?? 'credit') === 'debit' ? (float) $account['amount'] : 0, $bankAccountsArray));

        $tableRows = array_map(function ($account) {
            return [
                $account['name'] ?? '-',
                $account['bank'] ?? '-',
                number_format((float) ($account['amount'] ?? 0), 0, ',', ' ') . ' FCFA',
                ($account['movement_label'] ?? 'Solde initial') . ' (' . number_format((float) ($account['movement_amount'] ?? 0), 0, ',', ' ') . ' FCFA)',
                (($account['status'] ?? 'credit') === 'credit' ? 'Crédit' : 'Débit'),
            ];
        }, $bankAccountsArray);

        return $this->page('comptabilite-module', [
            'title' => 'Banques',
            'subtitle' => 'Suivi des comptes bancaires, virements et rapprochements',
            'moduleType' => 'banques',
            'summary' => [
                ['label' => 'Total banques', 'value' => count($bankAccountsArray) . ' comptes', 'change' => 'comptes suivants'],
                ['label' => 'Solde total', 'value' => number_format($totalBalance, 0, ',', ' ') . ' FCFA', 'change' => 'solde cumulé'],
                ['label' => 'Solde crédits', 'value' => number_format($creditBalance, 0, ',', ' ') . ' FCFA', 'change' => '+ entrées'],
                ['label' => 'Solde débits', 'value' => number_format($debitBalance, 0, ',', ' ') . ' FCFA', 'change' => '- sorties'],
            ],
            'bankAccounts' => $bankAccountsArray,
            'filters' => [
                'search' => $request->search ?? '',
                'type' => $request->type ?? '',
                'solde' => $request->solde ?? '',
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
            ],
            'tableColumns' => ['Compte', 'Banque', 'Solde', 'Dernier mouvement', 'État'],
            'tableRows' => $tableRows,
        ]);
    }

    public function storeBankAccount(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'bank' => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:10'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_type' => ['required', 'in:compte_courant,epargne,mobile_money'],
            'initial_balance' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:credit,debit'],
        ]);

        $account = BankAccount::create([
            'entreprise_id' => auth()->user()->entreprise_id,
            'name' => $validated['name'],
            'bank_name' => $validated['bank'],
            'short_name' => strtoupper($validated['short_name']),
            'account_number' => $validated['account_number'],
            'account_type' => $validated['account_type'],
            'current_balance' => (float) $validated['initial_balance'],
            'status' => $validated['status'],
            'logo_bg' => ['#ff7a59', '#0ea5e9', '#22c55e', '#a855f7', '#f59e0b'][BankAccount::where('entreprise_id', auth()->user()->entreprise_id)->count() % 5],
            'logo_text' => '#ffffff',
            'last_transaction_label' => 'Solde initial',
            'last_transaction_amount' => (float) $validated['initial_balance'],
            'last_transaction_direction' => 'up',
        ]);

        BankTransaction::create([
            'entreprise_id' => auth()->user()->entreprise_id,
            'bank_account_id' => $account->id,
            'transaction_type' => $validated['status'],
            'label' => 'Solde initial',
            'amount' => (float) $validated['initial_balance'],
            'description' => 'Initialisation du compte bancaire',
            'transaction_date' => now()->toDateString(),
        ]);

        return redirect()->route('admin.comptabilite.banques')->with('success', 'Compte bancaire ajouté avec succès.');
    }

    public function updateBankAccount(Request $request, BankAccount $bankAccount)
    {
        if ($bankAccount->entreprise_id !== auth()->user()?->entreprise_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'bank' => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:10'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_type' => ['required', 'in:compte_courant,epargne,mobile_money'],
            'status' => ['required', 'in:credit,debit'],
        ]);

        $bankAccount->update([
            'name' => $validated['name'],
            'bank_name' => $validated['bank'],
            'short_name' => strtoupper($validated['short_name']),
            'account_number' => $validated['account_number'],
            'account_type' => $validated['account_type'],
            'status' => $validated['status'],
        ]);

        return redirect()->route('admin.comptabilite.banques')->with('success', 'Compte bancaire mis à jour.');
    }

    public function destroyBankAccount(BankAccount $bankAccount)
    {
        if ($bankAccount->entreprise_id !== auth()->user()?->entreprise_id) {
            abort(403);
        }

        $bankAccount->delete();

        return redirect()->route('admin.comptabilite.banques')->with('success', 'Compte bancaire supprimé.');
    }

    public function storeBankTransaction(Request $request)
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'transaction_type' => ['required', 'in:credit,debit'],
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $account = BankAccount::where('entreprise_id', auth()->user()->entreprise_id)
            ->findOrFail($validated['account_id']);

        $amount = (float) $validated['amount'];

        if ($validated['transaction_type'] === 'credit') {
            $account->current_balance = (float) $account->current_balance + $amount;
            $account->last_transaction_direction = 'up';
        } else {
            $account->current_balance = (float) $account->current_balance - $amount;
            $account->last_transaction_direction = 'down';
        }

        $account->status = $validated['transaction_type'];
        $account->last_transaction_label = $validated['label'];
        $account->last_transaction_amount = $amount;
        $account->save();

        BankTransaction::create([
            'entreprise_id' => auth()->user()->entreprise_id,
            'bank_account_id' => $account->id,
            'transaction_type' => $validated['transaction_type'],
            'label' => $validated['label'],
            'amount' => $amount,
            'description' => $validated['comment'] ?? null,
            'transaction_date' => now()->toDateString(),
        ]);

        return redirect()->route('admin.comptabilite.banques')->with('success', 'Mouvement bancaire enregistré.');
    }

    /**
     * Etat de TVA de la periode : taxe collectee, taxe deductible et solde.
     */
    public function declarationTva(Request $request)
    {
        $entrepriseId = auth()->user()?->entreprise_id;
        abort_unless($entrepriseId, 403);

        $from = Carbon::parse($request->input('date_debut', now()->startOfMonth()->toDateString()));
        $to = Carbon::parse($request->input('date_fin', now()->endOfMonth()->toDateString()));
        abort_unless($from->lte($to), 422, 'La période sélectionnée est invalide.');

        $taxService = app(TaxService::class);
        $entreprise = auth()->user()->entreprise;

        // Le fait generateur vient du reglage de l'entreprise, et peut etre
        // change ponctuellement depuis l'ecran.
        $basis = $request->input('basis')
            ?: data_get($entreprise?->settings ?? [], 'tax_basis', VatDeclarationService::BASIS_DEBITS);

        return $this->page('comptabilite-declaration-tva', [
            'title' => 'Déclaration de TVA',
            'subtitle' => 'Taxe collectée, taxe déductible et solde de la période',
            'declaration' => app(VatDeclarationService::class)->declare($entrepriseId, $from, $to, $basis),
            'basis' => $basis,
            'dateDebut' => $from->toDateString(),
            'dateFin' => $to->toDateString(),
            'currencySymbol' => $taxService->currencySymbolFor($entreprise),
            'decimals' => $taxService->decimalsFor($taxService->currencyFor($entreprise)),
        ]);
    }

    /**
     * Journal comptable : les pieces de la periode et la balance des comptes.
     */
    public function journalComptable(Request $request)
    {
        $entrepriseId = auth()->user()?->entreprise_id;
        abort_unless($entrepriseId, 403);

        $from = Carbon::parse($request->input('date_debut', now()->startOfMonth()->toDateString()));
        $to = Carbon::parse($request->input('date_fin', now()->endOfMonth()->toDateString()));
        abort_unless($from->lte($to), 422, 'La période sélectionnée est invalide.');

        $ledger = app(LedgerService::class);
        $ledger->ensureChartOfAccounts($entrepriseId);

        $entries = \App\Models\JournalEntry::withoutGlobalScope('entreprise')
            ->where('entreprise_id', $entrepriseId)
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->when($request->filled('journal'), fn ($q) => $q->where('journal', $request->input('journal')))
            ->with('lines.account')
            ->orderBy('entry_date')->orderBy('id')
            ->get();

        // Balance : un compte par ligne, avec ses totaux et son solde.
        $balance = \DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('ledger_accounts as a', 'a.id', '=', 'l.ledger_account_id')
            ->where('e.entreprise_id', $entrepriseId)
            ->whereBetween('e.entry_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('a.code', 'a.name', 'a.type')
            ->selectRaw('a.code, a.name, a.type, SUM(l.debit) AS debit, SUM(l.credit) AS credit')
            ->orderBy('a.code')
            ->get();

        $taxService = app(TaxService::class);

        return $this->page('comptabilite-journal', [
            'title' => 'Journal comptable',
            'subtitle' => 'Pièces en partie double et balance des comptes',
            'entries' => $entries,
            'balance' => $balance,
            'totalDebit' => round((float) $balance->sum('debit'), 2),
            'totalCredit' => round((float) $balance->sum('credit'), 2),
            'dateDebut' => $from->toDateString(),
            'dateFin' => $to->toDateString(),
            'journalFilter' => $request->input('journal'),
            'journaux' => ['VE' => 'Ventes', 'AC' => 'Achats', 'CA' => 'Caisse', 'BQ' => 'Banque', 'OD' => 'Opérations diverses'],
            'currencySymbol' => $taxService->currencySymbolFor(auth()->user()->entreprise),
            'decimals' => $taxService->decimalsFor($taxService->currencyFor(auth()->user()->entreprise)),
        ]);
    }

    public function rapports()
    {
        $entrepriseId = auth()->user()?->entreprise_id;

        $cashAccounts = CashAccount::where('entreprise_id', $entrepriseId)->orderBy('name')->get();
        $bankAccounts = BankAccount::where('entreprise_id', $entrepriseId)->orderBy('name')->get();

        $cashTotal = (float) $cashAccounts->sum('balance');
        $bankTotal = (float) $bankAccounts->sum('current_balance');
        $liquidityTotal = $cashTotal + $bankTotal;

        $creditTransactions = BankTransaction::where('entreprise_id', $entrepriseId)
            ->where('transaction_type', 'credit')
            ->where('is_transfer', false)
            ->sum('amount');
        $debitTransactions = BankTransaction::where('entreprise_id', $entrepriseId)
            ->where('transaction_type', 'debit')
            ->where('is_transfer', false)
            ->sum('amount');
        $transferVolume = (float) AccountTransfer::where('entreprise_id', $entrepriseId)->sum('amount');

        $cashMovements = CashMovement::where('entreprise_id', $entrepriseId)
            ->with('cashAccount')
            ->where('is_transfer', false)
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $recentTransfers = BankTransaction::where('entreprise_id', $entrepriseId)
            ->with('bankAccount')
            ->where('is_transfer', true)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $recentBankOperations = BankTransaction::where('entreprise_id', $entrepriseId)
            ->with('bankAccount')
            ->where('is_transfer', false)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $typeStats = collect([
            ['label' => 'Entrées', 'value' => number_format((float) $creditTransactions, 0, ',', ' ') . ' FCFA', 'class' => 'text-success'],
            ['label' => 'Sorties', 'value' => number_format((float) $debitTransactions, 0, ',', ' ') . ' FCFA', 'class' => 'text-danger'],
            ['label' => 'Virements', 'value' => number_format((float) $transferVolume, 0, ',', ' ') . ' FCFA', 'class' => 'text-primary'],
            ['label' => 'Solde réel', 'value' => number_format((float) $liquidityTotal, 0, ',', ' ') . ' FCFA', 'class' => 'text-dark'],
        ]);

        // Volume des opérations bancaires des six derniers mois, mois en cours inclus.
        // Un mois sans opération vaut zéro : aucune valeur n'est inventée.
        $chartStart = now()->startOfMonth()->subMonths(5);
        $bankTransactions = BankTransaction::where('entreprise_id', $entrepriseId)
            ->where('is_transfer', false)
            ->whereDate('transaction_date', '>=', $chartStart->toDateString())
            ->get(['transaction_date', 'amount']);

        $chartData = collect(range(0, 5))->map(function ($offset) use ($chartStart, $bankTransactions) {
            $month = $chartStart->copy()->addMonths($offset);

            return [
                'label' => ucfirst($month->translatedFormat('M Y')),
                'value' => (float) $bankTransactions->filter(fn ($transaction) => $transaction->transaction_date?->isSameMonth($month))->sum('amount'),
            ];
        })->values();

        // Répartition des volumes réels ; les types sans montant sont omis.
        $distribution = collect([
            ['label' => 'Entrées', 'value' => (float) $creditTransactions, 'color' => '#059669'],
            ['label' => 'Sorties', 'value' => (float) $debitTransactions, 'color' => '#dc2626'],
            ['label' => 'Transferts', 'value' => $transferVolume, 'color' => '#7c3aed'],
        ])->filter(fn ($item) => $item['value'] > 0);
        $distributionTotal = $distribution->sum('value');
        $distribution = $distribution->map(fn ($item) => [
            'label' => $item['label'],
            'amount' => $item['value'],
            'percent' => $distributionTotal > 0 ? round(($item['value'] / $distributionTotal) * 100) : 0,
            'value' => number_format((float) $item['value'], 0, ',', ' ') . ' FCFA',
            'color' => $item['color'],
        ])->values();

        $activeCash = $cashAccounts->filter(fn ($account) => (bool) $account->is_active)->values();

        return $this->page('comptabilite-module', [
            'title' => 'État de trésorerie',
            'subtitle' => 'Synthèse de la trésorerie, des caisses et des comptes bancaires',
            'moduleType' => 'rapports',
            'summary' => [
                ['label' => 'Liquidité totale', 'value' => number_format($liquidityTotal, 0, ',', ' ') . ' FCFA', 'change' => 'caisses et banques'],
                ['label' => 'Total caisse', 'value' => number_format($cashTotal, 0, ',', ' ') . ' FCFA', 'change' => $activeCash->count() . ' caisse(s) active(s)'],
                ['label' => 'Total banque', 'value' => number_format($bankTotal, 0, ',', ' ') . ' FCFA', 'change' => $bankAccounts->count() . ' compte(s) actif(s)'],
                ['label' => 'Réapprovisionnements', 'value' => number_format($transferVolume, 0, ',', ' ') . ' FCFA', 'change' => 'volume total des transferts'],
            ],
            'cashAccounts' => $cashAccounts,
            'bankAccounts' => $bankAccounts,
            'chartData' => $chartData,
            'distribution' => $distribution,
            'activeCash' => $activeCash,
            'typeStats' => $typeStats,
            'recentCashMovements' => $cashMovements,
            'recentTransfers' => $recentTransfers,
            'recentBankOperations' => $recentBankOperations,
        ]);
    }

    public function rapportFinancier(Request $request)
    {
        $entrepriseId = auth()->user()?->entreprise_id;
        $from = Carbon::parse($request->input('date_debut', now()->startOfYear()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('date_fin', now()->endOfYear()->toDateString()))->endOfDay();
        abort_unless($from->lte($to), 422, 'La période sélectionnée est invalide.');
        $invoices = CommercialInvoice::with('creator')->where('entreprise_id', $entrepriseId)
            ->whereBetween('created_at', [$from, $to])->get();
        $totalRealise = (float) $invoices->sum('amount');
        $encaisse = (float) $invoices->sum('paid_amount');
        $creance = max($totalRealise - $encaisse, 0);
        $commercialRows = $invoices->groupBy('created_by_user_id')->map(function ($items) {
            $total = (float) $items->sum('amount');
            $paid = (float) $items->sum('paid_amount');
            return ['user' => $items->first()->creator?->name ?? 'Utilisateur supprimé', 'invoices' => $total, 'services' => 0, 'total' => $total, 'paid' => $paid, 'remaining' => max($total - $paid, 0)];
        })->values();
        $commercialMonthly = $invoices->groupBy('created_by_user_id')->map(function ($items) use ($from, $to) {
            $values = [];
            $cursor = $from->copy()->startOfMonth();
            while ($cursor->lte($to)) {
                $values[] = (float) $items->filter(fn ($invoice) => $invoice->created_at?->isSameMonth($cursor))->sum('amount');
                $cursor->addMonth();
            }
            return ['label' => $items->first()->creator?->name ?? 'Utilisateur supprimé', 'values' => $values];
        })->values();
        $chartData = collect();
        $cursor = $from->copy()->startOfMonth();
        while ($cursor->lte($to)) {
            $items = $invoices->filter(fn ($invoice) => $invoice->created_at?->isSameMonth($cursor));
            $chartData->push(['label' => $cursor->translatedFormat('M Y'), 'value' => (float) $items->sum('amount')]);
            $cursor->addMonth();
        }
        $cashMovements = CashMovement::with('expenseCategory')->where('entreprise_id', $entrepriseId)->where('movement_type', 'exit')->whereBetween('movement_date', [$from->toDateString(), $to->toDateString()])->get();
        $bankExpenses = BankTransaction::where('entreprise_id', $entrepriseId)->where('transaction_type', 'debit')->where('is_transfer', false)->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])->get();
        $supplierInvoices = SupplierInvoice::where('entreprise_id', $entrepriseId)->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()])->get();
        $cashTotal = (float) $cashMovements->sum('amount');
        $bankTotal = (float) $bankExpenses->sum('amount');
        $supplierTotal = (float) $supplierInvoices->sum('total_amount');
        $expenseTotal = $cashTotal + $bankTotal + $supplierTotal;
        $expenseCategories = $cashMovements->groupBy(fn ($movement) => $movement->expenseCategory?->name ?? 'Non catégorisé')->map(fn ($items, $category) => ['source' => 'Caisse', 'category' => $category, 'amount' => (float) $items->sum('amount')])->values();
        if ($bankTotal > 0) $expenseCategories->prepend(['source' => 'Banque', 'category' => 'Non catégorisé', 'amount' => $bankTotal]);
        if ($supplierTotal > 0) $expenseCategories->push(['source' => 'Fournisseurs', 'category' => 'Achats fournisseurs', 'amount' => $supplierTotal]);
        $expenseMonths = collect();
        $cursor = $from->copy()->startOfMonth();
        while ($cursor->lte($to)) {
            $month = $cursor->format('Y-m');
            $cash = (float) $cashMovements->filter(fn ($item) => $item->movement_date?->format('Y-m') === $month)->sum('amount');
            $bank = (float) $bankExpenses->filter(fn ($item) => $item->transaction_date?->format('Y-m') === $month)->sum('amount');
            $suppliers = (float) $supplierInvoices->filter(fn ($item) => $item->invoice_date?->format('Y-m') === $month)->sum('total_amount');
            $expenseMonths->push(['label' => $cursor->translatedFormat('M Y'), 'cash' => $cash, 'bank' => $bank, 'suppliers' => $suppliers, 'total' => $cash + $bank + $suppliers]);
            $cursor->addMonth();
        }
        $observation = $expenseTotal > 0 ? 'Les dépenses cumulées s’élèvent à ' . number_format($expenseTotal, 0, ',', ' ') . ' FCFA. Les postes les plus importants doivent être surveillés afin de préserver la trésorerie.' : 'Aucune dépense enregistrée sur la période sélectionnée.';
        $savedObservations = data_get(auth()->user()->entreprise?->settings, 'financial_report_observations', []);

        return $this->page('comptabilite-module', [
            'title' => 'Rapport financier',
            'subtitle' => 'Analyse des performances commerciales et bancaires',
            'moduleType' => 'rapport_financier',
            'filters' => [
                'date_debut' => $from->toDateString(),
                'date_fin' => $to->toDateString(),
            ],
            'summary' => [
                ['label' => 'CA total réalisé', 'value' => number_format($totalRealise, 0, ',', ' ') . ' FCFA', 'change' => 'Total réalisé'],
                ['label' => 'Montant encaissé', 'value' => number_format($encaisse, 0, ',', ' ') . ' FCFA', 'change' => 'Encaisse'],
                ['label' => 'Créance totale', 'value' => number_format($creance, 0, ',', ' ') . ' FCFA', 'change' => 'Reste à encaisser'],
            ],
            'chartData' => $chartData,
            'commercialSeries' => [['label' => 'CA total', 'value' => $totalRealise, 'color' => '#3ecf8e'], ['label' => 'Encaissé', 'value' => $encaisse, 'color' => '#44b3ff'], ['label' => 'Créance', 'value' => $creance, 'color' => '#e76f51']],
            'commercialRows' => $commercialRows,
            'commercialMonthly' => $commercialMonthly,
            'expenseCategories' => $expenseCategories,
            'expenseMonths' => $expenseMonths,
            'expenseTotal' => $expenseTotal,
            'totalRealise' => $totalRealise,
            'encaisse' => $encaisse,
            'creance' => $creance,
            'expenseObservation' => $observation,
            'observations' => $savedObservations,
            'reportTotal' => $totalRealise,
        ]);
    }

    public function saveReportObservations(Request $request)
    {
        $validated = $request->validate([
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'general_observation' => ['nullable', 'string', 'max:5000'],
            'expense_observation' => ['nullable', 'string', 'max:5000'],
        ]);
        $entreprise = auth()->user()->entreprise;
        $settings = $entreprise->settings ?? [];
        $settings['financial_report_observations'] = [
            'general' => $validated['general_observation'] ?? '',
            'expenses' => $validated['expense_observation'] ?? '',
        ];
        $entreprise->update(['settings' => $settings]);
        return redirect()->route('admin.comptabilite.rapport_financier', [
            'date_debut' => $validated['date_debut'],
            'date_fin' => $validated['date_fin'],
        ])->with('success', 'Observations enregistrées.');
    }

    public function transfers(Request $request)
    {
        $entrepriseId = auth()->user()?->entreprise_id;
        $cashAccounts = CashAccount::where('entreprise_id', $entrepriseId)->orderBy('name')->get();
        $bankAccounts = BankAccount::where('entreprise_id', $entrepriseId)->orderBy('name')->get();

        $query = AccountTransfer::where('entreprise_id', $entrepriseId)->latest('transfer_date')->latest('id');
        if ($request->filled('date_debut')) {
            $query->whereDate('transfer_date', '>=', $request->date_debut);
        }
        if ($request->filled('date_fin')) {
            $query->whereDate('transfer_date', '<=', $request->date_fin);
        }

        $transfers = $query->limit(100)->get()->map(function (AccountTransfer $transfer) use ($cashAccounts, $bankAccounts) {
            $source = $transfer->source_type === 'cash'
                ? $cashAccounts->firstWhere('id', $transfer->source_id)
                : $bankAccounts->firstWhere('id', $transfer->source_id);
            $destination = $transfer->destination_type === 'cash'
                ? $cashAccounts->firstWhere('id', $transfer->destination_id)
                : $bankAccounts->firstWhere('id', $transfer->destination_id);

            return [
                'source' => $source?->name ?? 'Compte supprimé',
                'source_type' => $transfer->source_type === 'cash' ? 'Caisse' : 'Banque',
                'destination' => $destination?->name ?? 'Compte supprimé',
                'destination_type' => $transfer->destination_type === 'cash' ? 'Caisse' : 'Banque',
                'amount' => number_format((float) $transfer->amount, 0, ',', ' ') . ' FCFA',
                'date' => $transfer->transfer_date?->format('d/m/Y') ?? '-',
                'reference' => $transfer->reference ?? '-',
                'description' => $transfer->description,
            ];
        });

        return $this->page('transfer', [
            'title' => 'Transfert de montant',
            'subtitle' => 'Transférez des fonds entre caisses et comptes bancaires',
            'cashAccounts' => $cashAccounts,
            'bankAccounts' => $bankAccounts,
            'transfers' => $transfers,
            'filters' => [
                'date_debut' => $request->date_debut ?? '',
                'date_fin' => $request->date_fin ?? '',
            ],
        ]);
    }

    public function storeTransfer(Request $request)
    {
        $validated = $request->validate([
            'source_type' => ['required', 'in:cash,bank'],
            'source_id' => ['required', 'integer', 'min:1'],
            'destination_type' => ['required', 'in:cash,bank'],
            'destination_id' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'transfer_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['source_type'] === $validated['destination_type']
            && (int) $validated['source_id'] === (int) $validated['destination_id']) {
            return back()->withErrors(['destination_id' => 'Le compte source et le compte destination doivent être différents.'])->withInput();
        }

        $entrepriseId = auth()->user()?->entreprise_id;
        $amount = (float) $validated['amount'];

        DB::transaction(function () use ($validated, $entrepriseId, $amount) {
            $source = $validated['source_type'] === 'cash'
                ? CashAccount::where('entreprise_id', $entrepriseId)->lockForUpdate()->findOrFail($validated['source_id'])
                : BankAccount::where('entreprise_id', $entrepriseId)->lockForUpdate()->findOrFail($validated['source_id']);
            $destination = $validated['destination_type'] === 'cash'
                ? CashAccount::where('entreprise_id', $entrepriseId)->lockForUpdate()->findOrFail($validated['destination_id'])
                : BankAccount::where('entreprise_id', $entrepriseId)->lockForUpdate()->findOrFail($validated['destination_id']);

            if (($validated['source_type'] === 'cash' && ! $source->is_active)
                || ($validated['destination_type'] === 'cash' && ! $destination->is_active)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'source_id' => 'Les caisses utilisées doivent être ouvertes.',
                ]);
            }

            $sourceBalance = (float) ($validated['source_type'] === 'cash' ? $source->balance : $source->current_balance);
            if ($sourceBalance < $amount) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount' => 'Le montant dépasse le solde disponible du compte source.',
                ]);
            }

            $sourceName = $source->name;
            $destinationName = $destination->name;

            if ($validated['source_type'] === 'cash') {
                $source->decrement('balance', $amount);
                CashMovement::create([
                    'entreprise_id' => $entrepriseId,
                    'cash_account_id' => $source->id,
                    'movement_type' => 'exit',
                    'label' => 'Transfert vers ' . $destinationName,
                    'amount' => $amount,
                    'currency' => company_currency()['code'],
                    'payment_mode' => 'transfer',
                    'is_transfer' => true,
                    'description' => $validated['description'] ?? 'Transfert inter-comptes',
                    'movement_date' => $validated['transfer_date'],
                ]);
            } else {
                $source->decrement('current_balance', $amount);
                $source->update([
                    'status' => 'debit',
                    'last_transaction_label' => 'Transfert vers ' . $destinationName,
                    'last_transaction_amount' => $amount,
                    'last_transaction_direction' => 'down',
                ]);
                BankTransaction::create([
                    'entreprise_id' => $entrepriseId,
                    'bank_account_id' => $source->id,
                    'transaction_type' => 'debit',
                    'is_transfer' => true,
                    'label' => 'Transfert vers ' . $destinationName,
                    'amount' => $amount,
                    'description' => $validated['description'] ?? 'Transfert inter-comptes',
                    'transaction_date' => $validated['transfer_date'],
                ]);
            }

            if ($validated['destination_type'] === 'cash') {
                $destination->increment('balance', $amount);
                CashMovement::create([
                    'entreprise_id' => $entrepriseId,
                    'cash_account_id' => $destination->id,
                    'movement_type' => 'entry',
                    'label' => 'Transfert depuis ' . $sourceName,
                    'amount' => $amount,
                    'currency' => company_currency()['code'],
                    'payment_mode' => 'transfer',
                    'is_transfer' => true,
                    'description' => $validated['description'] ?? 'Transfert inter-comptes',
                    'movement_date' => $validated['transfer_date'],
                ]);
            } else {
                $destination->increment('current_balance', $amount);
                $destination->update([
                    'status' => 'credit',
                    'last_transaction_label' => 'Transfert depuis ' . $sourceName,
                    'last_transaction_amount' => $amount,
                    'last_transaction_direction' => 'up',
                ]);
                BankTransaction::create([
                    'entreprise_id' => $entrepriseId,
                    'bank_account_id' => $destination->id,
                    'transaction_type' => 'credit',
                    'is_transfer' => true,
                    'label' => 'Transfert depuis ' . $sourceName,
                    'amount' => $amount,
                    'description' => $validated['description'] ?? 'Transfert inter-comptes',
                    'transaction_date' => $validated['transfer_date'],
                ]);
            }

            $transfer = AccountTransfer::create([
                'entreprise_id' => $entrepriseId,
                'source_type' => $validated['source_type'],
                'source_id' => $source->id,
                'destination_type' => $validated['destination_type'],
                'destination_id' => $destination->id,
                'amount' => $amount,
                'description' => $validated['description'] ?? null,
                'transfer_date' => $validated['transfer_date'],
            ]);
            $transfer->update(['reference' => 'TRF-' . str_pad((string) $transfer->id, 6, '0', STR_PAD_LEFT)]);
        });

        return redirect()->route('admin.comptabilite.transfers')->with('success', 'Transfert effectué avec succès.');
    }

    public function expenseCategories()
    {
        $categories = ExpenseCategory::where('entreprise_id', auth()->user()?->entreprise_id)
            ->withCount('cashMovements')
            ->orderBy('name')
            ->get();

        return $this->page('expense-categories', [
            'title' => 'Catégories de dépenses',
            'subtitle' => 'Classez les sorties de caisse par nature de dépense',
            'categories' => $categories,
        ]);
    }

    public function storeExpenseCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        ExpenseCategory::create([
            'entreprise_id' => auth()->user()->entreprise_id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('admin.comptabilite.expenseCategories')->with('success', 'Catégorie ajoutée.');
    }

    public function updateExpenseCategory(Request $request, ExpenseCategory $expenseCategory)
    {
        $this->authorizeExpenseCategory($expenseCategory);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $expenseCategory->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.comptabilite.expenseCategories')->with('success', 'Catégorie mise à jour.');
    }

    public function destroyExpenseCategory(ExpenseCategory $expenseCategory)
    {
        $this->authorizeExpenseCategory($expenseCategory);
        if ($expenseCategory->cashMovements()->exists()) {
            return back()->withErrors(['category' => 'Cette catégorie est utilisée par des mouvements et ne peut pas être supprimée. Désactivez-la plutôt.']);
        }
        $expenseCategory->delete();

        return redirect()->route('admin.comptabilite.expenseCategories')->with('success', 'Catégorie supprimée.');
    }

    protected function authorizeExpenseCategory(ExpenseCategory $expenseCategory): void
    {
        if ($expenseCategory->entreprise_id !== auth()->user()?->entreprise_id) {
            abort(403);
        }
    }

    public function fixedAssets(Request $request)
        {
            $query = FixedAsset::where('entreprise_id', auth()->user()?->entreprise_id)->latest('acquisition_date');
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'ilike', "%{$search}%")
                        ->orWhere('reference', 'ilike', "%{$search}%")
                        ->orWhere('asset_category', 'ilike', "%{$search}%");
                });
            }

            $assets = $query->get()->map(function (FixedAsset $asset) {
                // diffInMonths est absolu : une acquisition future ne doit pas être amortie.
                $elapsedMonths = $asset->acquisition_date?->isPast() ? $asset->acquisition_date->diffInMonths(now()) : 0;
                $usefulLifeMonths = max(1, $asset->useful_life_years * 12);
                $depreciableValue = max(0, (float) $asset->acquisition_value - (float) $asset->residual_value);
                $depreciation = min($depreciableValue, $depreciableValue * min($elapsedMonths, $usefulLifeMonths) / $usefulLifeMonths);
                $asset->depreciation_amount = $depreciation;
                $asset->depreciation_rate = $depreciableValue > 0 ? $depreciation / $depreciableValue * 100 : 0;
                $asset->net_value = max((float) $asset->residual_value, (float) $asset->acquisition_value - $depreciation);
                return $asset;
            });

            return $this->page('fixed-assets', [
                'title' => 'Immobilisations',
                'subtitle' => 'Gérez les biens durables et leur valeur nette comptable',
                'assets' => $assets,
                'summary' => [
                    'count' => $assets->count(),
                    'gross' => $assets->sum(fn ($asset) => (float) $asset->acquisition_value),
                    'net' => $assets->sum(fn ($asset) => (float) $asset->net_value),
                    'active' => $assets->where('status', 'active')->count(),
                ],
                'filters' => $request->only(['status', 'search']),
            ]);
        }

        public function storeFixedAsset(Request $request)
        {
            $validated = $this->validateFixedAsset($request);
            FixedAsset::create(array_merge($validated, ['entreprise_id' => auth()->user()->entreprise_id]));
            return redirect()->route('admin.comptabilite.fixedAssets')->with('success', 'Immobilisation ajoutée.');
        }

        public function updateFixedAsset(Request $request, FixedAsset $fixedAsset)
        {
            $this->authorizeFixedAsset($fixedAsset);
            $fixedAsset->update($this->validateFixedAsset($request));
            return redirect()->route('admin.comptabilite.fixedAssets')->with('success', 'Immobilisation mise à jour.');
        }

        public function destroyFixedAsset(FixedAsset $fixedAsset)
        {
            $this->authorizeFixedAsset($fixedAsset);
            $fixedAsset->delete();
            return redirect()->route('admin.comptabilite.fixedAssets')->with('success', 'Immobilisation supprimée.');
        }

        protected function validateFixedAsset(Request $request): array
        {
            return $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'asset_category' => ['required', 'string', 'max:100'],
                'reference' => ['nullable', 'string', 'max:100'],
                'supplier' => ['nullable', 'string', 'max:255'],
                'location' => ['nullable', 'string', 'max:255'],
                'acquisition_date' => ['required', 'date'],
                'acquisition_value' => ['required', 'numeric', 'min:0.01'],
                'residual_value' => ['nullable', 'numeric', 'min:0'],
                'useful_life_years' => ['required', 'integer', 'min:1', 'max:100'],
                'status' => ['required', 'in:active,sold,retired'],
                'description' => ['nullable', 'string'],
            ]);
        }

    protected function authorizeFixedAsset(FixedAsset $fixedAsset): void
        {
            if ($fixedAsset->entreprise_id !== auth()->user()?->entreprise_id) {
                abort(403);
            }
        }

    public function supplierInvoices(Request $request)
            {
                $query = SupplierInvoice::where('entreprise_id', auth()->user()?->entreprise_id)->latest();
                if ($request->filled('search')) {
                    $search = $request->search;
                    $query->where(function ($builder) use ($search) {
                        $builder->where('invoice_number', 'ilike', "%{$search}%")
                            ->orWhere('supplier_name', 'ilike', "%{$search}%")
                            ->orWhere('original_filename', 'ilike', "%{$search}%");
                    });
                }

                return $this->page('supplier-invoices', [
                    'title' => 'Factures fournisseurs',
                    'subtitle' => 'Import des factures PDF, contrôle des données extraites et suivi des achats.',
                    'invoices' => $query->get(),
                    'filters' => $request->only('search'),
                ]);
            }

            public function importSupplierInvoice(Request $request)
            {
                $request->validate([
                    'invoice' => ['required', 'file', 'mimes:pdf', 'max:10240'],
                ]);

                $file = $request->file('invoice');
                $storedPath = $file->store('supplier-invoices', 'public');
                $absolutePath = Storage::disk('public')->path($storedPath);
                $rawText = $this->extractPdfText($absolutePath);
                $data = $this->extractInvoiceData($rawText, $file->getClientOriginalName());

                SupplierInvoice::create([
                    'entreprise_id' => auth()->user()->entreprise_id,
                    'invoice_number' => $data['invoice_number'],
                    'supplier_name' => $data['supplier_name'],
                    'supplier_tax_id' => $data['supplier_tax_id'],
                    'invoice_date' => $data['invoice_date'],
                    'due_date' => $data['due_date'],
                    'subtotal' => $data['subtotal'],
                    'total_ht' => $data['total_ht'],
                    'tax_amount' => $data['tax_amount'],
                    'total_amount' => $data['total_amount'],
                    'currency' => $data['currency'],
                    'status' => $rawText !== '' ? 'imported' : 'needs_review',
                    'file_path' => $storedPath,
                    'original_filename' => $file->getClientOriginalName(),
                    'extracted_data' => $data,
                    'raw_text' => $rawText,
                ]);

                return redirect()->route('admin.comptabilite.supplierInvoices')->with('success', 'Facture importée. Vérifiez les données extraites dans le détail.');
            }

            public function destroySupplierInvoice(SupplierInvoice $supplierInvoice)
            {
                $this->authorizeSupplierInvoice($supplierInvoice);
                $this->deleteSupplierInvoiceFile($supplierInvoice);
                $supplierInvoice->delete();

                return redirect()->route('admin.comptabilite.supplierInvoices')->with('success', 'Facture supprimée.');
            }

            public function destroySupplierInvoices(Request $request)
            {
                $data = $request->validate([
                    'invoice_ids' => ['required', 'array', 'min:1'],
                    'invoice_ids.*' => ['integer'],
                ]);
                $invoices = SupplierInvoice::where('entreprise_id', auth()->user()?->entreprise_id)
                    ->whereIn('id', $data['invoice_ids'])
                    ->get();

                foreach ($invoices as $invoice) {
                    $this->deleteSupplierInvoiceFile($invoice);
                    $invoice->delete();
                }

                return redirect()->route('admin.comptabilite.supplierInvoices')
                    ->with('success', $invoices->count() . ' facture(s) supprimée(s).');
            }

            protected function deleteSupplierInvoiceFile(SupplierInvoice $supplierInvoice): void
            {
                if ($supplierInvoice->file_path && Storage::disk('public')->exists($supplierInvoice->file_path)) {
                    Storage::disk('public')->delete($supplierInvoice->file_path);
                }
            }

            public function showSupplierInvoice(SupplierInvoice $supplierInvoice)
            {
                $this->authorizeSupplierInvoice($supplierInvoice);
                return $this->page('supplier-invoice-show', [
                    'title' => 'Détail de la facture',
                    'invoice' => $supplierInvoice,
                    'regimes' => \App\Models\TaxRate::regimes(),
                ]);
            }

            /**
             * Le regime lu dans un PDF n'est qu'une supposition. Cette action
             * permet a un humain de le confirmer avant toute declaration.
             */
            public function updateSupplierInvoiceRegime(Request $request, SupplierInvoice $supplierInvoice)
            {
                $this->authorizeSupplierInvoice($supplierInvoice);

                abort_if($supplierInvoice->fne_status === 'certified', 422, 'Cette facture est déjà certifiée, son régime ne peut plus changer.');

                $validated = $request->validate([
                    'tax_regime' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Models\TaxRate::regimes()))],
                ]);

                $supplierInvoice->update(['tax_regime' => $validated['tax_regime']]);

                // L'ecriture comptable est passee une fois qu'un humain a
                // valide la facture : les donnees lues dans un PDF ne suffisent
                // pas a engager la comptabilite.
                app(AccountingPoster::class)->postSupplierInvoice($supplierInvoice->refresh(), auth()->id());

                return back()->with('success', 'Le régime fiscal de la facture a été confirmé et l’écriture comptable enregistrée.');
            }

            public function certifySupplierInvoice(SupplierInvoice $supplierInvoice, FneCertificationService $fne)
            {
                $this->authorizeSupplierInvoice($supplierInvoice);
                $data = $supplierInvoice->extracted_data ?: [];
                $taxService = app(TaxService::class);

                // Sans regime confirme, on ne declare pas : un zero extrait d'un
                // PDF ne prouve pas une exoneration.
                if (! $taxService->isDeclarable($supplierInvoice->tax_regime)) {
                    return back()->withErrors(['fne' => "Le régime fiscal de cette facture n'est pas confirmé. Renseignez-le sur la fiche avant de certifier."]);
                }

                $fiscalCode = $taxService->fiscalCodeFor($supplierInvoice->tax_regime);

                $items = collect($data['items'] ?? [])->map(fn ($item) => [
                    'taxes' => [$fiscalCode],
                    'reference' => $item['reference'] ?? 'ACHAT',
                    'description' => $item['description'] ?? $item['name'] ?? 'Article fournisseur',
                    'quantity' => (float) ($item['quantity'] ?? 1),
                    'amount' => (float) ($item['amount'] ?? $item['unit_price'] ?? 0),
                    'discount' => (float) ($item['discount'] ?? 0), 'measurementUnit' => $item['unit'] ?? 'pcs',
                ])->filter(fn ($item) => $item['amount'] > 0)->values()->all();
                if (!$items) {
                    return back()->withErrors(['fne' => 'Les lignes de cette facture PDF sont incomplètes. Vérifiez les données extraites avant certification.']);
                }
                try {
                    $fne->certify($supplierInvoice, 'purchase', [
                        'invoiceType' => 'purchase', 'paymentMethod' => 'deferred', 'template' => 'B2B',
                        'isRne' => false, 'clientCompanyName' => $supplierInvoice->supplier_name ?: 'Fournisseur',
                        'clientNcc' => $supplierInvoice->supplier_tax_id, 'pointOfSale' => config('fne.point_of_sale'),
                        'establishment' => config('fne.establishment'), 'commercialMessage' => '',
                        'footer' => '', 'items' => $items, 'discount' => 0,
                    ]);
                    return back()->with('success', 'Facture fournisseur certifiée par la FNE.');
                } catch (\Throwable $e) {
                    return back()->withErrors(['fne' => $e->getMessage()]);
                }
            }

            public function pdfSupplierInvoice(SupplierInvoice $supplierInvoice)
            {
                $this->authorizeSupplierInvoice($supplierInvoice);
                abort_unless(Storage::disk('public')->exists($supplierInvoice->file_path), 404);
                return response()->file(Storage::disk('public')->path($supplierInvoice->file_path), [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . basename($supplierInvoice->original_filename) . '"',
                ]);
            }

            public function printSupplierInvoice(SupplierInvoice $supplierInvoice)
            {
                $this->authorizeSupplierInvoice($supplierInvoice);

                return view('admin.supplier-invoice-print', [
                    'invoice' => $supplierInvoice,
                    'company' => auth()->user()->entreprise,
                ]);
            }

            public function printFneSupplierInvoice(SupplierInvoice $supplierInvoice)
            {
                $this->authorizeSupplierInvoice($supplierInvoice);
                abort_unless($supplierInvoice->fne_status === 'certified', 404);

                return view('admin.fne-invoice-print', [
                    'invoice' => $supplierInvoice,
                    'company' => auth()->user()->entreprise,
                    'documentType' => 'Achat',
                ]);
            }

            protected function authorizeSupplierInvoice(SupplierInvoice $supplierInvoice): void
            {
                if ($supplierInvoice->entreprise_id !== auth()->user()?->entreprise_id) {
                    abort(403);
                }
            }

            protected function extractPdfText(string $path): string
            {
                $contents = file_get_contents($path);
                preg_match_all('/stream\s*(.*?)\s*endstream/s', $contents, $streams);
                $text = '';
                $unicodeMap = [];

                foreach ($streams[1] as $stream) {
                    $decoded = @gzuncompress(trim($stream));
                    $decoded = $decoded !== false ? $decoded : $stream;
                    if (strpos($decoded, 'begincmap') !== false) {
                        preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $decoded, $cmapEntries, PREG_SET_ORDER);
                        foreach ($cmapEntries as $entry) {
                            $unicodeMap[strtoupper($entry[1])] = $this->unicodeCodepointToUtf8(hexdec($entry[2]));
                        }
                    }
                }

                foreach ($streams[1] as $stream) {
                    $jpegStart = strpos($stream, "\xFF\xD8\xFF");
                    $jpegEnd = strrpos($stream, "\xFF\xD9");
                    if ($jpegStart !== false && $jpegEnd !== false && $jpegEnd > $jpegStart) {
                        $imagePath = tempnam(sys_get_temp_dir(), 'invoice-') . '.jpg';
                        file_put_contents($imagePath, substr($stream, $jpegStart, $jpegEnd - $jpegStart + 2));
                        $text .= ' ' . $this->runInvoiceOcr($imagePath);
                        @unlink($imagePath);
                    }

                    $decoded = @gzuncompress(trim($stream));
                    $decoded = $decoded !== false ? $decoded : $stream;
                    preg_match_all('/\((.*?)\)\s*Tj|\[(.*?)\]\s*TJ|<([0-9A-Fa-f]+)>\s*Tj/s', $decoded, $matches, PREG_SET_ORDER);
                    foreach ($matches as $match) {
                        if ($match[1] !== '') {
                            $value = $match[1];
                        } elseif ($match[2] !== '') {
                            $value = '';
                            preg_match_all('/<([0-9A-Fa-f]+)>/', $match[2], $hexStrings);
                            foreach ($hexStrings[1] as $hexString) {
                                $chunks = str_split(strtoupper($hexString), 4);
                                foreach ($chunks as $chunk) {
                                    $value .= $unicodeMap[$chunk] ?? (strlen($chunk) % 2 === 0 ? hex2bin($chunk) : '');
                                }
                            }
                        } else {
                            $value = $unicodeMap[strtoupper($match[3])] ?? hex2bin($match[3]);
                        }
                        $value = preg_replace('/\([^)]*\)\s*\d+\s*/', ' ', $value);
                        $value = str_replace(['\\(', '\\)', '\\n', '\\r'], ['(', ')', ' ', ' '], $value);
                        $text .= ' ' . $value;
                    }
                }

                return trim(preg_replace('/\s+/', ' ', $text));
            }

            protected function runInvoiceOcr(string $imagePath): string
            {
                $tesseract = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
                if (! is_file($tesseract)) {
                    return '';
                }

                $command = escapeshellarg($tesseract) . ' ' . escapeshellarg($imagePath) . ' stdout -l eng 2>NUL';
                $output = shell_exec($command);
                return is_string($output) ? trim($output) : '';
            }

            protected function unicodeCodepointToUtf8(int $codepoint): string
            {
                if ($codepoint < 0x80) {
                    return chr($codepoint);
                }
                if ($codepoint < 0x800) {
                    return chr(0xC0 | ($codepoint >> 6))
                        . chr(0x80 | ($codepoint & 0x3F));
                }
                if ($codepoint < 0x10000) {
                    return chr(0xE0 | ($codepoint >> 12))
                        . chr(0x80 | (($codepoint >> 6) & 0x3F))
                        . chr(0x80 | ($codepoint & 0x3F));
                }
                return chr(0xF0 | ($codepoint >> 18))
                    . chr(0x80 | (($codepoint >> 12) & 0x3F))
                    . chr(0x80 | (($codepoint >> 6) & 0x3F))
                    . chr(0x80 | ($codepoint & 0x3F));
            }

            protected function extractInvoiceData(string $text, string $filename): array
            {
                if (preg_match_all('/[\x13-\x1C]/', $text) > 3) {
                    $text = preg_replace_callback('/[\x13-\x1C]/', static function (array $match): string {
                        return chr(ord($match[0]) + 0x1D);
                    }, $text) ?: $text;
                }
                if (substr_count($text, "\0") > 10) {
                    $text = str_replace("\0", '', $text);
                }
                $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', $text) ?: $text;
                $text = str_replace(["\xc2\xa0", "\xe2\x80\xaf"], ' ', $text);
                $find = static function (array $patterns) use ($text): ?string {
                    foreach ($patterns as $pattern) {
                        if (preg_match($pattern, $text, $matches)) {
                            return trim($matches[1]);
                        }
                    }
                    return null;
                };
                $filenameStem = pathinfo($filename, PATHINFO_FILENAME);
                $filenameReference = preg_split('/[_\s]+/', $filenameStem)[0] ?? '';
                $number = preg_match('/^(?=.*\d)[A-Z0-9-]{8,}$/i', $filenameReference)
                    ? $filenameReference
                    : $find([
                    '/(?:facture|invoice|référence|reference|ref\.?)\s*(?:n[°o]\.?)?\s*[:#-]?\s*([A-Z0-9][A-Z0-9\/_-]{3,})/i',
                    '/(?:N°|No|N° facture)\s*[:#-]?\s*([A-Z0-9][A-Z0-9\/_-]+)/i',
                    '/(\d{8,})/',
                ]);
                $date = $find(['/date\s*(?:de facture)?\s*[:\-]?\s*(\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4})/i']);
                if (! $date && preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $filenameStem, $filenameDate)) {
                    $date = $filenameDate[1];
                }
                $normalizedDate = $this->normalizeInvoiceDate($date);
                if (! $normalizedDate && preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $filenameStem, $filenameDate)) {
                    $normalizedDate = $filenameDate[1];
                }
                $findAmount = static function (array $patterns) use ($text): ?string {
                    foreach ($patterns as $pattern) {
                        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
                            foreach ($matches as $match) {
                                if (isset($match[1]) && preg_match('/\d/', $match[1])) {
                                    return trim($match[1]);
                                }
                            }
                        }
                    }
                    return null;
                };
                $totalHt = $findAmount([
                    '/(?:total\s*(?:ht|hors\s+taxe)|montant\s*(?:ht|hors\s+taxe)|sous[\s-]*total)\s*[:=\-]?\s*([\d][\d\s.,]*)\s*(?:FCFA|CFA|XOF|EUR|USD|€)?/iu',
                ]);
                $totalTtc = $findAmount([
                    '/(?:total\s*(?:ttc|toutes?\s+taxes?\s+comprises?)|montant\s*(?:ttc|toutes?\s+taxes?\s+comprises?)|net\s*[àa]\s*payer|total\s*[àa]\s*payer)\s*[:=\-]?\s*([\d][\d\s.,]*)\s*(?:FCFA|CFA|XOF|EUR|USD|€)?/iu',
                    '/\b(?:montant|total)?\s*ttc\s*[:=\-]?\s*([\d][\d\s.,]*)\s*(?:FCFA|CFA|XOF|EUR|USD|€)?/iu',
                ]);
                if (! $totalTtc && preg_match_all('/([\d][\d\s.,]*)\s*(?:FCFA|CFA|XOF|EUR|USD|€)\b/iu', $text, $currencyAmounts)) {
                    $amounts = array_values(array_filter(array_map('trim', $currencyAmounts[1]), static fn ($amount) => preg_match('/\d/', $amount)));
                    $totalTtc = $amounts ? end($amounts) : null;
                }
                if (! $totalTtc && preg_match_all('/\b\d[\d\s.,]{2,}\b/', $text, $numericAmounts)) {
                    $amounts = array_values(array_filter(array_map('trim', $numericAmounts[0]), static fn ($amount) => strlen(preg_replace('/\D/', '', $amount)) >= 3));
                    $totalTtc = $amounts ? end($amounts) : null;
                }
                if (! $totalHt && preg_match_all('/\b\d{4,}(?:[.,]\d+)?\b/', $text, $plainAmounts)) {
                    $amounts = array_values(array_filter($plainAmounts[0], static fn ($amount) => (float) $amount > 100));
                    $ttcValue = $this->normalizeInvoiceAmount($totalTtc);
                    $htCandidates = array_values(array_filter($amounts, static fn ($amount) => $ttcValue === null || (float) $amount < $ttcValue));
                    if ($htCandidates) {
                        $totalHt = (string) max(array_map('floatval', $htCandidates));
                    }
                }
                $supplier = $find([
                    '/(?:fournisseur|vendeur|supplier|seller)\s*[:\-]\s*([^\r\n]{2,100})/i',
                    '/(?:à|a)\s*l[\'’]attention\s+de\s*[:\-]?\s*([^\r\n]{2,100})/i',
                    '/^([A-Z][A-Z0-9 &.,\'’-]{2,80})(?:\s+facture|\s+invoice)/im',
                    '/\b([A-Z][a-z]+[A-Z][a-z]+)\b/',
                ]);

                return [
                    'invoice_number' => $number,
                    'supplier_name' => $supplier ?: pathinfo($filename, PATHINFO_FILENAME),
                    'supplier_tax_id' => $find(['/(?:nif|ifu|cc)\s*[:\-]?\s*([A-Z0-9-]+)/i']),
                    'invoice_date' => $normalizedDate,
                    'due_date' => null,
                    'subtotal' => $this->normalizeInvoiceAmount($totalHt),
                    'total_ht' => $this->normalizeInvoiceAmount($totalHt),
                    'tax_amount' => null,
                    'total_amount' => $this->normalizeInvoiceAmount($totalTtc),
                    'currency' => $this->detectInvoiceCurrency($text),
                ];
            }

            protected function detectInvoiceCurrency(string $text): string
            {
                if (preg_match('/(?:\bFCFA\b|\bCFA\b|\bXOF\b|F\s*[C)]\s*F\s*A)/iu', $text)) {
                    return 'XOF';
                }
                if (preg_match('/(?:\bEUR\b|€)/iu', $text)) {
                    return 'EUR';
                }
                if (preg_match('/\bUSD\b/iu', $text)) {
                    return 'USD';
                }

                // A defaut de mention lisible, on retient la devise de l'entreprise.
                return company_currency()['code'];
            }

            protected function normalizeInvoiceDate(?string $value): ?string
            {
                if (! $value) {
                    return null;
                }
                $parts = preg_split('/[\/-]/', $value);
                if (count($parts) !== 3) {
                    return null;
                }
                if (strlen($parts[0]) === 4) {
                    [$year, $month, $day] = $parts;
                } else {
                    [$day, $month, $year] = $parts;
                }
                $year = strlen($year) === 2 ? '20' . $year : $year;
                return checkdate((int) $month, (int) $day, (int) $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
            }

            protected function normalizeInvoiceAmount(?string $value): ?float
            {
                if (! $value) {
                    return null;
                }
                $value = str_replace([" ", "\xc2\xa0", "\xe2\x80\xaf", "'"], '', trim($value));
                $lastComma = strrpos($value, ',');
                $lastDot = strrpos($value, '.');
                if ($lastComma !== false && $lastDot !== false) {
                    if ($lastComma > $lastDot) {
                        $value = str_replace('.', '', $value);
                        $value = str_replace(',', '.', $value);
                    } else {
                        $value = str_replace(',', '', $value);
                    }
                } elseif ($lastComma !== false) {
                    $value = str_replace(',', '.', $value);
                }
                return (float) $value;
            }
    protected function buildComptabiliteHub(): array
    {
        return [
            'summary' => ['total' => 13, 'available' => 12, 'planned' => 1, 'ohada' => 0],
            'sections' => [[
                'key' => 'finance',
                'title' => 'Finance',
                'subtitle' => 'Modules de trésorerie, banque, reporting et caisse',
                'icon' => 'bi-cash-stack',
                'items' => [
                    $this->makeModule('Tableau Comptabilité', 'Pilotage avancé de la trésorerie, des flux, des achats et des actifs', route('admin.comptabilite.tableau'), 'Pilotage', 'bi-graph-up-arrow', 'indigo', 'tableau comptabilite dashboard pilotage flux analyse', true, true),
                    $this->makeModule('Caisses', 'Gestion de trésorerie et des caisses', route('admin.comptabilite.caisses'), 'Finance', 'bi-cash-stack', 'blue', 'socle finance tresorerie caisse', true, true),
                    $this->makeModule('Banques', 'Gestion des comptes bancaires', route('admin.comptabilite.banques'), 'Banque', 'bi-bank', 'green', 'socle banque comptes bancaires', true, true),
                    $this->makeModule('État trésorerie', 'Situation globale de trésorerie', route('admin.comptabilite.rapports'), 'Analyse', 'bi-pie-chart', 'purple', 'socle analyse tresorerie etat', true, true),
                    $this->makeModule('Rapport financier', 'Analyses et synthèses financières', route('admin.comptabilite.rapport_financier'), 'Rapport', 'bi-file-earmark-text', 'orange', 'socle rapport financier synthese', true, true),
                    $this->makeModule('Bilans financiers', 'Bilan et compte de résultat des exercices clos', route('admin.bilans.index'), 'Bilan', 'bi-clipboard-data', 'green', 'bilan financier exercice annuel resultat cloture', true, true),
                    $this->makeModule('Journal comptable', 'Écritures en partie double et balance des comptes', route('admin.comptabilite.journal'), 'Comptable', 'bi-journal-text', 'indigo', 'journal ecritures partie double balance grand livre comptes', true, true),
                    $this->makeModule('Déclaration de TVA', 'Taxe collectée, taxe déductible et solde de la période', route('admin.comptabilite.declaration_tva'), 'Fiscal', 'bi-percent', 'orange', 'tva taxe declaration fiscal collectee deductible solde', true, true),
                    $this->makeModule('Transfert de montant', 'Mouvements entre comptes', route('admin.comptabilite.transfers'), 'Transfert', 'bi-arrow-left-right', 'teal', 'socle transfert mouvements comptes', true, true),
                    $this->makeModule('Catégorie dépenses', 'Gestion des catégories de dépenses', route('admin.comptabilite.expenseCategories'), 'Catégorie', 'bi-tags', 'red', 'socle depenses categories', true, true),
                    $this->makeModule('Immobilisations', 'Gestion des actifs immobilisés', route('admin.comptabilite.fixedAssets'), 'Actif', 'bi-building', 'purple', 'socle immobilisations actifs', true, true),
                    $this->makeModule('Factures ventes', 'Gestion des factures de ventes', route('admin.commercial.module', 'factures'), 'Ventes', 'bi-receipt', 'cyan', 'socle ventes factures clients', true, true),
                    $this->makeModule('Import facture fournisseur', 'Téléversement PDF et enregistrement des factures fournisseurs', route('admin.comptabilite.supplierInvoices'), 'Achats', 'bi-file-earmark-pdf', 'pink', 'socle achats factures fournisseurs import upload', true, true),
                    $this->makeModule('Factures achats', 'Consultation et suivi des factures fournisseurs', route('admin.comptabilite.supplierInvoices'), 'Achats', 'bi-cart', 'purple', 'socle achats factures fournisseurs', true, true),
                ],
            ]],
            'workflow' => [],
        ];
    }

    protected function makeModule(string $title, string $description, string $url, string $badge, string $icon, string $color, string $keywords, bool $isOhada, bool $isAvailable): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'badge' => $badge,
            'icon' => $icon,
            'color' => $color,
            'keywords' => $keywords,
            'is_ohada' => $isOhada,
            'status' => $isAvailable ? 'available' : 'planned',
            'status_label' => $isAvailable ? 'Disponible' : 'A activer',
        ];
    }
}
