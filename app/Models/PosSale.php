<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use App\Models\Concerns\HasTaxLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosSale extends Model
{
    use BelongsToEntreprise;
    use HasTaxLabel;

    protected $fillable = ['entreprise_id', 'reference', 'client_id', 'client_name', 'lines', 'total', 'total_ht','tax_amount','tax_rate','tax_regime','tax_rate_id','currency', 'paid_amount', 'change_amount', 'payment_method', 'cash_account_id', 'bank_account_id', 'status'];
    protected $casts = ['lines' => 'array', 'total' => 'decimal:2','total_ht'=>'decimal:2','tax_amount'=>'decimal:2','tax_rate'=>'decimal:3', 'paid_amount' => 'decimal:2', 'change_amount' => 'decimal:2'];

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /** D'où ressort l'argent si la vente est annulée : « de la caisse « … » » ou « du compte bancaire « … » ». */
    public function refundSourceLabel(): string
    {
        return $this->payment_method === 'cash'
            ? 'de la caisse' . ($this->cashAccount ? ' « ' . $this->cashAccount->name . ' »' : '')
            : 'du compte bancaire' . ($this->bankAccount ? ' « ' . $this->bankAccount->name . ' »' : '');
    }

    /** Caisse ou compte bancaire qui a reçu l'argent. */
    public function paymentLabel(): string
    {
        return $this->payment_method === 'cash'
            ? 'Espèces' . ($this->cashAccount ? ' · ' . $this->cashAccount->name : '')
            : 'Banque' . ($this->bankAccount ? ' · ' . $this->bankAccount->name : '');
    }
}
