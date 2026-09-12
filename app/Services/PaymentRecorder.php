<?php

namespace App\Services;

use App\Models\InvoicePayment;
use Illuminate\Database\Eloquent\Model;

/**
 * Enregistre chaque reglement comme une ligne datee.
 *
 * C'est ce journal qui permet de calculer la TVA sur les encaissements :
 * sans date par fraction encaissee, ce regime est incalculable.
 */
class PaymentRecorder
{
    public function record(
        Model $invoice,
        float $amount,
        string $method,
        ?\DateTimeInterface $paidOn = null,
        ?int $userId = null,
        ?string $reference = null,
    ): ?InvoicePayment {
        if ($amount <= 0) {
            return null;
        }

        return InvoicePayment::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $invoice->entreprise_id,
            'payable_type' => $invoice::class,
            'payable_id' => $invoice->getKey(),
            'amount' => round($amount, 2),
            'method' => in_array($method, ['cash', 'bank', 'transfer'], true) ? $method : 'cash',
            'paid_on' => ($paidOn ?: now())->format('Y-m-d'),
            // Sans devise propre (facture personnalisée), celle de l'entreprise.
            'currency' => $invoice->currency ?: app(TaxService::class)->currencyFor($invoice->entreprise),
            'reference' => $reference,
            'created_by_user_id' => $userId,
        ]);
    }
}
