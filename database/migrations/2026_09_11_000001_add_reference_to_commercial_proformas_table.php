<?php

use App\Services\DocumentNumberService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un proforma remis au client n'avait aucun numéro : le compteur prévoyait un
 * format « PRO », mais rien ne le conservait. Les proformas existants sont
 * numérotés dans l'ordre de leur création.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_proformas', function (Blueprint $table) {
            $table->string('reference')->nullable()->after('entreprise_id');
            $table->unique(['entreprise_id', 'reference']);
        });

        $numbers = app(DocumentNumberService::class);
        DB::table('commercial_proformas')->whereNull('reference')->whereNotNull('entreprise_id')
            ->orderBy('creation_date')->orderBy('id')
            ->get(['id', 'entreprise_id', 'creation_date'])
            ->each(fn ($proforma) => DB::table('commercial_proformas')->where('id', $proforma->id)->update([
                'reference' => $numbers->next((int) $proforma->entreprise_id, 'proforma', new \DateTimeImmutable($proforma->creation_date)),
            ]));
    }

    public function down(): void
    {
        Schema::table('commercial_proformas', function (Blueprint $table) {
            $table->dropUnique(['entreprise_id', 'reference']);
            $table->dropColumn('reference');
        });
    }
};
