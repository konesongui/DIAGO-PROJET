<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialObjective extends Model
{
    use BelongsToEntreprise;
    protected $fillable = ['entreprise_id', 'amount', 'objective_date'];

    protected $casts = ['amount' => 'decimal:2', 'objective_date' => 'date'];

    public function assignments(): HasMany
    {
        return $this->hasMany(CommercialObjectiveAssignment::class, 'objective_id');
    }
}
