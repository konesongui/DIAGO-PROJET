<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le montant de taxe s'appelait vat_amount sur les factures personnalisees et
 * tax_amount partout ailleurs. Cette divergence oblige a se souvenir du nom
 * selon la table et complique toute reprise. On aligne sur tax_amount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_invoices', function (Blueprint $table) {
            $table->renameColumn('vat_amount', 'tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('custom_invoices', function (Blueprint $table) {
            $table->renameColumn('tax_amount', 'vat_amount');
        });
    }
};
