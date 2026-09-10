<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compteur de numerotation, une ligne par entreprise, type de document et
 * periode. Remplace le comptage des documents existants, qui donnait le meme
 * numero a deux utilisateurs simultanes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->string('document_type', 40);

            // Periode de remise a zero : '2026' pour un compteur annuel,
            // '20260909' pour un compteur journalier.
            $table->string('period', 20);

            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['entreprise_id', 'document_type', 'period'], 'document_sequences_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
