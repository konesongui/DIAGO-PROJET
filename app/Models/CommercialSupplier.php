<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;

class CommercialSupplier extends Model
{
    use BelongsToEntreprise;
    protected $fillable = [
        'entreprise_id', 'name', 'responsible_name', 'tax_id', 'phone', 'email', 'address',
    ];
}
