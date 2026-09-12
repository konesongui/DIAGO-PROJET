<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Inventaire : comptage physique daté, dont les écarts ont corrigé le stock. */
class StockInventory extends Model
{
    use BelongsToEntreprise;

    protected $fillable = [
        'entreprise_id', 'inventoried_at', 'note', 'counted_items', 'variance_items',
        'shortage_value', 'surplus_value', 'counted_by_user_id',
    ];

    protected $casts = [
        'inventoried_at' => 'date',
        'shortage_value' => 'decimal:2',
        'surplus_value' => 'decimal:2',
    ];

    public function audits(): HasMany
    {
        return $this->hasMany(InventoryAudit::class);
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by_user_id');
    }
}
