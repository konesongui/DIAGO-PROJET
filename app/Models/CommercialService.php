<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialService extends Model
{
    use BelongsToEntreprise;

    protected $fillable = ['entreprise_id', 'code', 'name', 'description', 'price', 'unit', 'is_active', 'is_global'];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_global' => 'boolean',
    ];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }
}
