<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomInvoice extends Model
{
    use BelongsToEntreprise;

    protected $fillable = [
        'entreprise_id',
        'created_by_user_id',
        'reference',
        'client_name',
        'client_phone',
        'client_email',
        'quote_date',
        'valid_until',
        'payment_terms',
        'delivery_terms',
        'delivery_location',
        'payment_method',
        'cash_account_id',
        'bank_account_id',
        'cash_payment_method',
        'subject',
        'items',
        'global_services',
        'total_ht',
        'total_discount',
        'subtotal_after_discount',
        'vat_amount',
        'total_ttc',
        'paid_amount',
        'paid_at',
        'status',
    ];

    protected $casts = [
        'quote_date' => 'date',
        'valid_until' => 'date',
        'items' => 'array',
        'global_services' => 'array',
        'total_ht' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'subtotal_after_discount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total_ttc' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
