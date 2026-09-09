<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;

class SalaryCategory extends Model
{
    use BelongsToEntreprise;

    protected $fillable = ['entreprise_id', 'name', 'amount', 'description', 'is_active'];

    protected $casts = ['amount' => 'decimal:2', 'is_active' => 'boolean'];
}
