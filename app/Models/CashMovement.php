<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    use BelongsToEntreprise;
    protected $fillable = [
        'entreprise_id',
        'cash_account_id',
        'expense_category_id',
        'movement_type',
        'label',
        'amount',
        'currency',
        'payment_mode',
        'is_transfer',
        'reference',
        'description',
        'movement_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_transfer' => 'boolean',
        'movement_date' => 'date',
    ];

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }
}
