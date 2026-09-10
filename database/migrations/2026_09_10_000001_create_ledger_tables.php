<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comptabilite en partie double.
 *
 * La table existante n'avait qu'un sens par ligne, debit OU credit, sans rien
 * qui relie un debit a son credit : aucune verification d'equilibre n'etait
 * possible. On separe donc la PIECE comptable de ses LIGNES, ce qui permet
 * d'exiger que la somme des debits egale celle des credits.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Plan comptable, propre a chaque entreprise pour rester utilisable
        // hors de la zone OHADA.
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name');

            // Nature du compte : determine son sens normal et son classement
            // au bilan ou au compte de resultat.
            $table->string('type', 20); // asset, liability, equity, revenue, expense
            $table->string('role', 40)->nullable(); // customers, suppliers, vat_collected, ...
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['entreprise_id', 'code']);
            $table->index(['entreprise_id', 'role']);
        });

        // Piece comptable : un evenement, une date, un journal.
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->string('reference');
            $table->string('journal', 20)->default('OD'); // VE ventes, AC achats, BQ banque, CA caisse, OD divers
            $table->date('entry_date');
            $table->string('label');

            // Document a l'origine de l'ecriture, pour remonter a la source.
            $table->string('sourceable_type')->nullable();
            $table->unsignedBigInteger('sourceable_id')->nullable();

            $table->string('currency', 10)->default('XOF');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['entreprise_id', 'reference']);
            $table->index(['sourceable_type', 'sourceable_id']);
            $table->index(['entreprise_id', 'entry_date']);
        });

        // Lignes : chaque ligne porte un compte et un seul sens.
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->string('label')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('ledger_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('ledger_accounts');
    }
};
