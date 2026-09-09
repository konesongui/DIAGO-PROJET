<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;

class FneCertificationLog extends Model
{
    use BelongsToEntreprise;
    protected $fillable = [
        'entreprise_id', 'certifiable_type', 'certifiable_id', 'document_type',
        'request_payload', 'response_payload', 'http_code', 'status', 'error_message',
    ];

    protected $casts = ['request_payload' => 'array', 'response_payload' => 'array'];
}
