<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commercial_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->string('unit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['entreprise_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_services');
    }
};
