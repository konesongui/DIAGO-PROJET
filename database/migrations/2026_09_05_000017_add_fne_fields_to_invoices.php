<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['commercial_invoices', 'supplier_invoices'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('fne_status')->default('not_certified');
                $table->string('fne_reference')->nullable();
                $table->text('fne_token')->nullable();
                $table->decimal('fne_balance_sticker', 15, 2)->nullable();
                $table->json('fne_response')->nullable();
                $table->text('fne_error')->nullable();
                $table->timestamp('fne_certified_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['commercial_invoices', 'supplier_invoices'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['fne_status', 'fne_reference', 'fne_token', 'fne_balance_sticker', 'fne_response', 'fne_error', 'fne_certified_at']);
            });
        }
    }
};
