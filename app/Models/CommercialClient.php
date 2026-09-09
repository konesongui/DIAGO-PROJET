<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialClient extends Model
{
    use BelongsToEntreprise;
    protected $fillable = ['entreprise_id', 'name', 'responsible_name', 'phone', 'email', 'city', 'tax_id', 'tax_regime', 'address'];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }
}
