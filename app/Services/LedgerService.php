<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\LedgerAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Tenue du journal en partie double.
 *
 * Toute piece doit s'equilibrer : la somme des debits egale celle des credits.
 * Le service refuse d'enregistrer une piece qui ne respecte pas cette regle,
 * plutot que de laisser une comptabilite fausse s'installer.
 */
class LedgerService
{
    /** Plan comptable par defaut, aligne sur le referentiel SYSCOHADA. */
    private const DEFAULT_ACCOUNTS = [
        ['code' => '411000', 'name' => 'Clients', 'type' => 'asset', 'role' => LedgerAccount::ROLE_CUSTOMERS],
        ['code' => '401000', 'name' => 'Fournisseurs', 'type' => 'liability', 'role' => LedgerAccount::ROLE_SUPPLIERS],
        ['code' => '443100', 'name' => 'TVA facturée sur ventes', 'type' => 'liability', 'role' => LedgerAccount::ROLE_VAT_COLLECTED],
        ['code' => '445200', 'name' => 'TVA récupérable sur achats', 'type' => 'asset', 'role' => LedgerAccount::ROLE_VAT_DEDUCTIBLE],
        ['code' => '444100', 'name' => 'État, TVA due', 'type' => 'liability', 'role' => LedgerAccount::ROLE_VAT_DUE],
        ['code' => '571000', 'name' => 'Caisse', 'type' => 'asset', 'role' => LedgerAccount::ROLE_CASH],
        ['code' => '521000', 'name' => 'Banques', 'type' => 'asset', 'role' => LedgerAccount::ROLE_BANK],
        ['code' => '701000', 'name' => 'Ventes', 'type' => 'revenue', 'role' => LedgerAccount::ROLE_SALES],
        ['code' => '601000', 'name' => 'Achats', 'type' => 'expense', 'role' => LedgerAccount::ROLE_PURCHASES],
    ];

    public function __construct(private DocumentNumberService $numbers)
    {
    }

    /** Cree le plan comptable d'une entreprise si elle n'en a pas. */
    public function ensureChartOfAccounts(int $entrepriseId): void
    {
        foreach (self::DEFAULT_ACCOUNTS as $account) {
            LedgerAccount::withoutGlobalScope('entreprise')->firstOrCreate(
                ['entreprise_id' => $entrepriseId, 'code' => $account['code']],
                $account + ['entreprise_id' => $entrepriseId, 'is_active' => true]
            );
        }
    }

    /** Retrouve un compte par son role, sans coder de numero dans le metier. */
    public function account(int $entrepriseId, string $role): LedgerAccount
    {
        $account = LedgerAccount::withoutGlobalScope('entreprise')
            ->where('entreprise_id', $entrepriseId)->where('role', $role)->first();

        if (! $account) {
            $this->ensureChartOfAccounts($entrepriseId);

            $account = LedgerAccount::withoutGlobalScope('entreprise')
                ->where('entreprise_id', $entrepriseId)->where('role', $role)->first();
        }

        if (! $account) {
            throw new RuntimeException("Aucun compte comptable n'est configuré pour le rôle « {$role} ».");
        }

        return $account;
    }

    /**
     * Enregistre une piece equilibree.
     *
     * @param  array  $lines  [['role' => ..., 'debit' => x, 'credit' => y, 'label' => ...], ...]
     */
    public function post(
        int $entrepriseId,
        string $journal,
        \DateTimeInterface $date,
        string $label,
        array $lines,
        ?Model $source = null,
        string $currency = 'XOF',
        ?int $userId = null,
    ): JournalEntry {
        $lines = array_values(array_filter($lines, fn ($l) => (float) ($l['debit'] ?? 0) > 0 || (float) ($l['credit'] ?? 0) > 0));

        if (count($lines) < 2) {
            throw new RuntimeException('Une écriture comptable doit comporter au moins deux lignes.');
        }

        $debit = round(array_sum(array_map(fn ($l) => (float) ($l['debit'] ?? 0), $lines)), 2);
        $credit = round(array_sum(array_map(fn ($l) => (float) ($l['credit'] ?? 0), $lines)), 2);

        // La regle non negociable de la partie double.
        if (abs($debit - $credit) >= 0.01) {
            throw new RuntimeException(sprintf(
                'Écriture déséquilibrée : %s au débit contre %s au crédit.',
                number_format($debit, 2, ',', ' '),
                number_format($credit, 2, ',', ' ')
            ));
        }

        return DB::transaction(function () use ($entrepriseId, $journal, $date, $label, $lines, $source, $currency, $userId) {
            $entry = JournalEntry::create([
                'entreprise_id' => $entrepriseId,
                'reference' => $this->numbers->next($entrepriseId, 'journal_entry', $date),
                'journal' => $journal,
                'entry_date' => $date,
                'label' => $label,
                'sourceable_type' => $source ? $source::class : null,
                'sourceable_id' => $source?->getKey(),
                'currency' => $currency,
                'created_by_user_id' => $userId,
            ]);

            foreach ($lines as $position => $line) {
                $entry->lines()->create([
                    // Un compte désigné par son rôle, ou directement par son identifiant.
                    'ledger_account_id' => $line['account_id'] ?? $this->account($entrepriseId, $line['role'])->id,
                    'label' => $line['label'] ?? null,
                    'debit' => round((float) ($line['debit'] ?? 0), 2),
                    'credit' => round((float) ($line['credit'] ?? 0), 2),
                    'position' => $position,
                ]);
            }

            return $entry->load('lines.account');
        });
    }

    /**
     * Solde net, par compte, de toutes les pièces rattachées à un document :
     * débits moins crédits.
     *
     * @return array<int, float> identifiant du compte => solde
     */
    public function netBySource(Model $source): array
    {
        return JournalEntryLine::query()
            ->whereHas('entry', fn ($query) => $query->withoutGlobalScope('entreprise')
                ->where('sourceable_type', $source::class)->where('sourceable_id', $source->getKey()))
            ->selectRaw('ledger_account_id, SUM(debit) - SUM(credit) AS net')
            ->groupBy('ledger_account_id')
            ->pluck('net', 'ledger_account_id')
            ->map(fn ($net) => round((float) $net, 2))
            ->all();
    }

    /** Une piece a-t-elle deja ete passee pour ce document ? */
    public function alreadyPosted(Model $source): bool
    {
        return JournalEntry::withoutGlobalScope('entreprise')
            ->where('sourceable_type', $source::class)
            ->where('sourceable_id', $source->getKey())
            ->exists();
    }

    /** Solde d'un compte sur une periode : debits moins credits. */
    public function balance(int $entrepriseId, string $role, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): float
    {
        $account = $this->account($entrepriseId, $role);

        $query = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.entreprise_id', $entrepriseId)
            ->where('l.ledger_account_id', $account->id);

        if ($from) {
            $query->where('e.entry_date', '>=', $from);
        }

        if ($to) {
            $query->where('e.entry_date', '<=', $to);
        }

        $row = $query->selectRaw('COALESCE(SUM(l.debit),0) AS d, COALESCE(SUM(l.credit),0) AS c')->first();

        return round((float) $row->d - (float) $row->c, 2);
    }
}
