<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InvoicePayment extends Model
{
    use BelongsToEntreprise;

    protected $fillable = [
        'entreprise_id', 'payable_type', 'payable_id', 'amount',
        'method', 'paid_on', 'currency', 'reference', 'created_by_user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_on' => 'date',
    ];

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }
}
