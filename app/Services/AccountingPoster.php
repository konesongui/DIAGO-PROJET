<?php

namespace App\Services;

use App\Models\CommercialInvoice;
use App\Models\CreditNote;
use App\Models\CustomInvoice;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\PosSale;
use App\Models\SupplierInvoice;
use Illuminate\Database\Eloquent\Model;

/**
 * Traduit les evenements commerciaux en ecritures comptables.
 *
 * C'est le chainon qui manquait : le module commercial alimentait la
 * tresorerie mais rien ne remontait en comptabilite. Chaque document passe
 * desormais sa piece, une seule fois.
 */
class AccountingPoster
{
    public function __construct(private LedgerService $ledger)
    {
    }

    /** Facture de vente : creance client, produit et TVA collectee. */
    public function postSaleInvoice(CommercialInvoice $invoice, ?int $userId = null): ?JournalEntry
    {
        return $this->guard($invoice, function () use ($invoice, $userId) {
            $ht = (float) $invoice->total_ht;
            $tax = (float) $invoice->tax_amount;

            return $this->ledger->post(
                $invoice->entreprise_id, 'VE',
                $invoice->issued_at ?: $invoice->created_at,
                'Facture de vente ' . ($invoice->client_name ?: ''),
                [
                    ['role' => LedgerAccount::ROLE_CUSTOMERS, 'debit' => $ht + $tax, 'label' => $invoice->client_name],
                    ['role' => LedgerAccount::ROLE_SALES, 'credit' => $ht, 'label' => 'Vente HT'],
                    ['role' => LedgerAccount::ROLE_VAT_COLLECTED, 'credit' => $tax, 'label' => 'TVA collectée'],
                ],
                $invoice, $invoice->currency ?: 'XOF', $userId
            );
        });
    }

    /** Facture personnalisee : meme schema, declenche a l'emission. */
    public function postCustomInvoice(CustomInvoice $invoice, ?int $userId = null): ?JournalEntry
    {
        return $this->guard($invoice, function () use ($invoice, $userId) {
            $ht = (float) $invoice->total_ht;
            $tax = (float) $invoice->tax_amount;

            return $this->ledger->post(
                $invoice->entreprise_id, 'VE',
                $invoice->issued_at ?: $invoice->created_at,
                'Facture ' . $invoice->reference,
                [
                    ['role' => LedgerAccount::ROLE_CUSTOMERS, 'debit' => $ht + $tax, 'label' => $invoice->client_name],
                    ['role' => LedgerAccount::ROLE_SALES, 'credit' => $ht, 'label' => 'Vente HT'],
                    ['role' => LedgerAccount::ROLE_VAT_COLLECTED, 'credit' => $tax, 'label' => 'TVA collectée'],
                ],
                $invoice, company_currency()['code'], $userId
            );
        });
    }

    /** Vente comptoir : encaissee immediatement, donc pas de compte client. */
    public function postPosSale(PosSale $sale, ?int $userId = null): ?JournalEntry
    {
        return $this->guard($sale, function () use ($sale, $userId) {
            $ht = (float) $sale->total_ht;
            $tax = (float) $sale->tax_amount;
            $target = $sale->payment_method === 'cash' ? LedgerAccount::ROLE_CASH : LedgerAccount::ROLE_BANK;

            return $this->ledger->post(
                $sale->entreprise_id, $sale->payment_method === 'cash' ? 'CA' : 'BQ',
                $sale->created_at,
                'Vente au comptoir',
                [
                    ['role' => $target, 'debit' => $ht + $tax, 'label' => 'Encaissement'],
                    ['role' => LedgerAccount::ROLE_SALES, 'credit' => $ht, 'label' => 'Vente HT'],
                    ['role' => LedgerAccount::ROLE_VAT_COLLECTED, 'credit' => $tax, 'label' => 'TVA collectée'],
                ],
                $sale, $sale->currency ?: 'XOF', $userId
            );
        });
    }

    /**
     * Remet le journal en accord avec une vente au comptoir modifiée ou annulée.
     *
     * Toutes les pièces d'une vente lui sont rattachées : leur solde net est
     * comparé à ce que la vente doit peser (encaissement, vente HT, TVA ; rien
     * si elle est annulée) et seul l'écart est passé. Avant, une modification
     * ne touchait pas le journal et une annulation n'y contrepassait rien.
     */
    public function syncPosSale(PosSale $sale, string $label, ?int $userId = null): ?JournalEntry
    {
        try {
            $role = fn (string $role) => $this->ledger->account($sale->entreprise_id, $role)->id;
            $target = [];
            if ($sale->status !== 'cancelled') {
                $ht = round((float) $sale->total_ht, 2);
                $tax = round((float) $sale->tax_amount, 2);
                $target[$role($sale->payment_method === 'cash' ? LedgerAccount::ROLE_CASH : LedgerAccount::ROLE_BANK)] = $ht + $tax;
                $target[$role(LedgerAccount::ROLE_SALES)] = -$ht;
                $target[$role(LedgerAccount::ROLE_VAT_COLLECTED)] = -$tax;
            }
            $net = $this->ledger->netBySource($sale);

            $lines = [];
            foreach (array_unique(array_merge(array_keys($target), array_keys($net))) as $accountId) {
                $delta = round(($target[$accountId] ?? 0) - ($net[$accountId] ?? 0), 2);
                if (abs($delta) >= 0.01) {
                    $lines[] = ['account_id' => $accountId, 'debit' => max(0, $delta), 'credit' => max(0, -$delta), 'label' => $label];
                }
            }
            if (! $lines) {
                return null;
            }

            return $this->ledger->post(
                $sale->entreprise_id, $sale->payment_method === 'cash' ? 'CA' : 'BQ', now(), $label,
                $lines, $sale, $sale->currency ?: 'XOF', $userId
            );
        } catch (\Throwable $e) {
            // Comme pour les autres pièces : une comptabilité en retard se rattrape, une vente perdue non.
            report($e);

            return null;
        }
    }

    /** Facture fournisseur : charge, TVA deductible et dette fournisseur. */
    public function postSupplierInvoice(SupplierInvoice $invoice, ?int $userId = null): ?JournalEntry
    {
        return $this->guard($invoice, function () use ($invoice, $userId) {
            $ht = (float) $invoice->total_ht;
            $tax = (float) $invoice->tax_amount;

            return $this->ledger->post(
                $invoice->entreprise_id, 'AC',
                $invoice->invoice_date ?: $invoice->created_at,
                'Facture fournisseur ' . ($invoice->supplier_name ?: ''),
                [
                    ['role' => LedgerAccount::ROLE_PURCHASES, 'debit' => $ht, 'label' => 'Achat HT'],
                    ['role' => LedgerAccount::ROLE_VAT_DEDUCTIBLE, 'debit' => $tax, 'label' => 'TVA déductible'],
                    ['role' => LedgerAccount::ROLE_SUPPLIERS, 'credit' => $ht + $tax, 'label' => $invoice->supplier_name],
                ],
                $invoice, $invoice->currency ?: 'XOF', $userId
            );
        });
    }

    /** Avoir : contrepassation de la vente et de sa TVA. */
    public function postCreditNote(CreditNote $note, ?int $userId = null): ?JournalEntry
    {
        return $this->guard($note, function () use ($note, $userId) {
            $ht = (float) $note->total_ht;
            $tax = (float) $note->tax_amount;

            return $this->ledger->post(
                $note->entreprise_id, 'VE',
                $note->created_at,
                'Avoir ' . $note->reference,
                [
                    ['role' => LedgerAccount::ROLE_SALES, 'debit' => $ht, 'label' => 'Annulation de vente'],
                    ['role' => LedgerAccount::ROLE_VAT_COLLECTED, 'debit' => $tax, 'label' => 'TVA sur avoir'],
                    ['role' => LedgerAccount::ROLE_CUSTOMERS, 'credit' => $ht + $tax, 'label' => 'Client'],
                ],
                $note, $note->currency ?: 'XOF', $userId
            );
        });
    }

    /**
     * Reglement client : solde la creance.
     * La piece n'est pas rattachee a la facture, qui porte deja la sienne.
     */
    public function postCustomerPayment(Model $invoice, float $amount, string $method, \DateTimeInterface $date, ?int $userId = null): ?JournalEntry
    {
        if ($amount <= 0) {
            return null;
        }

        $target = $method === 'bank' ? LedgerAccount::ROLE_BANK : LedgerAccount::ROLE_CASH;

        return $this->ledger->post(
            $invoice->entreprise_id, $method === 'bank' ? 'BQ' : 'CA',
            $date,
            'Règlement client',
            [
                ['role' => $target, 'debit' => $amount, 'label' => 'Encaissement'],
                ['role' => LedgerAccount::ROLE_CUSTOMERS, 'credit' => $amount, 'label' => 'Solde créance'],
            ],
            null, $invoice->currency ?: app(TaxService::class)->currencyFor($invoice->entreprise), $userId
        );
    }

    /**
     * Passe l'ecriture une seule fois par document, et n'interrompt jamais
     * l'operation commerciale : une comptabilite en retard se rattrape, une
     * vente perdue non.
     */
    private function guard(Model $source, callable $callback): ?JournalEntry
    {
        if ($this->ledger->alreadyPosted($source)) {
            return null;
        }

        try {
            return $callback();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
