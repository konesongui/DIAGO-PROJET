<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->string('token', 128)->unique();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->dateTime('expires_at');
            $table->boolean('is_used')->default(false);
            $table->dateTime('used_at')->nullable();
            $table->timestamps();
            $table->index(['entreprise_id', 'is_used', 'expires_at']);
        });

        Schema::create('staff_attendance_qr', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->time('arrival_time')->nullable();
            $table->time('departure_time')->nullable();
            $table->dateTime('scan_date')->nullable();
            $table->string('status', 30)->default('arrival');
            $table->text('photo_path')->nullable();
            $table->string('verification_status', 30)->default('verified');
            $table->text('verification_details')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'attendance_date']);
            $table->index(['entreprise_id', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_attendance_qr');
        Schema::dropIfExists('qr_tokens');
    }
};
