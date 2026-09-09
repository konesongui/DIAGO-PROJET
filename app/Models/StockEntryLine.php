<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockEntryLine extends Model
{
    use BelongsToEntreprise;
    protected $fillable = ['stock_entry_id', 'designation', 'article', 'unit', 'quantity', 'purchase_price', 'profit_per_unit', 'total_purchase', 'total_profit'];
    protected $casts = ['quantity' => 'decimal:3', 'purchase_price' => 'decimal:2', 'profit_per_unit' => 'decimal:2', 'total_purchase' => 'decimal:2', 'total_profit' => 'decimal:2'];

    public function stockEntry(): BelongsTo
    {
        return $this->belongsTo(StockEntry::class);
    }
}
