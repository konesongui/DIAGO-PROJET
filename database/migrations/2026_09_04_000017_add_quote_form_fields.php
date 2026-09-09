<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('commercial_quotes', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('quote_date');
            $table->string('payment_terms')->nullable()->after('due_date');
            $table->string('delivery_terms')->nullable()->after('payment_terms');
            $table->string('delivery_location')->nullable()->after('delivery_terms');
            $table->string('payment_method')->nullable()->after('delivery_location');
            $table->text('subject')->nullable()->after('payment_method');
            $table->decimal('total_discount', 15, 2)->default(0)->after('total_ht');
            $table->decimal('net_ht', 15, 2)->default(0)->after('total_discount');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('net_ht');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_quotes', function (Blueprint $table) {
            $table->dropColumn([
                'due_date', 'payment_terms', 'delivery_terms', 'delivery_location',
                'payment_method', 'subject', 'total_discount', 'net_ht', 'tax_rate', 'tax_amount',
            ]);
        });
    }
};
