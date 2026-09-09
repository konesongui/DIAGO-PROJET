<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAsset extends Model
{
    use BelongsToEntreprise;
    protected $fillable = [
        'entreprise_id',
        'name',
        'asset_category',
        'reference',
        'supplier',
        'location',
        'acquisition_date',
        'acquisition_value',
        'residual_value',
        'useful_life_years',
        'status',
        'description',
    ];

    protected $casts = [
        'acquisition_date' => 'date',
        'acquisition_value' => 'decimal:2',
        'residual_value' => 'decimal:2',
        'useful_life_years' => 'integer',
    ];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }
}
