<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['commercial_quotes', 'commercial_orders', 'commercial_deliveries', 'commercial_invoices'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->foreignId('created_by_user_id')->nullable()->after('entreprise_id')
                    ->constrained('users')->nullOnDelete();
                if ($tableName === 'commercial_quotes') {
                    $table->string('reference')->nullable()->unique()->after('created_by_user_id');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['commercial_invoices', 'commercial_deliveries', 'commercial_orders', 'commercial_quotes'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropForeign(['created_by_user_id']);
                $table->dropColumn('created_by_user_id');
                if ($tableName === 'commercial_quotes') {
                    $table->dropUnique(['commercial_quotes_reference_unique']);
                    $table->dropColumn('reference');
                }
            });
        }
    }
};
