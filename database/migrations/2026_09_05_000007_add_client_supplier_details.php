<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('commercial_clients', function (Blueprint $table) {
            $table->string('city')->nullable()->after('email');
            $table->string('tax_id')->nullable()->after('city');
            $table->string('tax_regime')->nullable()->after('tax_id');
            $table->text('address')->nullable()->after('tax_regime');
            $table->string('responsible_name')->nullable()->after('name');
        });

        Schema::create('commercial_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->string('name');
            $table->string('responsible_name')->nullable();
            $table->string('tax_id');
            $table->string('phone');
            $table->string('email');
            $table->text('address')->nullable();
            $table->timestamps();
            $table->index(['entreprise_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_suppliers');
        Schema::table('commercial_clients', function (Blueprint $table) {
            $table->dropColumn(['city', 'tax_id', 'tax_regime', 'address', 'responsible_name']);
        });
    }
};
