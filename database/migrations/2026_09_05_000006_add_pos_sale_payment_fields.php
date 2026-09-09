<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->decimal('paid_amount', 15, 2)->default(0)->after('total');
            $table->decimal('change_amount', 15, 2)->default(0)->after('paid_amount');
            $table->string('status')->default('completed')->after('bank_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropColumn(['paid_amount', 'change_amount', 'status']);
        });
    }
};
