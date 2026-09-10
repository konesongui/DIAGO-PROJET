<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Les factures fournisseurs sont lues depuis un PDF. Un montant de taxe a
 * zero peut vouloir dire "exonere" ou "extraction ratee" : sans regime
 * explicite, impossible de trancher, et le document part au fisc sur une
 * supposition. On rend donc le regime explicite et modifiable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->string('tax_regime', 30)->nullable()->after('tax_amount');
        });

        // Une taxe positive sur le document du fournisseur est un fait lisible,
        // on peut la qualifier. Un zero reste a confirmer par un humain.
        DB::statement("
            UPDATE supplier_invoices
            SET tax_regime = CASE WHEN tax_amount > 0 THEN 'standard' ELSE 'unknown' END
        ");
    }

    public function down(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->dropColumn('tax_regime');
        });
    }
};
