<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_exits', function (Blueprint $table) {
            $table->foreignId('delivery_id')->nullable()->unique()->after('entreprise_id')
                ->constrained('commercial_deliveries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_exits', function (Blueprint $table) {
            $table->dropForeign(['delivery_id']);
            $table->dropUnique(['stock_exits_delivery_id_unique']);
            $table->dropColumn('delivery_id');
        });
    }
};
