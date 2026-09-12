<?php

namespace App\Services;

use App\Models\CommercialInvoice;
use App\Models\CreditNote;
use App\Models\CustomInvoice;
use App\Models\PosSale;
use App\Models\SupplierInvoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Etat de TVA d'une periode.
 *
 * Le calcul est fait SUR LES DEBITS : la taxe est due a la date d'emission du
 * document, pas a celle de son encaissement. C'est le regime le plus courant
 * et le seul que les donnees permettent aujourd'hui de calculer : les
 * paiements de factures ne sont pas dates au niveau du document.
 *
 * Un brouillon n'est jamais declare : seul un document emis est opposable.
 */
class VatDeclarationService
{
    public function __construct(private TaxService $taxes)
    {
    }

    public const BASIS_DEBITS = 'debits';
    public const BASIS_COLLECTIONS = 'collections';

    public function declare(int $entrepriseId, \DateTimeInterface $from, \DateTimeInterface $to, string $basis = self::BASIS_DEBITS): array
    {
        // Accepte indifferemment les deux classes Carbon utilisees dans le
        // projet, puis normalise sur les bornes de journee.
        $from = Carbon::instance($from)->startOfDay();
        $to = Carbon::instance($to)->endOfDay();

        $basis = $basis === self::BASIS_COLLECTIONS ? self::BASIS_COLLECTIONS : self::BASIS_DEBITS;

        $collected = $basis === self::BASIS_COLLECTIONS
            ? $this->collectedOnPayments($entrepriseId, $from, $to)
            : $this->collected($entrepriseId, $from, $to);

        $credits = $this->creditNotes($entrepriseId, $from, $to);
        $deductible = $this->deductible($entrepriseId, $from, $to);

        // Les avoirs viennent en diminution de la taxe collectee.
        $collectedNet = round($collected['tax'] - $credits['tax'], 2);
        $balance = round($collectedNet - $deductible['tax'], 2);

        return [
            'from' => $from,
            'to' => $to,
            'basis' => $basis,
            'collected' => $collected,
            'credit_notes' => $credits,
            'collected_net' => $collectedNet,
            'deductible' => $deductible,
            'balance' => $balance,
            'is_payable' => $balance > 0,
            'warnings' => $this->warnings($entrepriseId, $from, $to),
        ];
    }

    /** TVA collectee sur les ventes emises de la periode. */
    private function collected(int $entrepriseId, Carbon $from, Carbon $to): array
    {
        $rows = collect();

        // Factures de vente : emises des la validation de la livraison. Une
        // facture annulee reste comptee : son avoir la deduit a sa propre date.
        CommercialInvoice::withoutGlobalScope('entreprise')
            ->where('entreprise_id', $entrepriseId)
            ->countedInSales()
            ->whereBetween(DB::raw('COALESCE(issued_at, created_at)'), [$from, $to])
            ->get()
            ->each(fn ($i) => $rows->push($this->row('Factures de vente', $i->total_ht, $i->tax_amount, $i->tax_rate, $i->tax_regime)));

        // Factures personnalisees : uniquement celles reellement emises.
        CustomInvoice::withoutGlobalScope('entreprise')
            ->where('entreprise_id', $entrepriseId)
            ->whereNotNull('issued_at')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('issued_at', [$from, $to])
            ->get()
            ->each(fn ($i) => $rows->push($this->row('Factures personnalisées', $i->total_ht, $i->tax_amount, $i->tax_rate, $i->tax_regime)));

        // Ventes au comptoir : encaissees et emises au meme instant.
        PosSale::withoutGlobalScope('entreprise')
            ->where('entreprise_id', $entrepriseId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->each(fn ($s) => $rows->push($this->row('Ventes au comptoir', $s->total_ht, $s->tax_amount, $s->tax_rate, $s->tax_regime)));

        return $this->summarise($rows);
    }

    /**
     * TVA collectee SUR LES ENCAISSEMENTS.
     *
     * La taxe devient exigible au fur et a mesure des reglements. Pour un
     * paiement partiel, seule la fraction encaissee est declaree : une
     * facture de 118 000 dont 59 000 sont regles ne rend exigible que la
     * moitie des 18 000 de taxe.
     *
     * Les ventes au comptoir sont encaissees a l'instant de la vente : elles
     * sont declarees a leur date, sans passer par le journal des reglements.
     */
    private function collectedOnPayments(int $entrepriseId, Carbon $from, Carbon $to): array
    {
        $rows = collect();

        $payments = \App\Models\InvoicePayment::withoutGlobalScope('entreprise')
            ->where('entreprise_id', $entrepriseId)
            ->whereBetween('paid_on', [$from->toDateString(), $to->toDateString()])
            ->get();

        foreach ($payments as $payment) {
            $invoice = $payment->payable;

            if (! $invoice) {
                continue;
            }

            $total = (float) ($invoice->total_ttc ?? $invoice->amount ?? 0);
            $tax = (float) ($invoice->tax_amount ?? 0);
            $baseHt = (float) ($invoice->total_ht ?? 0);

            if ($total <= 0) {
                continue;
            }

            // Fraction du document reellement encaissee sur la periode.
            $share = min(1.0, (float) $payment->amount / $total);

            $rows->push($this->row(
                $invoice instanceof \App\Models\CommercialInvoice ? 'Factures de vente' : 'Factures personnalisées',
                round($baseHt * $share, 2),
                round($tax * $share, 2),
                (float) ($invoice->tax_rate ?? 0),
                $invoice->tax_regime
            ));
        }

        PosSale::withoutGlobalScope('entreprise')
            ->where('entreprise_id', $entrepriseId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->each(fn ($s) => $rows->push($this->row('Ventes au comptoir', $s->total_ht, $s->tax_amount, $s->tax_rate, $s->tax_regime)));

        return $this->summarise($rows);
    }

    /** TVA deductible sur les achats de la periode. */
    private function deductible(int $entrepriseId, Carbon $from, Carbon $to): array
    {
        $rows = SupplierInvoice::withoutGlobalScope('entreprise')
            ->where('entreprise_id', $entrepriseId)
            ->whereBetween(DB::raw('COALESCE(invoice_date, created_at)'), [$from, $to])
            ->get()
            ->map(fn ($i) => $this->row('Factures fournisseurs', $i->total_ht, $i->tax_amount, 0, $i->tax_regime));

        return $this->summarise(collect($rows));
    }

    /** Avoirs emis sur la periode, en diminution de la taxe collectee. */
    private function creditNotes(int $entrepriseId, Carbon $from, Carbon $to): array
    {
        $rows = CreditNote::withoutGlobalScope('entreprise')
            ->where('entreprise_id', $entrepriseId)
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->map(fn ($n) => $this->row('Avoirs', $n->total_ht, $n->tax_amount, $n->tax_rate, $n->tax_regime));

        return $this->summarise(collect($rows));
    }

    private function row(string $source, $baseHt, $tax, $rate, ?string $regime): array
    {
        return [
            'source' => $source,
            'base_ht' => (float) $baseHt,
            'tax' => (float) $tax,
            'rate' => (float) $rate,
            'regime' => $regime ?: 'unknown',
        ];
    }

    /** Regroupe par taux et par origine. */
    private function summarise(Collection $rows): array
    {
        return [
            'base_ht' => round($rows->sum('base_ht'), 2),
            'tax' => round($rows->sum('tax'), 2),
            'count' => $rows->count(),
            'by_rate' => $rows->groupBy(fn ($r) => (string) $r['rate'])
                ->map(fn ($group, $rate) => [
                    'rate' => (float) $rate,
                    'base_ht' => round($group->sum('base_ht'), 2),
                    'tax' => round($group->sum('tax'), 2),
                    'count' => $group->count(),
                ])->sortByDesc('rate')->values()->all(),
            'by_source' => $rows->groupBy('source')
                ->map(fn ($group, $source) => [
                    'source' => $source,
                    'base_ht' => round($group->sum('base_ht'), 2),
                    'tax' => round($group->sum('tax'), 2),
                    'count' => $group->count(),
                ])->values()->all(),
        ];
    }

    /**
     * Documents que la declaration ne peut pas qualifier.
     *
     * Un document sans regime fiscal fausse le total : on le signale plutot
     * que de le compter en silence dans une case ou dans une autre.
     */
    private function warnings(int $entrepriseId, Carbon $from, Carbon $to): array
    {
        $unknown = ['label' => 'Documents sans régime fiscal renseigné', 'count' => 0, 'tax' => 0.0];

        $unknown['count'] += CommercialInvoice::withoutGlobalScope('entreprise')
            ->where('entreprise_id', $entrepriseId)->countedInSales()
            ->whereBetween(DB::raw('COALESCE(issued_at, created_at)'), [$from, $to])
            ->where(fn ($q) => $q->whereNull('tax_regime')->orWhere('tax_regime', 'unknown'))
            ->count();

        $unknown['count'] += CustomInvoice::withoutGlobalScope('entreprise')
            ->where('entreprise_id', $entrepriseId)->whereNotNull('issued_at')
            ->whereBetween('issued_at', [$from, $to])
            ->where(fn ($q) => $q->whereNull('tax_regime')->orWhere('tax_regime', 'unknown'))
            ->count();

        $unknown['count'] += SupplierInvoice::withoutGlobalScope('entreprise')
            ->where('entreprise_id', $entrepriseId)
            ->whereBetween(DB::raw('COALESCE(invoice_date, created_at)'), [$from, $to])
            ->where(fn ($q) => $q->whereNull('tax_regime')->orWhere('tax_regime', 'unknown'))
            ->count();

        return $unknown['count'] > 0 ? [$unknown] : [];
    }
}
