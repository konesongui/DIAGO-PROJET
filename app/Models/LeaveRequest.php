<?php
namespace App\Models;
use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
class LeaveRequest extends Model {
    use BelongsToEntreprise;
    protected $fillable = ['entreprise_id','employee_id','leave_type_id','start_date','end_date','days','reason','status','reviewed_by','review_comment'];
    protected $casts = ['start_date'=>'date','end_date'=>'date'];
    public function employee() { return $this->belongsTo(Employee::class); }
    public function leaveType() { return $this->belongsTo(LeaveType::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
