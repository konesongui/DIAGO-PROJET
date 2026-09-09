<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;

class Designation extends Model
{
    use BelongsToEntreprise;
    protected $fillable = ['entreprise_id', 'name', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
