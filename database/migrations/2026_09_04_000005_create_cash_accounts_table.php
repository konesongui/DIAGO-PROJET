<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id');
            $table->string('name');
            $table->string('account_type')->default('caisse');
            $table->text('description')->nullable();
            $table->string('currency', 10)->default('XOF');
            $table->decimal('initial_balance', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('entreprise_id')->references('id')->on('entreprises')->cascadeOnDelete();
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id');
            $table->unsignedBigInteger('cash_account_id');
            $table->string('movement_type')->default('entry');
            $table->string('label');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('currency', 10)->default('XOF');
            $table->string('payment_mode')->default('cash');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->date('movement_date');
            $table->timestamps();

            $table->foreign('entreprise_id')->references('id')->on('entreprises')->cascadeOnDelete();
            $table->foreign('cash_account_id')->references('id')->on('cash_accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_accounts');
    }
};
