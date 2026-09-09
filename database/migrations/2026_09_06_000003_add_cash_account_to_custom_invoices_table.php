<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('cash_account_id')->nullable()->after('payment_method');
            $table->unsignedBigInteger('bank_account_id')->nullable()->after('cash_account_id');

            $table->foreign('cash_account_id')->references('id')->on('cash_accounts')->nullOnDelete();
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('custom_invoices', function (Blueprint $table) {
            $table->dropForeign(['cash_account_id']);
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn(['cash_account_id', 'bank_account_id']);
        });
    }
};
