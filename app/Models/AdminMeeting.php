<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;

class AdminMeeting extends Model
{
    use BelongsToEntreprise;
    protected $table = 'admin_meetings';
    protected $guarded = ['id'];
    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
}
