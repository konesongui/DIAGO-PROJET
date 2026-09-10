<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30)->nullable();

            // 6 chiffres dont 3 decimales : couvre 20,000 / 5,500 / 2,100 / 18,000.
            $table->decimal('rate', 6, 3)->default(0);

            // Regime fiscal de la ligne. Determine ce qui est declare a
            // l'administration, independamment de la valeur du taux.
            $table->string('regime', 30)->default('standard');

            // Date d'entree en vigueur : une facture doit conserver le taux
            // applicable a sa date d'emission, meme reeditee des annees apres.
            $table->date('effective_from')->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['entreprise_id', 'is_active']);
            $table->unique(['entreprise_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
