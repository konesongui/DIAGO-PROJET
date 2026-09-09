<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    use BelongsToEntreprise;
    protected $fillable = [
        'entreprise_id',
        'name',
        'bank_name',
        'short_name',
        'account_number',
        'account_type',
        'current_balance',
        'status',
        'logo_bg',
        'logo_text',
        'last_transaction_label',
        'last_transaction_amount',
        'last_transaction_direction',
        'description',
    ];

    protected $casts = [
        'current_balance' => 'decimal:2',
        'last_transaction_amount' => 'decimal:2',
    ];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class, 'bank_account_id');
    }
}
