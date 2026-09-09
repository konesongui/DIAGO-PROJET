<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->date('entry_date');
            $table->decimal('total_purchase', 15, 2)->default(0);
            $table->decimal('total_profit', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('stock_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_entry_id')->constrained('stock_entries')->cascadeOnDelete();
            $table->string('designation');
            $table->string('article')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('quantity', 15, 3);
            $table->decimal('purchase_price', 15, 2)->default(0);
            $table->decimal('profit_per_unit', 15, 2)->default(0);
            $table->decimal('total_purchase', 15, 2)->default(0);
            $table->decimal('total_profit', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_entry_lines');
        Schema::dropIfExists('stock_entries');
    }
};
