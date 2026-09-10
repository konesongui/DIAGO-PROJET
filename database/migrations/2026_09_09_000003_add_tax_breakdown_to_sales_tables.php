<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute la ventilation de taxe aux documents qui n'en portaient aucune.
 *
 * Convention retenue : la colonne de montant deja presente reste le TTC,
 * c'est-a-dire ce que le client doit ou a paye. On lui adjoint le detail
 * fiscal, sans jamais modifier le montant existant.
 *   commercial_orders   -> total_ttc reste le TTC
 *   commercial_invoices -> amount    reste le TTC
 *   pos_sales           -> total     reste le TTC
 */
return new class extends Migration
{
    private array $tables = [
        'commercial_orders' => 'total_ttc',
        'commercial_invoices' => 'amount',
        'pos_sales' => 'total',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $ttcColumn) {
            Schema::table($table, function (Blueprint $blueprint) use ($ttcColumn) {
                $blueprint->decimal('total_ht', 15, 2)->default(0)->after($ttcColumn);
                $blueprint->decimal('tax_amount', 15, 2)->default(0)->after('total_ht');

                // Copie figee du taux et du regime a l'emission : un document
                // reedite doit montrer ce qui s'appliquait ce jour-la.
                $blueprint->decimal('tax_rate', 6, 3)->default(0)->after('tax_amount');
                $blueprint->string('tax_regime', 30)->nullable()->after('tax_rate');
                $blueprint->foreignId('tax_rate_id')->nullable()->after('tax_regime')
                    ->constrained('tax_rates')->nullOnDelete();

                $blueprint->string('currency', 10)->default('XOF')->after('tax_rate_id');
            });
        }

        $this->backfill();
    }

    /**
     * Reprise des documents existants.
     *
     * Les commandes et factures sont rattachees a un devis qui porte deja le
     * detail : on le recopie. Les ventes au point de vente n'ont jamais eu de
     * taxe calculee, on inscrit un regime "unknown" plutot que d'inventer un
     * montant qui n'a jamais ete facture.
     */
    private function backfill(): void
    {
        DB::statement("
            UPDATE commercial_orders o
            SET total_ht = q.net_ht, tax_amount = q.tax_amount,
                tax_rate = q.tax_rate, tax_regime = CASE WHEN q.tax_rate > 0 THEN 'standard' ELSE 'exempt' END
            FROM commercial_quotes q
            WHERE o.quote_id = q.id
        ");

        DB::statement("
            UPDATE commercial_invoices i
            SET total_ht = o.total_ht, tax_amount = o.tax_amount,
                tax_rate = o.tax_rate, tax_regime = o.tax_regime
            FROM commercial_deliveries d
            JOIN commercial_orders o ON o.id = d.order_id
            WHERE i.delivery_id = d.id
        ");

        // Documents sans devis rattache : le montant est connu, sa ventilation non.
        DB::statement("UPDATE commercial_orders   SET total_ht = total_ttc, tax_regime = 'unknown' WHERE tax_regime IS NULL");
        DB::statement("UPDATE commercial_invoices SET total_ht = amount,    tax_regime = 'unknown' WHERE tax_regime IS NULL");
        DB::statement("UPDATE pos_sales           SET total_ht = total,     tax_regime = 'unknown' WHERE tax_regime IS NULL");
    }

    public function down(): void
    {
        foreach (array_keys($this->tables) as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('tax_rate_id');
                $blueprint->dropColumn(['total_ht', 'tax_amount', 'tax_rate', 'tax_regime', 'currency']);
            });
        }
    }
};
