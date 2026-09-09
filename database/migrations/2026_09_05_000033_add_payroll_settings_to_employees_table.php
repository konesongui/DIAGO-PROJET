<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedInteger('children_count')->default(0)->after('annual_leave');
            $table->decimal('sursalary', 12, 2)->default(0)->after('monthly_salary');
            $table->decimal('seniority_bonus', 12, 2)->default(0)->after('sursalary');
            $table->decimal('transport_allowance', 12, 2)->default(0)->after('seniority_bonus');
            $table->decimal('overtime_hours', 8, 2)->default(0)->after('transport_allowance');
            $table->decimal('responsibility_bonus', 12, 2)->default(0)->after('overtime_hours');
            $table->decimal('bonus', 12, 2)->default(0)->after('responsibility_bonus');
            $table->decimal('performance_bonus', 12, 2)->default(0)->after('bonus');
            $table->decimal('risk_bonus', 12, 2)->default(0)->after('performance_bonus');
            $table->decimal('attendance_bonus', 12, 2)->default(0)->after('risk_bonus');
            $table->decimal('gratification', 12, 2)->default(0)->after('attendance_bonus');
            $table->decimal('leave_pay', 12, 2)->default(0)->after('gratification');
            $table->decimal('income_tax', 12, 2)->default(0)->after('leave_pay');
            $table->decimal('cmu', 12, 2)->default(0)->after('income_tax');
            $table->decimal('other_deductions', 12, 2)->default(0)->after('cmu');
            $table->decimal('indemnities', 12, 2)->default(0)->after('other_deductions');
            $table->decimal('part_igr', 4, 1)->default(1)->after('indemnities');
            $table->string('payment_mode')->nullable()->after('part_igr');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'children_count', 'sursalary', 'seniority_bonus', 'transport_allowance',
                'overtime_hours', 'responsibility_bonus', 'bonus', 'performance_bonus',
                'risk_bonus', 'attendance_bonus', 'gratification', 'leave_pay', 'income_tax',
                'cmu', 'other_deductions', 'indemnities', 'part_igr', 'payment_mode',
            ]);
        });
    }
};
