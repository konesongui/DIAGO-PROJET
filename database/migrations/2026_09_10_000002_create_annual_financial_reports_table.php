<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bilan financier annuel.
 *
 * Le bilan d'un exercice clos est fige : on enregistre le resultat calcule
 * plutot que de le recalculer a chaque affichage, pour qu'une ecriture passee
 * tardivement ne modifie pas un bilan deja presente au dirigeant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annual_financial_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');

            // Photographie du bilan et du compte de resultat a la cloture.
            $table->json('data');

            $table->decimal('total_assets', 18, 2)->default(0);
            $table->decimal('total_liabilities', 18, 2)->default(0);
            $table->decimal('net_result', 18, 2)->default(0);
            $table->string('currency', 10)->default('XOF');

            $table->timestamp('generated_at')->nullable();

            // Suivi de la lecture : tant que le PDF n'est pas telecharge, le
            // bilan reste signale comme non lu sur le tableau de bord.
            $table->timestamp('seen_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->foreignId('downloaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['entreprise_id', 'fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annual_financial_reports');
    }
};
