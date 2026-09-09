<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('succursale_id')->nullable()->after('entreprise_id')->constrained('succursales')->nullOnDelete();
            $table->index(['entreprise_id', 'succursale_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['succursale_id']);
            $table->dropIndex(['entreprise_id', 'succursale_id']);
            $table->dropColumn('succursale_id');
        });
    }
};
