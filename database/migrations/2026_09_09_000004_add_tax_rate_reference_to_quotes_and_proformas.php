<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le devis et le proforma ne retenaient qu'un pourcentage. Sans le regime,
 * impossible de distinguer un client exonere d'un client a taux zero : la
 * seule facon de trancher aurait ete de regarder le montant, ce qui est
 * precisement l'erreur a ne pas reproduire.
 */
return new class extends Migration
{
    private array $tables = ['commercial_quotes', 'commercial_proformas'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('tax_regime', 30)->nullable()->after('tax_rate');
                $blueprint->foreignId('tax_rate_id')->nullable()->after('tax_regime')
                    ->constrained('tax_rates')->nullOnDelete();
                $blueprint->string('currency', 10)->default('XOF')->after('tax_rate_id');
            });

            // Reprise : un taux positif etait forcement du taux normal, un taux
            // nul ne peut pas etre qualifie retroactivement.
            DB::statement("UPDATE {$table} SET tax_regime = CASE WHEN tax_rate > 0 THEN 'standard' ELSE 'unknown' END");
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('tax_rate_id');
                $blueprint->dropColumn(['tax_regime', 'currency']);
            });
        }
    }
};
