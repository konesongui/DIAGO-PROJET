<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_entries', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('label');
            $table->string('account_code', 50);
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('XOF');
            $table->date('posted_at');
            $table->string('status')->default('validated');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_entries');
    }
};
