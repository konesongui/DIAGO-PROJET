<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;

class PermissionRequest extends Model
{
    use BelongsToEntreprise;

    protected $fillable = [
        'entreprise_id', 'employee_id', 'type', 'start_date', 'end_date',
        'reason', 'status', 'reviewed_by', 'review_comment',
    ];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
