<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->decimal('sursalary', 12, 2)->default(0)->after('base_salary');
            $table->decimal('transport_allowance', 12, 2)->default(0)->after('allowances');
            $table->decimal('responsibility_bonus', 12, 2)->default(0)->after('transport_allowance');
            $table->decimal('performance_bonus', 12, 2)->default(0)->after('responsibility_bonus');
            $table->decimal('risk_bonus', 12, 2)->default(0)->after('performance_bonus');
            $table->decimal('attendance_bonus', 12, 2)->default(0)->after('risk_bonus');
            $table->decimal('gratification', 12, 2)->default(0)->after('attendance_bonus');
            $table->decimal('leave_pay', 12, 2)->default(0)->after('gratification');
            $table->decimal('seniority_bonus', 12, 2)->default(0)->after('leave_pay');
            $table->decimal('fiscal_gross', 12, 2)->default(0)->after('gross_salary');
            $table->decimal('social_gross', 12, 2)->default(0)->after('fiscal_gross');
            $table->decimal('cnps_employer', 12, 2)->default(0)->after('cnps_employee');
            $table->decimal('work_accident', 12, 2)->default(0)->after('cnps_employer');
            $table->decimal('family_benefits', 12, 2)->default(0)->after('work_accident');
            $table->decimal('fdfp_apprenticeship', 12, 2)->default(0)->after('family_benefits');
            $table->decimal('fdfp_training', 12, 2)->default(0)->after('fdfp_apprenticeship');
            $table->decimal('total_employee_deductions', 12, 2)->default(0)->after('fdfp_training');
            $table->decimal('total_employer_deductions', 12, 2)->default(0)->after('total_employee_deductions');
            $table->decimal('part_igr', 4, 1)->default(1)->after('year');
            $table->unsignedInteger('children_count')->default(0)->after('part_igr');
            $table->decimal('overtime_hours', 8, 2)->default(0)->after('children_count');
            $table->string('payment_mode')->nullable()->after('overtime_hours');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn([
                'sursalary', 'transport_allowance', 'responsibility_bonus', 'performance_bonus',
                'risk_bonus', 'attendance_bonus', 'gratification', 'leave_pay', 'seniority_bonus',
                'fiscal_gross', 'social_gross', 'cnps_employer', 'work_accident', 'family_benefits',
                'fdfp_apprenticeship', 'fdfp_training', 'total_employee_deductions',
                'total_employer_deductions', 'part_igr', 'children_count', 'overtime_hours', 'payment_mode',
            ]);
        });
    }
};
