<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;

class CommercialProformaLine extends Model
{
    use BelongsToEntreprise;
    protected $fillable = [
        'proforma_id', 'type', 'category', 'item_name', 'unit', 'quantity', 'unit_price',
        'discount', 'discount_type', 'net_unit_price', 'line_total',
    ];
}
