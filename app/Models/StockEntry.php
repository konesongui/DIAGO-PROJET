<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockEntry extends Model
{
    use BelongsToEntreprise;
    protected $fillable = ['entreprise_id', 'entry_date', 'total_purchase', 'total_profit'];
    protected $casts = ['entry_date' => 'date', 'total_purchase' => 'decimal:2', 'total_profit' => 'decimal:2'];

    public function lines(): HasMany
    {
        return $this->hasMany(StockEntryLine::class);
    }
}
