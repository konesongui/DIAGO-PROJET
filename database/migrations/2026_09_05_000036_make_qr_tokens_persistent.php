<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE qr_tokens MODIFY expires_at DATETIME NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE qr_tokens MODIFY expires_at DATETIME NOT NULL');
    }
};
