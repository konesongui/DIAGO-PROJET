<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use BelongsToEntreprise;
    use HasFactory;

    protected $fillable = [
        'entreprise_id',
        'company_name',
        'contact_name',
        'email',
        'phone',
        'status',
        'value',
        'source',
        'next_step_at',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'next_step_at' => 'datetime',
    ];

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }
}
