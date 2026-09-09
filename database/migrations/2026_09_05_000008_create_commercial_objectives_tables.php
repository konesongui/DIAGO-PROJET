<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commercial_objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('objective_date');
            $table->timestamps();
            $table->index(['entreprise_id', 'objective_date']);
        });

        Schema::create('commercial_objective_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('objective_id')->constrained('commercial_objectives')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('starts_at');
            $table->date('ends_at');
            $table->timestamps();
            $table->index(['objective_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_objective_assignments');
        Schema::dropIfExists('commercial_objectives');
    }
};
