<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CreditNote extends Model
{
    use BelongsToEntreprise;

    protected $fillable = [
        'entreprise_id', 'reference', 'creditable_type', 'creditable_id', 'reason',
        'total_ht', 'tax_amount', 'tax_rate', 'tax_regime', 'tax_rate_id',
        'total_ttc', 'currency', 'is_full', 'created_by_user_id',
    ];

    protected $casts = [
        'total_ht' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_rate' => 'decimal:3',
        'total_ttc' => 'decimal:2',
        'is_full' => 'boolean',
    ];

    public function creditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
