<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commercial_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('commercial_clients')->nullOnDelete();
            $table->string('client_name');
            $table->date('quote_date');
            $table->decimal('total_ht', 15, 2)->default(0);
            $table->decimal('total_ttc', 15, 2)->default(0);
            $table->json('lines');
            $table->string('status')->default('pending_validation');
            $table->timestamps();
        });
        Schema::create('commercial_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->foreignId('quote_id')->constrained('commercial_quotes')->cascadeOnDelete();
            $table->string('client_name');
            $table->json('lines');
            $table->decimal('total_ttc', 15, 2)->default(0);
            $table->string('status')->default('pending_delivery');
            $table->timestamps();
        });
        Schema::create('commercial_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('commercial_orders')->cascadeOnDelete();
            $table->string('client_name');
            $table->json('lines');
            $table->string('delivery_type')->default('partial');
            $table->string('status')->default('pending_validation');
            $table->timestamps();
        });
        Schema::create('commercial_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->foreignId('delivery_id')->constrained('commercial_deliveries')->cascadeOnDelete();
            $table->string('client_name');
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('status')->default('unpaid');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_invoices');
        Schema::dropIfExists('commercial_deliveries');
        Schema::dropIfExists('commercial_orders');
        Schema::dropIfExists('commercial_quotes');
    }
};
