<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('reference')->unique();
            $table->string('client_name')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('client_email')->nullable();
            $table->date('quote_date')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('payment_terms')->nullable();
            $table->text('delivery_terms')->nullable();
            $table->text('delivery_location')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('cash_payment_method')->nullable();
            $table->string('subject')->nullable();
            $table->json('items')->nullable();
            $table->json('global_services')->nullable();
            $table->decimal('total_ht', 15, 2)->default(0);
            $table->decimal('total_discount', 15, 2)->default(0);
            $table->decimal('subtotal_after_discount', 15, 2)->default(0);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('total_ttc', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->dateTime('paid_at')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->foreign('entreprise_id')->references('id')->on('entreprises')->onDelete('cascade');
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_invoices');
    }
};
