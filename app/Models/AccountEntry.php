<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountEntry extends Model
{
    use HasFactory;
    use BelongsToEntreprise;

    protected $fillable = [
        'reference',
        'label',
        'account_code',
        'type',
        'amount',
        'currency',
        'posted_at',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'posted_at' => 'datetime',
    ];
}
