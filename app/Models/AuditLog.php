<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'actor_id',
        'target_user_id',
        'entreprise_id',
        'action',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
