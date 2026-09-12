<?php

namespace App\Services;

use App\Models\CommercialInvoice;
use App\Models\CommercialObjective;
use App\Models\CreditNote;
use App\Models\CustomInvoice;
use App\Models\PosSale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Chiffre d'affaires HT d'une période, document par document.
 *
 * Même périmètre que la TVA collectée : factures de ventes (émises à la
 * validation de la livraison), factures personnalisées émises, ventes au
 * comptoir terminées, moins les avoirs émis sur la période. Chaque ligne
 * garde l'utilisateur qui a fait la vente : le créateur du devis d'origine
 * pour une facture de ventes, celui de la facture personnalisée. La caisse
 * n'enregistre pas de vendeur : une vente au comptoir n'est attribuée à
 * personne.
 */
class SalesRevenueService
{
    public const SOURCES = [
        'invoice' => 'Factures de ventes',
        'custom_invoice' => 'Factures personnalisées',
        'pos' => 'Ventes au comptoir',
        'credit_note' => 'Avoirs',
    ];

    /**
     * @return Collection<int, array{date: Carbon, user_id: ?int, amount: float, source: string, reference: string, client: string, url: ?string}>
     */
    public function rows(int $entrepriseId, \DateTimeInterface $from, \DateTimeInterface $to): Collection
    {
        // Les deux classes Carbon du projet sont acceptées.
        $from = Carbon::instance($from);
        $to = Carbon::instance($to);
        $rows = collect();
        $row = fn ($document, $date, $userId, $amount, string $source) => [
            'date' => Carbon::parse($date), 'user_id' => $userId ? (int) $userId : null,
            'amount' => round((float) $amount, 2), 'source' => $source,
            'reference' => self::reference($document), 'client' => self::client($document), 'url' => self::url($document),
        ];

        $this->invoices($entrepriseId, $from, $to)
            ->each(fn ($invoice) => $rows->push($row($invoice, $invoice->issued_at ?: $invoice->created_at, $invoice->created_by_user_id, self::invoiceHt($invoice), 'invoice')));

        $this->customInvoices($entrepriseId, $from, $to)
            ->each(fn ($invoice) => $rows->push($row($invoice, $invoice->issued_at, $invoice->created_by_user_id, $invoice->total_ht, 'custom_invoice')));

        $this->posSales($entrepriseId, $from, $to)
            ->each(fn ($sale) => $rows->push($row($sale, $sale->created_at, null, $sale->total_ht ?? $sale->total, 'pos')));

        // Un avoir diminue les ventes du commercial qui avait fait la vente.
        CreditNote::withoutGlobalScope('entreprise')
            ->where('entreprise_id', $entrepriseId)
            ->whereBetween('created_at', [$from, $to])
            ->with(['creditable' => fn ($query) => $query->withoutGlobalScope('entreprise')])
            ->get()
            ->each(fn ($note) => $rows->push(array_merge(
                $row($note, $note->created_at, $note->creditable?->created_by_user_id, -1 * (float) $note->total_ht, 'credit_note'),
                ['client' => self::client($note->creditable), 'url' => self::url($note->creditable)],
            )));

        return $rows->sortBy('date')->values();
    }

    private function invoices(int $entrepriseId, Carbon $from, Carbon $to, array $with = []): Collection
    {
        return CommercialInvoice::withoutGlobalScope('entreprise')
            ->where('entreprise_id', $entrepriseId)->countedInSales()
            ->whereBetween(DB::raw('COALESCE(issued_at, created_at)'), [$from, $to])
            ->with($with)->get();
    }

    private function customInvoices(int $entrepriseId, Carbon $from, Carbon $to): Collection
    {
        return CustomInvoice::withoutGlobalScope('entreprise')
            ->where('entreprise_id', $entrepriseId)->whereNotNull('issued_at')
            ->whereBetween('issued_at', [$from, $to])->get();
    }

    private function posSales(int $entrepriseId, Carbon $from, Carbon $to): Collection
    {
        return PosSale::withoutGlobalScope('entreprise')
            ->where('entreprise_id', $entrepriseId)->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])->get();
    }

    private static function invoiceHt(CommercialInvoice $invoice): float
    {
        return (float) ($invoice->total_ht ?? ((float) $invoice->amount - (float) $invoice->tax_amount));
    }

    /** Numéro affiché d'un document de vente, tel que le montrent ses écrans. */
    public static function reference($document): string
    {
        return match (true) {
            $document instanceof CommercialInvoice => 'Facture N° ' . $document->id,
            $document instanceof CustomInvoice => $document->reference ?: 'Facture personnalisée N° ' . $document->id,
            $document instanceof PosSale => $document->reference ?: 'Vente N° ' . $document->id,
            $document instanceof CreditNote => 'Avoir ' . $document->reference,
            default => 'Document',
        };
    }

    private static function client($document): string
    {
        $name = trim((string) ($document?->client_name ?? ''));

        return $name !== '' ? $name : ($document instanceof PosSale ? 'Client comptoir' : 'Client non renseigné');
    }

    private static function url($document): ?string
    {
        return match (true) {
            $document instanceof CommercialInvoice => route('admin.commercial.invoices.print', $document),
            $document instanceof CustomInvoice => route('admin.commercial.custom-invoice.show', $document),
            $document instanceof PosSale => route('admin.commercial.pos.show', $document),
            default => null,
        };
    }

    /**
     * Argent reçu sur la période : règlements datés des factures, et ventes au
     * comptoir terminées (encaissées au moment de la vente, monnaie rendue déduite).
     *
     * @return Collection<int, array{date: Carbon, amount: float, method: string, reference: string, client: string, url: ?string}>
     */
    public function collections(int $entrepriseId, \DateTimeInterface $from, \DateTimeInterface $to): Collection
    {
        // Les deux classes Carbon du projet sont acceptées.
        $from = Carbon::instance($from);
        $to = Carbon::instance($to);
        $methods = ['cash' => 'Espèces', 'bank' => 'Banque', 'transfer' => 'Virement'];
        $rows = \App\Models\InvoicePayment::withoutGlobalScope('entreprise')
            ->where('entreprise_id', $entrepriseId)
            ->whereBetween('paid_on', [$from->toDateString(), $to->toDateString()])
            ->with(['payable' => fn ($query) => $query->withoutGlobalScope('entreprise')])
            ->get()
            ->map(fn ($payment) => [
                'date' => $payment->paid_on, 'amount' => round((float) $payment->amount, 2),
                'method' => $methods[$payment->method] ?? 'Espèces',
                'reference' => self::reference($payment->payable), 'client' => self::client($payment->payable), 'url' => self::url($payment->payable),
            ]);

        return $rows->concat($this->posSales($entrepriseId, $from, $to)->map(fn ($sale) => [
            'date' => $sale->created_at, 'amount' => round((float) $sale->total, 2),
            'method' => $sale->payment_method === 'cash' ? 'Espèces' : 'Banque',
            'reference' => self::reference($sale), 'client' => self::client($sale), 'url' => self::url($sale),
        ]))->sortByDesc('date')->values();
    }

    /**
     * Factures émises qui restent à encaisser à ce jour, avoirs et règlements
     * déduits. Seule la facture personnalisée porte une échéance.
     *
     * @return Collection<int, array{date: Carbon, reference: string, client: string, total: float, paid: float, remaining: float, due: ?Carbon, url: ?string}>
     */
    public function receivables(int $entrepriseId): Collection
    {
        $open = collect();
        CommercialInvoice::withoutGlobalScope('entreprise')
            ->where('entreprise_id', $entrepriseId)->where('status', '!=', 'cancelled')->get()
            ->each(function ($invoice) use ($open) {
                $due = max(0, (float) $invoice->amount - (float) $invoice->credited_amount);
                $open->push(['document' => $invoice, 'date' => $invoice->issued_at ?: $invoice->created_at, 'total' => $due,
                    'paid' => (float) $invoice->paid_amount, 'remaining' => max(0, $due - (float) $invoice->paid_amount), 'due' => null]);
            });
        CustomInvoice::withoutGlobalScope('entreprise')
            ->where('entreprise_id', $entrepriseId)->whereNotNull('issued_at')->get()
            ->each(fn ($invoice) => $open->push(['document' => $invoice, 'date' => $invoice->issued_at, 'total' => $invoice->amountDue(),
                'paid' => (float) $invoice->paid_amount, 'remaining' => $invoice->remainingAmount(), 'due' => $invoice->valid_until ? Carbon::parse($invoice->valid_until) : null]));

        return $open->filter(fn ($row) => $row['remaining'] >= 0.01)
            ->map(fn ($row) => [
                'date' => Carbon::parse($row['date']), 'reference' => self::reference($row['document']), 'client' => self::client($row['document']),
                'total' => round($row['total'], 2), 'paid' => round($row['paid'], 2), 'remaining' => round($row['remaining'], 2),
                'due' => $row['due'], 'url' => self::url($row['document']),
            ])->sortBy('date')->values();
    }

    /**
     * Prestations et produits vendus sur la période. Le HT de chaque document
     * est réparti sur ses lignes au prorata de leur montant : les remises
     * globales sont ainsi déduites, et une ligne de caisse (saisie TTC) est
     * ramenée au HT. Les avoirs, qui ne visent pas une ligne, n'y figurent pas.
     *
     * @return Collection<int, array{name: string, amount: float, documents: int}>
     */
    public function soldItems(int $entrepriseId, \DateTimeInterface $from, \DateTimeInterface $to): Collection
    {
        // Les deux classes Carbon du projet sont acceptées.
        $from = Carbon::instance($from);
        $to = Carbon::instance($to);
        $items = [];
        $spread = function (array $lines, float $ht, string $document) use (&$items) {
            $weights = collect($lines)->map(fn ($line) => max(0, (float) ($line['quantity'] ?? 1) * (float) ($line['unit_price'] ?? $line['price'] ?? 0)));
            $total = $weights->sum();
            foreach ($lines as $index => $line) {
                $name = trim((string) ($line['item_name'] ?? $line['designation'] ?? ''));
                if ($name === '' || $total <= 0) {
                    continue;
                }
                $key = mb_strtolower($name);
                $items[$key] ??= ['name' => $name, 'amount' => 0.0, 'documents' => []];
                $items[$key]['amount'] += $ht * $weights[$index] / $total;
                $items[$key]['documents'][$document] = true;
            }
        };

        $this->invoices($entrepriseId, $from, $to, ['delivery.order'])
            ->each(fn ($invoice) => $spread(array_values((array) ($invoice->delivery?->order?->lines ?: $invoice->delivery?->lines ?: [])), self::invoiceHt($invoice), 'i' . $invoice->id));
        $this->customInvoices($entrepriseId, $from, $to)
            ->each(fn ($invoice) => $spread($invoice->documentLines(), (float) $invoice->total_ht, 'c' . $invoice->id));
        $this->posSales($entrepriseId, $from, $to)
            ->each(fn ($sale) => $spread(array_values((array) $sale->lines), (float) ($sale->total_ht ?? $sale->total), 'p' . $sale->id));

        return collect($items)->map(fn ($item) => ['name' => $item['name'], 'amount' => round($item['amount'], 2), 'documents' => count($item['documents'])])
            ->sortByDesc('amount')->values();
    }

    /**
     * Avancement d'un objectif annuel : réalisé de l'exercice, rythme attendu
     * à la date du jour et réalisé de chaque commercial sur sa propre période.
     */
    public function objectiveProgress(CommercialObjective $objective, ?Carbon $today = null): array
    {
        $today = ($today ?? now())->copy()->endOfDay();
        $year = $objective->objective_date->year;
        $from = Carbon::create($year, 1, 1)->startOfDay();
        $to = Carbon::create($year, 12, 31)->endOfDay();
        $assignments = $objective->assignments;
        // Les périodes des attributions restent dans l'exercice, mais une
        // attribution ancienne peut en déborder : on lit tout ce qu'elles couvrent.
        $readFrom = collect([$from])->concat($assignments->pluck('starts_at'))->min()->copy()->startOfDay();
        $readTo = collect([$to])->concat($assignments->pluck('ends_at'))->max()->copy()->endOfDay();
        $rows = $this->rows($objective->entreprise_id, $readFrom, $readTo);
        $yearRows = $rows->filter(fn ($row) => $row['date']->between($from, $to));

        $amount = (float) $objective->amount;
        $realized = round((float) $yearRows->sum('amount'), 2);
        $pace = $this->elapsed($from, $to, $today);
        $expected = round($amount * $pace, 2);

        $cumulative = 0.0;
        $months = collect(range(1, 12))->map(function (int $month) use ($yearRows, $year, $amount, &$cumulative) {
            $value = (float) $yearRows->filter(fn ($row) => $row['date']->month === $month)->sum('amount');
            $cumulative += $value;

            return [
                'label' => ucfirst(Carbon::create($year, $month, 1)->translatedFormat('M')),
                'value' => round($value, 2),
                'cumulative' => round($cumulative, 2),
                'target' => round($amount * $month / 12, 2),
            ];
        });

        $followed = fn (array $row) => $row['user_id'] !== null && $assignments->contains(fn ($assignment) => $assignment->employee?->user_id === $row['user_id']
            && $row['date']->between($assignment->starts_at->copy()->startOfDay(), $assignment->ends_at->copy()->endOfDay()));
        $lines = $assignments->map(function ($assignment) use ($rows, $today) {
            $userId = $assignment->employee?->user_id;
            $start = $assignment->starts_at->copy()->startOfDay();
            $end = $assignment->ends_at->copy()->endOfDay();
            $target = (float) $assignment->amount;
            $done = $userId
                ? round((float) $rows->filter(fn ($row) => $row['user_id'] === $userId && $row['date']->between($start, $end))->sum('amount'), 2)
                : null;
            $expected = round($target * $this->elapsed($start, $end, $today), 2);

            return [
                'assignment' => $assignment,
                'measurable' => $userId !== null,
                'realized' => $done,
                'percent' => $done !== null && $target > 0 ? $done / $target * 100 : null,
                'expected' => $expected,
                'state' => $this->state($done, $target, $expected, $start, $end, $today),
            ];
        });

        return [
            'year' => $year,
            'from' => $from,
            'to' => $to,
            'amount' => $amount,
            'realized' => $realized,
            'remaining' => max(0, round($amount - $realized, 2)),
            'percent' => $amount > 0 ? $realized / $amount * 100 : 0,
            'pace' => $pace * 100,
            'expected' => $expected,
            'gap' => round($realized - $expected, 2),
            'state' => $this->state($realized, $amount, $expected, $from, $to, $today),
            'assigned' => round((float) $assignments->sum('amount'), 2),
            'sources' => collect(self::SOURCES)->map(fn ($label, $source) => [
                'label' => $label,
                'amount' => round((float) $yearRows->where('source', $source)->sum('amount'), 2),
                'count' => $yearRows->where('source', $source)->count(),
            ])->all(),
            // Ventes qu'aucun objectif individuel ne suit : comptoir, vendeur sans
            // objectif, ou vente faite hors de la période de son vendeur.
            'unassigned' => round((float) $yearRows->reject($followed)->sum('amount'), 2),
            'months' => $months->all(),
            'lines' => $lines->all(),
        ];
    }

    /** Part écoulée d'une période à une date, entre 0 et 1. */
    private function elapsed(Carbon $start, Carbon $end, Carbon $today): float
    {
        if ($today->lt($start)) {
            return 0.0;
        }
        if ($today->gte($end)) {
            return 1.0;
        }
        $days = $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;

        return min(1.0, ($start->copy()->startOfDay()->diffInDays($today->copy()->startOfDay()) + 1) / $days);
    }

    private function state(?float $done, float $target, float $expected, Carbon $start, Carbon $end, Carbon $today): string
    {
        if ($done === null) {
            return 'unmeasured';
        }
        if ($target > 0 && $done >= $target) {
            return 'reached';
        }
        if ($today->lt($start)) {
            return 'upcoming';
        }
        if ($today->gte($end)) {
            return 'missed';
        }

        return $done >= $expected ? 'on_track' : 'late';
    }

    /** Libellé, couleur de badge et icône de chaque état d'avancement. */
    public static function states(): array
    {
        return [
            'reached' => ['Objectif atteint', 'success', 'bi-trophy'],
            'on_track' => ['Dans le rythme', 'success', 'bi-graph-up-arrow'],
            'late' => ['En retard', 'warning', 'bi-exclamation-triangle'],
            'missed' => ['Non atteint', 'danger', 'bi-x-circle'],
            'upcoming' => ['À venir', 'neutral', 'bi-hourglass'],
            'unmeasured' => ['Non mesurable', 'neutral', 'bi-person-x'],
        ];
    }
}
