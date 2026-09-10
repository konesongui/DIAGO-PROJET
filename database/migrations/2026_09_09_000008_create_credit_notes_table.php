<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avoirs.
 *
 * Une facture emise ne se modifie pas et ne se supprime pas : on la corrige
 * en emettant un avoir, qui laisse les deux documents lisibles et tracables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->string('reference');

            // Rattachement au document corrige, quel que soit son type.
            $table->string('creditable_type');
            $table->unsignedBigInteger('creditable_id');

            // Un avoir sans motif n'est pas justifiable devant un controle.
            $table->text('reason');

            $table->decimal('total_ht', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('tax_rate', 6, 3)->default(0);
            $table->string('tax_regime', 30)->nullable();
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->decimal('total_ttc', 15, 2)->default(0);
            $table->string('currency', 10)->default('XOF');

            $table->boolean('is_full')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['entreprise_id', 'reference']);
            $table->index(['creditable_type', 'creditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
