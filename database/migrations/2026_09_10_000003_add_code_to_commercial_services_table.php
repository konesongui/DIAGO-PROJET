<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le catalogue de prestations et ses tarifs etait ecrit en dur dans le
 * controleur, avec des services propres a un seul client. On lui donne un
 * code stable pour le deplacer en base, ou chaque entreprise gere le sien.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_services', function (Blueprint $table) {
            $table->string('code', 60)->nullable()->after('entreprise_id');
            $table->boolean('is_global')->default(false)->after('is_active');

            $table->unique(['entreprise_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('commercial_services', function (Blueprint $table) {
            $table->dropUnique(['entreprise_id', 'code']);
            $table->dropColumn(['code', 'is_global']);
        });
    }
};
