<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commercial_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
            $table->index(['entreprise_id', 'name']);
        });

        Schema::create('commercial_proformas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('commercial_clients')->nullOnDelete();
            $table->string('client_name');
            $table->string('client_phone')->nullable();
            $table->date('creation_date');
            $table->date('due_date')->nullable();
            $table->string('payment_terms')->nullable();
            $table->string('delivery_terms')->nullable();
            $table->string('delivery_location')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('subject')->nullable();
            $table->decimal('total_ht', 15, 2)->default(0);
            $table->decimal('total_discount', 15, 2)->default(0);
            $table->decimal('net_ht', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_ttc', 15, 2)->default(0);
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('commercial_proforma_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proforma_id')->constrained('commercial_proformas')->cascadeOnDelete();
            $table->string('type')->default('product');
            $table->string('category')->nullable();
            $table->string('item_name');
            $table->string('unit')->nullable();
            $table->decimal('quantity', 15, 3)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->string('discount_type')->default('percent');
            $table->decimal('net_unit_price', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_proforma_lines');
        Schema::dropIfExists('commercial_proformas');
        Schema::dropIfExists('commercial_clients');
    }
};
