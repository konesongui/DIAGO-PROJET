<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockExitLine extends Model
{
    use BelongsToEntreprise;
    protected $fillable = ['stock_exit_id', 'stock_entry_line_id', 'designation', 'article', 'unit', 'quantity', 'unit_price', 'total_value'];
    protected $casts = ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'total_value' => 'decimal:2'];

    public function entryLine(): BelongsTo
    {
        return $this->belongsTo(StockEntryLine::class, 'stock_entry_line_id');
    }
}
