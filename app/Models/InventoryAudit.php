<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;

class InventoryAudit extends Model
{
    use BelongsToEntreprise;
    protected $fillable = [
        'entreprise_id', 'designation', 'article', 'unit',
        'theoretical_quantity', 'actual_quantity', 'variance',
        'audited_at', 'audited_by_user_id',
    ];

    protected $casts = [
        'theoretical_quantity' => 'decimal:3',
        'actual_quantity' => 'decimal:3',
        'variance' => 'decimal:3',
        'audited_at' => 'date',
    ];
}
