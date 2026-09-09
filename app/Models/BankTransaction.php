<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    use BelongsToEntreprise;
    protected $fillable = [
        'entreprise_id',
        'bank_account_id',
        'transaction_type',
        'is_transfer',
        'label',
        'amount',
        'description',
        'transaction_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_transfer' => 'boolean',
        'transaction_date' => 'date',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }
}
