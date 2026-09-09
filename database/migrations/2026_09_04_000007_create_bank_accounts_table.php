<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id');
            $table->string('name');
            $table->string('bank_name');
            $table->string('short_name', 20)->nullable();
            $table->string('account_number');
            $table->string('account_type')->default('compte_courant');
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->string('status')->default('credit');
            $table->string('logo_bg')->nullable();
            $table->string('logo_text')->nullable();
            $table->string('last_transaction_label')->nullable();
            $table->decimal('last_transaction_amount', 15, 2)->default(0);
            $table->string('last_transaction_direction')->default('up');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('entreprise_id')->references('id')->on('entreprises')->cascadeOnDelete();
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id');
            $table->unsignedBigInteger('bank_account_id');
            $table->string('transaction_type')->default('credit');
            $table->string('label');
            $table->decimal('amount', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->date('transaction_date');
            $table->timestamps();

            $table->foreign('entreprise_id')->references('id')->on('entreprises')->cascadeOnDelete();
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_accounts');
    }
};
