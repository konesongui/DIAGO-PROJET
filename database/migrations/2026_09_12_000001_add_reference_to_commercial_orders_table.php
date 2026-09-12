<?php

use App\Services\DocumentNumberService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un bon de commande n'avait aucun numéro propre : il ne portait que la
 * référence de commande du client. Les commandes existantes sont numérotées
 * « BC » dans l'ordre de leur création.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_orders', function (Blueprint $table) {
            $table->string('reference')->nullable()->after('entreprise_id');
            $table->unique(['entreprise_id', 'reference']);
        });

        $numbers = app(DocumentNumberService::class);
        DB::table('commercial_orders')->whereNull('reference')->whereNotNull('entreprise_id')
            ->orderBy('created_at')->orderBy('id')
            ->get(['id', 'entreprise_id', 'created_at'])
            ->each(fn ($order) => DB::table('commercial_orders')->where('id', $order->id)->update([
                'reference' => $numbers->next((int) $order->entreprise_id, 'order', new \DateTimeImmutable($order->created_at)),
            ]));
    }

    public function down(): void
    {
        Schema::table('commercial_orders', function (Blueprint $table) {
            $table->dropUnique(['entreprise_id', 'reference']);
            $table->dropColumn('reference');
        });
    }
};
