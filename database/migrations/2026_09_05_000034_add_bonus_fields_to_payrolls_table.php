<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->decimal('bonus', 12, 2)->default(0)->after('responsibility_bonus');
            $table->decimal('indemnities', 12, 2)->default(0)->after('leave_pay');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['bonus', 'indemnities']);
        });
    }
};
