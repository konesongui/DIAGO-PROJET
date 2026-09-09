<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashAccount extends Model
{
    use BelongsToEntreprise;
    protected $fillable = [
        'entreprise_id',
        'name',
        'account_type',
        'description',
        'currency',
        'initial_balance',
        'balance',
        'is_active',
    ];

    protected $casts = [
        'initial_balance' => 'decimal:2',
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'cash_account_id');
    }
}
