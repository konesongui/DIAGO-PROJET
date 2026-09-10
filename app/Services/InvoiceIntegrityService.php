<?php

namespace App\Services;

use App\Models\CreditNote;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Regles d'integrite des documents de facturation.
 *
 * Une facture remise au client est un document opposable : elle ne se modifie
 * pas et ne se supprime pas. La seule correction admise est l'emission d'un
 * avoir, qui laisse la facture d'origine et sa correction toutes deux
 * lisibles.
 */
class InvoiceIntegrityService
{
    public function __construct(
        private DocumentNumberService $numbers,
        private TaxService $taxes,
    ) {
    }

    /** Une facture emise est figee. */
    public function isLocked(Model $invoice): bool
    {
        return $invoice->issued_at !== null
            || ($invoice->fne_status ?? null) === 'certified'
            || (float) ($invoice->paid_amount ?? 0) > 0;
    }

    /** Raison lisible du verrouillage, pour l'afficher a l'utilisateur. */
    public function lockReason(Model $invoice): ?string
    {
        if (($invoice->fne_status ?? null) === 'certified') {
            return 'Cette facture est certifiée par l’administration fiscale.';
        }

        if ((float) ($invoice->paid_amount ?? 0) > 0) {
            return 'Un paiement a déjà été enregistré sur cette facture.';
        }

        if ($invoice->issued_at !== null) {
            return 'Cette facture a été émise le ' . $invoice->issued_at->format('d/m/Y') . '.';
        }

        return null;
    }

    public function assertModifiable(Model $invoice): void
    {
        if ($this->isLocked($invoice)) {
            throw new RuntimeException(
                trim($this->lockReason($invoice) . ' Elle ne peut plus être modifiée ni supprimée : émettez un avoir pour la corriger.')
            );
        }
    }

    /** Marque la facture comme remise au client : elle devient definitive. */
    public function issue(Model $invoice): void
    {
        if ($invoice->issued_at !== null) {
            throw new RuntimeException('Cette facture est déjà émise.');
        }

        $invoice->forceFill(['issued_at' => now()])->save();
    }

    /** Montant qu'il reste possible de porter en avoir. */
    public function creditableAmount(Model $invoice): float
    {
        $total = (float) ($invoice->total_ttc ?? $invoice->amount ?? 0);

        return max(0, round($total - (float) ($invoice->credited_amount ?? 0), 2));
    }

    /**
     * Emet un avoir sur une facture.
     *
     * L'avoir reprend le regime fiscal du document d'origine : corriger une
     * facture exoneree ne doit pas produire un avoir taxe.
     */
    public function credit(Model $invoice, float $amountTtc, string $reason, ?int $userId = null): CreditNote
    {
        if ($invoice->issued_at === null) {
            throw new RuntimeException("Cette facture n'est pas encore émise : modifiez-la directement plutôt que d'émettre un avoir.");
        }

        $remaining = $this->creditableAmount($invoice);

        if ($amountTtc <= 0) {
            throw new RuntimeException("Le montant de l'avoir doit être supérieur à zéro.");
        }

        if ($amountTtc > $remaining) {
            throw new RuntimeException(sprintf(
                "Le montant dépasse ce qui reste à créditer sur cette facture (%s).",
                number_format($remaining, 2, ',', ' ')
            ));
        }

        $currency = $invoice->currency ?: 'XOF';
        $rate = (float) ($invoice->tax_rate ?? 0);

        // Le montant saisi est un TTC : on en extrait la taxe au taux d'origine.
        $baseHt = $rate > 0 ? $this->taxes->roundMoney($amountTtc / (1 + $rate / 100), $currency) : $amountTtc;
        $taxAmount = $this->taxes->roundMoney($amountTtc - $baseHt, $currency);

        return DB::transaction(function () use ($invoice, $amountTtc, $reason, $userId, $currency, $rate, $baseHt, $taxAmount, $remaining) {
            $note = CreditNote::create([
                'entreprise_id' => $invoice->entreprise_id,
                'reference' => $this->numbers->next($invoice->entreprise_id, 'credit_note'),
                'creditable_type' => $invoice::class,
                'creditable_id' => $invoice->id,
                'reason' => $reason,
                'total_ht' => $baseHt,
                'tax_amount' => $taxAmount,
                'tax_rate' => $rate,
                'tax_regime' => $invoice->tax_regime,
                'tax_rate_id' => $invoice->tax_rate_id,
                'total_ttc' => $amountTtc,
                'currency' => $currency,
                'is_full' => abs($amountTtc - $remaining) < 0.01 && (float) ($invoice->credited_amount ?? 0) === 0.0,
                'created_by_user_id' => $userId,
            ]);

            // La facture d'origine n'est pas touchee : on ne met a jour que le
            // cumul des avoirs, pour empecher de crediter deux fois.
            $invoice->forceFill([
                'credited_amount' => (float) ($invoice->credited_amount ?? 0) + $amountTtc,
            ])->save();

            // L'avoir contrepasse la vente au journal comptable.
            app(AccountingPoster::class)->postCreditNote($note, $userId);

            return $note;
        });
    }
}
