<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('commercial_quotes', function (Blueprint $table) {
            $table->string('customer_order_code')->nullable()->after('subject');
        });

        Schema::table('commercial_orders', function (Blueprint $table) {
            $table->string('customer_order_code')->nullable()->after('client_name');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_orders', function (Blueprint $table) {
            $table->dropColumn('customer_order_code');
        });
        Schema::table('commercial_quotes', function (Blueprint $table) {
            $table->dropColumn('customer_order_code');
        });
    }
};
