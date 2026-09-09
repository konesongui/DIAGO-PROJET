<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('entreprise_id')->references('id')->on('entreprises')->cascadeOnDelete();
            $table->unique(['entreprise_id', 'name']);
        });

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('expense_category_id')->nullable()->after('cash_account_id');
            $table->foreign('expense_category_id')->references('id')->on('expense_categories')->nullOnDelete();
            $table->index(['entreprise_id', 'expense_category_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cash_movements', function (Blueprint $table) {
            $table->dropForeign(['expense_category_id']);
            $table->dropIndex(['entreprise_id', 'expense_category_id']);
            $table->dropColumn('expense_category_id');
        });

        Schema::dropIfExists('expense_categories');
    }
};
