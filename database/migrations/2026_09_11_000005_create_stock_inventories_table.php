<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un inventaire enregistrait des comptages sans jamais corriger le stock.
 * Il devient un document daté qui regroupe ses comptages ; ses écarts sont
 * passés au stock : un manquant sort (« écart d'inventaire »), un surplus entre
 * (« régularisation d'inventaire »), chaque mouvement restant relié à l'inventaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->date('inventoried_at');
            $table->string('note')->nullable();
            $table->unsignedInteger('counted_items')->default(0);
            $table->unsignedInteger('variance_items')->default(0);
            $table->decimal('shortage_value', 15, 2)->default(0);
            $table->decimal('surplus_value', 15, 2)->default(0);
            $table->foreignId('counted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['entreprise_id', 'inventoried_at']);
        });

        foreach (['inventory_audits', 'stock_entries', 'stock_exits'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('stock_inventory_id')->nullable()->after('entreprise_id')->constrained('stock_inventories')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['inventory_audits', 'stock_entries', 'stock_exits'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('stock_inventory_id');
            });
        }
        Schema::dropIfExists('stock_inventories');
    }
};
