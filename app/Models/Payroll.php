<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;
    use BelongsToEntreprise;

    protected $fillable = [
        'entreprise_id', 'employee_id', 'month', 'year', 'part_igr', 'children_count',
        'overtime_hours', 'payment_mode', 'base_salary', 'sursalary', 'allowances',
        'transport_allowance', 'responsibility_bonus', 'bonus', 'performance_bonus', 'risk_bonus',
        'attendance_bonus', 'gratification', 'leave_pay', 'indemnities', 'seniority_bonus', 'deductions',
        'gross_salary', 'fiscal_gross', 'social_gross', 'cnps_employee', 'income_tax', 'cmu',
        'cnps_employer', 'work_accident', 'family_benefits', 'fdfp_apprenticeship',
        'fdfp_training', 'total_employee_deductions', 'total_employer_deductions',
        'net_salary', 'employer_charges', 'notes', 'sent_at',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'sursalary' => 'decimal:2',
        'allowances' => 'decimal:2',
        'transport_allowance' => 'decimal:2',
        'responsibility_bonus' => 'decimal:2',
        'bonus' => 'decimal:2',
        'performance_bonus' => 'decimal:2',
        'risk_bonus' => 'decimal:2',
        'attendance_bonus' => 'decimal:2',
        'gratification' => 'decimal:2',
        'leave_pay' => 'decimal:2',
        'indemnities' => 'decimal:2',
        'seniority_bonus' => 'decimal:2',
        'deductions' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'fiscal_gross' => 'decimal:2',
        'social_gross' => 'decimal:2',
        'cnps_employee' => 'decimal:2',
        'income_tax' => 'decimal:2',
        'cmu' => 'decimal:2',
        'cnps_employer' => 'decimal:2',
        'work_accident' => 'decimal:2',
        'family_benefits' => 'decimal:2',
        'fdfp_apprenticeship' => 'decimal:2',
        'fdfp_training' => 'decimal:2',
        'total_employee_deductions' => 'decimal:2',
        'total_employer_deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'employer_charges' => 'decimal:2',
        'part_igr' => 'decimal:1',
        'children_count' => 'integer',
        'overtime_hours' => 'decimal:2',
        'sent_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
