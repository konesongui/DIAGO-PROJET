<?php

use App\Services\DocumentNumberService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une vente au comptoir n'avait aucun numéro de ticket : le compteur prévoyait
 * un format « POS », mais rien ne le conservait. Les ventes existantes sont
 * numérotées dans l'ordre où elles ont été faites.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->string('reference')->nullable()->after('entreprise_id');
            $table->unique(['entreprise_id', 'reference']);
        });

        $numbers = app(DocumentNumberService::class);
        DB::table('pos_sales')->whereNull('reference')->orderBy('created_at')->orderBy('id')
            ->get(['id', 'entreprise_id', 'created_at'])
            ->each(fn ($sale) => DB::table('pos_sales')->where('id', $sale->id)->update([
                'reference' => $numbers->next((int) $sale->entreprise_id, 'pos_sale', new \DateTimeImmutable($sale->created_at)),
            ]));
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropUnique(['entreprise_id', 'reference']);
            $table->dropColumn('reference');
        });
    }
};
