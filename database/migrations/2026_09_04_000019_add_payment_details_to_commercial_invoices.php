<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('commercial_invoices', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('paid_amount');
            $table->foreignId('cash_account_id')->nullable()->after('payment_method')->constrained('cash_accounts')->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->after('cash_account_id')->constrained('bank_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commercial_invoices', function (Blueprint $table) {
            $table->dropForeign(['cash_account_id']);
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn(['payment_method', 'cash_account_id', 'bank_account_id']);
        });
    }
};
