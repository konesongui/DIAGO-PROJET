<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_invoices', function (Blueprint $table) {
            // Le taux retenu, pour pouvoir retrouver le parametrage d'origine.
            $table->foreignId('tax_rate_id')->nullable()->after('vat_amount')
                ->constrained('tax_rates')->nullOnDelete();

            // Copie figee du taux et du regime au moment de l'emission : une
            // facture reeditee doit afficher le taux applique ce jour-la, meme
            // si le parametrage a change depuis.
            $table->decimal('tax_rate', 6, 3)->default(0)->after('tax_rate_id');
            $table->string('tax_regime', 30)->nullable()->after('tax_rate');
        });

        // Les factures existantes ont ete calculees a 18 %, on l'inscrit
        // explicitement plutot que de laisser un taux a zero qui serait faux.
        DB::table('custom_invoices')->where('vat_amount', '>', 0)->update([
            'tax_rate' => 18,
            'tax_regime' => 'standard',
        ]);
    }

    public function down(): void
    {
        Schema::table('custom_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_rate_id');
            $table->dropColumn(['tax_rate', 'tax_regime']);
        });
    }
};
