<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verrou d'emission.
 *
 * Le logiciel n'avait aucune notion de facture "emise" : une facture remise
 * au client restait modifiable et supprimable. On date l'emission, ce qui
 * fige le document, et on suit le montant deja porte en avoir pour ne pas
 * pouvoir crediter plus que le montant facture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_invoices', function (Blueprint $table) {
            $table->timestamp('issued_at')->nullable()->after('status');
            $table->decimal('credited_amount', 15, 2)->default(0)->after('issued_at');
        });

        Schema::table('commercial_invoices', function (Blueprint $table) {
            $table->timestamp('issued_at')->nullable()->after('status');
            $table->decimal('credited_amount', 15, 2)->default(0)->after('issued_at');
        });

        // Une facture de vente nait de la validation d'une livraison : elle est
        // emise des sa creation, il n'existe pas d'etat brouillon.
        DB::statement('UPDATE commercial_invoices SET issued_at = created_at WHERE issued_at IS NULL');

        // Une facture personnalisee deja payee a forcement ete remise au client.
        DB::statement('UPDATE custom_invoices SET issued_at = COALESCE(paid_at, created_at) WHERE paid_amount > 0 AND issued_at IS NULL');
    }

    public function down(): void
    {
        foreach (['custom_invoices', 'commercial_invoices'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['issued_at', 'credited_amount']);
            });
        }
    }
};
