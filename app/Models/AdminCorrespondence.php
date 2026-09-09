<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;

class AdminCorrespondence extends Model
{
    use BelongsToEntreprise;
    protected $table = 'admin_correspondences';
    protected $guarded = ['id'];
    protected $casts = ['received_at' => 'date'];
}
