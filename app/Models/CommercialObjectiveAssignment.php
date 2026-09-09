<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialObjectiveAssignment extends Model
{
    use BelongsToEntreprise;
    protected $fillable = ['objective_id', 'employee_id', 'amount', 'starts_at', 'ends_at'];

    protected $casts = ['amount' => 'decimal:2', 'starts_at' => 'date', 'ends_at' => 'date'];

    public function objective(): BelongsTo
    {
        return $this->belongsTo(CommercialObjective::class, 'objective_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
