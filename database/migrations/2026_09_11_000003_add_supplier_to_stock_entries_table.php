<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chaque produit entré en stock doit pouvoir être suivi jusqu'au fournisseur
 * qui l'a livré. Une réception porte son fournisseur, son nom au moment de la
 * livraison (il reste lisible si le fournisseur quitte le carnet) et le numéro
 * du bon de livraison.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_entries', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('entreprise_id')->constrained('commercial_suppliers')->nullOnDelete();
            $table->string('supplier_name')->nullable()->after('supplier_id');
            $table->string('supplier_reference', 100)->nullable()->after('supplier_name');
        });
    }

    public function down(): void
    {
        Schema::table('stock_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn(['supplier_name', 'supplier_reference']);
        });
    }
};
