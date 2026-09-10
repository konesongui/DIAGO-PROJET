<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des reglements clients.
 *
 * Les factures ne conservaient qu'un montant paye cumule et, au mieux, la
 * date du premier reglement. Impossible dans ces conditions de calculer la
 * TVA sur les encaissements, qui exige de savoir QUAND chaque fraction a ete
 * encaissee. Chaque reglement est desormais une ligne datee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();

            // Facture reglee, quel que soit son type.
            $table->string('payable_type');
            $table->unsignedBigInteger('payable_id');

            $table->decimal('amount', 15, 2);
            $table->string('method', 20)->default('cash');
            $table->date('paid_on');
            $table->string('currency', 10)->default('XOF');
            $table->string('reference')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id']);
            $table->index(['entreprise_id', 'paid_on']);
        });

        $this->backfill();
    }

    /**
     * Reprise de l'existant : on cree une ligne par facture deja reglee, a la
     * date connue. C'est une approximation assumee pour le passe, les
     * reglements futurs seront dates precisement.
     */
    private function backfill(): void
    {
        DB::statement("
            INSERT INTO invoice_payments
                (entreprise_id, payable_type, payable_id, amount, method, paid_on, currency, reference, created_at, updated_at)
            SELECT entreprise_id, 'App\\\\Models\\\\CustomInvoice', id, paid_amount, 'cash',
                   COALESCE(paid_at::date, created_at::date), 'XOF', 'Reprise', NOW(), NOW()
            FROM custom_invoices
            WHERE paid_amount > 0
        ");

        DB::statement("
            INSERT INTO invoice_payments
                (entreprise_id, payable_type, payable_id, amount, method, paid_on, currency, reference, created_at, updated_at)
            SELECT entreprise_id, 'App\\\\Models\\\\CommercialInvoice', id, paid_amount, 'cash',
                   created_at::date, currency, 'Reprise', NOW(), NOW()
            FROM commercial_invoices
            WHERE paid_amount > 0
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
