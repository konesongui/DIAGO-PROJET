<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;

class AdminCall extends Model
{
    use BelongsToEntreprise;
    protected $table = 'admin_calls';
    protected $guarded = ['id'];
    protected $casts = ['call_at' => 'datetime'];
}
