<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountTransfer extends Model
{
    use BelongsToEntreprise;
    protected $fillable = [
        'entreprise_id',
        'source_type',
        'source_id',
        'destination_type',
        'destination_id',
        'amount',
        'reference',
        'description',
        'transfer_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transfer_date' => 'date',
    ];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }
}
