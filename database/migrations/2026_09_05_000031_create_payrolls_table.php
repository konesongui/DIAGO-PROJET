<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->decimal('allowances', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('gross_salary', 12, 2)->default(0);
            $table->decimal('cnps_employee', 12, 2)->default(0);
            $table->decimal('income_tax', 12, 2)->default(0);
            $table->decimal('cmu', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2)->default(0);
            $table->decimal('employer_charges', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
