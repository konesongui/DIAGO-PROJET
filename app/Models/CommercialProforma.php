<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialProforma extends Model
{
    use BelongsToEntreprise;
    protected $fillable = [
        'entreprise_id', 'client_id', 'client_name', 'client_phone', 'creation_date', 'due_date',
        'payment_terms', 'delivery_terms', 'delivery_location', 'payment_method', 'subject',
        'total_ht', 'total_discount', 'net_ht', 'tax_amount', 'tax_rate', 'total_ttc', 'status',
    ];

    protected $casts = [
        'creation_date' => 'date',
        'due_date' => 'date',
        'total_ht' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'net_ht' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'total_ttc' => 'decimal:2',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(CommercialProformaLine::class, 'proforma_id');
    }
}
