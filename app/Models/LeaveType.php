<?php
namespace App\Models;
use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
class LeaveType extends Model {
    use BelongsToEntreprise;
    protected $fillable = ['entreprise_id','name','days','description','is_active'];
    protected $casts = ['is_active'=>'boolean'];
    public function requests() { return $this->hasMany(LeaveRequest::class); }
}
