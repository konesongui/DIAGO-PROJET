<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une sortie manuelle ne disait pas où partait la marchandise. Elle porte
 * désormais son motif (usage interne, casse, retour fournisseur…) et une note ;
 * une sortie liée à une livraison garde son lien vers la livraison.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_exits', function (Blueprint $table) {
            $table->string('reason', 30)->nullable()->after('delivery_id');
            $table->string('note')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('stock_exits', function (Blueprint $table) {
            $table->dropColumn(['reason', 'note']);
        });
    }
};
