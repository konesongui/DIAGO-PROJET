<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id');
            $table->string('invoice_number')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('supplier_tax_id')->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 15, 2)->nullable();
            $table->decimal('tax_amount', 15, 2)->nullable();
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->string('currency', 10)->default('XOF');
            $table->string('status')->default('imported');
            $table->string('file_path');
            $table->string('original_filename');
            $table->json('extracted_data')->nullable();
            $table->longText('raw_text')->nullable();
            $table->timestamps();

            $table->foreign('entreprise_id')->references('id')->on('entreprises')->cascadeOnDelete();
            $table->index(['entreprise_id', 'invoice_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};
