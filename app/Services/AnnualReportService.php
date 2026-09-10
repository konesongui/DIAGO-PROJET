<?php

namespace App\Services;

use App\Models\AnnualFinancialReport;
use App\Models\Entreprise;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bilan financier annuel, construit a partir du journal en partie double.
 *
 * Un exercice est considere clos a partir du 31 decembre de l'annee. Le bilan
 * est alors calcule une fois puis fige : une ecriture passee apres coup ne
 * doit pas modifier un document deja presente au dirigeant.
 */
class AnnualReportService
{
    public function __construct(private TaxService $taxes)
    {
    }

    /** Un exercice est clos des le 31 decembre de son annee. */
    public function isYearClosed(int $year, ?Carbon $today = null): bool
    {
        $today = $today ?: now();

        return $today->greaterThanOrEqualTo(Carbon::create($year, 12, 31)->startOfDay());
    }

    /** Dernier exercice clos a la date du jour. */
    public function latestClosedYear(?Carbon $today = null): int
    {
        $today = $today ?: now();

        return $this->isYearClosed($today->year, $today) ? $today->year : $today->year - 1;
    }

    /**
     * Retourne le bilan de l'exercice, en le calculant si c'est la premiere
     * fois. Retourne null si l'exercice n'est pas encore clos ou si aucune
     * ecriture n'a ete passee.
     */
    public function reportFor(int $entrepriseId, int $year, ?Carbon $today = null): ?AnnualFinancialReport
    {
        if (! $this->isYearClosed($year, $today)) {
            return null;
        }

        $existing = AnnualFinancialReport::withoutGlobalScope('entreprise')
            ->where('entreprise_id', $entrepriseId)->where('fiscal_year', $year)->first();

        if ($existing) {
            return $existing;
        }

        $data = $this->compute($entrepriseId, $year);

        // Un exercice sans aucune ecriture ne produit pas de bilan.
        if ($data['entry_count'] === 0) {
            return null;
        }

        return AnnualFinancialReport::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entrepriseId,
            'fiscal_year' => $year,
            'data' => $data,
            'total_assets' => $data['totals']['assets'],
            'total_liabilities' => $data['totals']['liabilities_and_equity'],
            'net_result' => $data['totals']['net_result'],
            'currency' => $data['currency'],
            'generated_at' => now(),
        ]);
    }

    /** Bilan non encore telecharge, a signaler au dirigeant. */
    public function pendingReport(int $entrepriseId, ?Carbon $today = null): ?AnnualFinancialReport
    {
        $report = $this->reportFor($entrepriseId, $this->latestClosedYear($today), $today);

        return $report && $report->isUnread() ? $report : null;
    }

    /**
     * Calcule bilan et compte de resultat a partir des soldes de comptes.
     */
    public function compute(int $entrepriseId, int $year): array
    {
        $from = Carbon::create($year, 1, 1)->startOfDay();
        $to = Carbon::create($year, 12, 31)->endOfDay();

        $rows = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('ledger_accounts as a', 'a.id', '=', 'l.ledger_account_id')
            ->where('e.entreprise_id', $entrepriseId)
            ->whereBetween('e.entry_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('a.code', 'a.name', 'a.type')
            ->selectRaw('a.code, a.name, a.type, SUM(l.debit) AS debit, SUM(l.credit) AS credit')
            ->orderBy('a.code')
            ->get();

        $entryCount = DB::table('journal_entries')
            ->where('entreprise_id', $entrepriseId)
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->count();

        $buckets = ['assets' => [], 'liabilities' => [], 'equity' => [], 'revenue' => [], 'expenses' => []];

        foreach ($rows as $row) {
            $debit = (float) $row->debit;
            $credit = (float) $row->credit;

            // Un compte d'actif ou de charge a un solde debiteur, les autres
            // un solde crediteur : on presente chaque solde dans son sens.
            $isDebitNature = in_array($row->type, ['asset', 'expense'], true);
            $amount = round($isDebitNature ? $debit - $credit : $credit - $debit, 2);

            $line = ['code' => $row->code, 'name' => $row->name, 'amount' => $amount,
                     'debit' => round($debit, 2), 'credit' => round($credit, 2)];

            $bucket = match ($row->type) {
                'asset' => 'assets',
                'liability' => 'liabilities',
                'equity' => 'equity',
                'revenue' => 'revenue',
                'expense' => 'expenses',
                default => 'assets',
            };

            $buckets[$bucket][] = $line;
        }

        $sum = fn (array $lines) => round(array_sum(array_column($lines, 'amount')), 2);

        $revenue = $sum($buckets['revenue']);
        $expenses = $sum($buckets['expenses']);
        $netResult = round($revenue - $expenses, 2);

        $assets = $sum($buckets['assets']);
        $liabilities = $sum($buckets['liabilities']);
        $equity = $sum($buckets['equity']);

        $entreprise = Entreprise::find($entrepriseId);

        return [
            'year' => $year,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'entry_count' => $entryCount,
            'currency' => $this->taxes->currencyFor($entreprise),
            'currency_symbol' => $this->taxes->currencySymbolFor($entreprise),
            'decimals' => $this->taxes->decimalsFor($this->taxes->currencyFor($entreprise)),
            'entreprise' => $entreprise?->name,
            'balance_sheet' => [
                'assets' => $buckets['assets'],
                'liabilities' => $buckets['liabilities'],
                'equity' => $buckets['equity'],
            ],
            'income_statement' => [
                'revenue' => $buckets['revenue'],
                'expenses' => $buckets['expenses'],
            ],
            'totals' => [
                'assets' => $assets,
                'liabilities' => $liabilities,
                'equity' => $equity,
                'revenue' => $revenue,
                'expenses' => $expenses,
                'net_result' => $netResult,
                // Le resultat de l'exercice appartient aux capitaux propres :
                // c'est lui qui equilibre le bilan.
                'liabilities_and_equity' => round($liabilities + $equity + $netResult, 2),
            ],
        ];
    }
}
