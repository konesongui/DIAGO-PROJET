<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Succursale extends Model
{
    use HasFactory;

    protected $fillable = [
        'entreprise_id',
        'name',
        'code',
        'address',
        'city',
        'phone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
