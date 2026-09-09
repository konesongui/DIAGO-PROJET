<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;

class QrToken extends Model
{
    use BelongsToEntreprise;

    protected $fillable = [
        'entreprise_id', 'token', 'employee_id', 'expires_at', 'is_used', 'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'is_used' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
