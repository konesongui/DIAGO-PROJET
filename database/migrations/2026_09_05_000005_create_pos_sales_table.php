<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pos_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('commercial_clients')->nullOnDelete();
            $table->string('client_name')->nullable();
            $table->json('lines');
            $table->decimal('total', 15, 2)->default(0);
            $table->string('payment_method');
            $table->foreignId('cash_account_id')->nullable()->constrained('cash_accounts')->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sales');
    }
};
