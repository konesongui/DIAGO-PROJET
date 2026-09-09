<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;

class StaffAttendanceQr extends Model
{
    use BelongsToEntreprise;

    protected $table = 'staff_attendance_qr';

    protected $fillable = [
        'entreprise_id', 'employee_id', 'attendance_date', 'arrival_time', 'departure_time',
        'scan_date', 'status', 'photo_path', 'verification_status', 'verification_details',
        'verified_at',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'scan_date' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
