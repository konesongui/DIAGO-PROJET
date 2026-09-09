<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;

class AdminDocument extends Model
{
    use BelongsToEntreprise;
    protected $table = 'admin_documents';
    protected $guarded = ['id'];
    protected $casts = ['document_date' => 'date'];
}
