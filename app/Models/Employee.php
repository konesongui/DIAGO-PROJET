<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;
    use BelongsToEntreprise;

    protected $fillable = [
        'entreprise_id',
        'user_id',
        'matricule',
        'civility',
        'full_name',
        'first_name', 'father_name', 'mother_name', 'cnps_number', 'gender',
        'contract_type', 'nationality', 'birth_date', 'registered_at', 'contract_end_date',
        'phone', 'emergency_contact', 'marital_status', 'salary_category', 'address',
        'permanent_address', 'qualification', 'experience', 'remark',
        'paternity_leave', 'maternity_leave', 'annual_leave',
        'children_count', 'sursalary', 'seniority_bonus', 'transport_allowance',
        'overtime_hours', 'responsibility_bonus', 'bonus', 'performance_bonus',
        'risk_bonus', 'attendance_bonus', 'gratification', 'leave_pay', 'income_tax',
        'cmu', 'other_deductions', 'indemnities', 'part_igr', 'payment_mode',
        'account_title', 'bank_account_number', 'bank_name', 'ifsc_code', 'bank_branch',
        'facebook_url', 'twitter_url', 'linkedin_url', 'instagram_url', 'photo_path', 'documents',
        'email',
        'position',
        'department',
        'status',
        'monthly_salary',
        'hire_date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected $casts = [
        'monthly_salary' => 'decimal:2',
        'sursalary' => 'decimal:2',
        'seniority_bonus' => 'decimal:2',
        'transport_allowance' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'responsibility_bonus' => 'decimal:2',
        'bonus' => 'decimal:2',
        'performance_bonus' => 'decimal:2',
        'risk_bonus' => 'decimal:2',
        'attendance_bonus' => 'decimal:2',
        'gratification' => 'decimal:2',
        'leave_pay' => 'decimal:2',
        'income_tax' => 'decimal:2',
        'cmu' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'indemnities' => 'decimal:2',
        'part_igr' => 'decimal:1',
        'children_count' => 'integer',
        'hire_date' => 'date',
        'documents' => 'array',
        'birth_date' => 'date',
        'registered_at' => 'date',
        'contract_end_date' => 'date',
    ];

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }
}
