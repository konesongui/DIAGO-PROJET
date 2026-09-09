<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_exits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->date('exit_date');
            $table->decimal('total_value', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('stock_exit_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_exit_id')->constrained('stock_exits')->cascadeOnDelete();
            $table->foreignId('stock_entry_line_id')->constrained('stock_entry_lines')->cascadeOnDelete();
            $table->string('designation');
            $table->string('article')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total_value', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_exit_lines');
        Schema::dropIfExists('stock_exits');
    }
};
