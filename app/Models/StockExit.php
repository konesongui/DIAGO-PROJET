<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockExit extends Model
{
    use BelongsToEntreprise;
    protected $fillable = ['entreprise_id', 'delivery_id', 'exit_date', 'total_value'];
    protected $casts = ['exit_date' => 'date', 'total_value' => 'decimal:2'];

    public function lines(): HasMany
    {
        return $this->hasMany(StockExitLine::class);
    }
}
