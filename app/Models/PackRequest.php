<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackRequest extends Model
{
    protected $fillable = [
        'pack_name',
        'name',
        'email',
        'phone',
        'message',
        'status',
        'admin_reply',
        'replied_at',
    ];

    protected $casts = [
        'replied_at' => 'datetime',
    ];
}
