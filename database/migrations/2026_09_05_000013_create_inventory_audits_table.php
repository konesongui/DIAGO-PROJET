<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->string('designation', 100);
            $table->string('article', 80)->nullable();
            $table->string('unit', 30)->nullable();
            $table->decimal('theoretical_quantity', 15, 3);
            $table->decimal('actual_quantity', 15, 3);
            $table->decimal('variance', 15, 3);
            $table->date('audited_at');
            $table->foreignId('audited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['entreprise_id', 'designation', 'article', 'unit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_audits');
    }
};
