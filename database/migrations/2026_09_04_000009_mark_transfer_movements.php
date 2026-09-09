<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_movements', function (Blueprint $table) {
            $table->boolean('is_transfer')->default(false)->after('payment_mode');
        });

        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->boolean('is_transfer')->default(false)->after('transaction_type');
        });

        DB::table('cash_movements')
            ->where(function ($query) {
                $query->where('payment_mode', 'transfer')
                    ->orWhere('label', 'like', 'Transfert %');
            })
            ->update(['is_transfer' => true]);

        DB::table('bank_transactions')
            ->where(function ($query) {
                $query->where('label', 'like', 'Transfert %')
                    ->orWhere('description', 'like', '%Transfert inter-comptes%');
            })
            ->update(['is_transfer' => true]);
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropColumn('is_transfer');
        });

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->dropColumn('is_transfer');
        });
    }
};
