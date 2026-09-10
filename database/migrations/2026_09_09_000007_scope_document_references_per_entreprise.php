<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L'unicite des references etait GLOBALE alors que la numerotation est propre
 * a chaque entreprise. Consequence : la deuxieme entreprise ne pouvait pas
 * creer sa premiere facture du jour, la reference etant deja prise par une
 * autre societe. L'unicite doit porter sur le couple entreprise + reference.
 */
return new class extends Migration
{
    private array $targets = [
        'custom_invoices' => ['column' => 'reference', 'old' => 'custom_invoices_reference_unique'],
        'commercial_quotes' => ['column' => 'reference', 'old' => 'commercial_quotes_reference_unique'],
    ];

    public function up(): void
    {
        foreach ($this->targets as $table => $config) {
            Schema::table($table, function (Blueprint $blueprint) use ($config) {
                $blueprint->dropUnique($config['old']);
                $blueprint->unique(['entreprise_id', $config['column']]);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->targets as $table => $config) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $config) {
                $blueprint->dropUnique([$table === 'custom_invoices' ? 'entreprise_id' : 'entreprise_id', $config['column']]);
                $blueprint->unique($config['column'], $config['old']);
            });
        }
    }
};
