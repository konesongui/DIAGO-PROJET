<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;

class AdminVisitor extends Model
{
    use BelongsToEntreprise;
    protected $table = 'admin_visitors';
    protected $guarded = ['id'];
    protected $casts = ['check_in_at' => 'datetime', 'check_out_at' => 'datetime'];
}
