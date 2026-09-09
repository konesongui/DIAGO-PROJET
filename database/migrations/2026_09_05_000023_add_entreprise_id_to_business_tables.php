<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'account_entries',
        'commercial_objective_assignments',
        'commercial_proforma_lines',
        'stock_entry_lines',
        'stock_exit_lines',
        'fne_certification_logs',
    ];

    public function up()
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'entreprise_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('entreprise_id')->nullable()->after('id')->constrained('entreprises')->nullOnDelete();
                $table->index('entreprise_id');
            });
        }
    }

    public function down()
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'entreprise_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['entreprise_id']);
                $table->dropIndex([$table->getTable() . '_entreprise_id_index']);
                $table->dropColumn('entreprise_id');
            });
        }
    }
};
