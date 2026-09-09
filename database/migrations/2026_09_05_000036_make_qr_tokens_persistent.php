<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->setExpiresAtNullable(true);
    }

    public function down(): void
    {
        $this->setExpiresAtNullable(false);
    }

    /**
     * Rend la colonne expires_at nullable ou non, sans dépendre de doctrine/dbal
     * ni de la syntaxe MODIFY propre à MySQL.
     */
    private function setExpiresAtNullable(bool $nullable): void
    {
        $driver = Schema::getConnection()->getDriverName();

        match ($driver) {
            'pgsql' => DB::statement(sprintf(
                'ALTER TABLE qr_tokens ALTER COLUMN expires_at %s NOT NULL',
                $nullable ? 'DROP' : 'SET'
            )),
            'mysql', 'mariadb' => DB::statement(sprintf(
                'ALTER TABLE qr_tokens MODIFY expires_at DATETIME %s',
                $nullable ? 'NULL' : 'NOT NULL'
            )),
            // SQLite ne sait pas modifier une contrainte de colonne en place.
            // La table est recréée par les migrations de test, on ne fait rien.
            default => null,
        };
    }
};
