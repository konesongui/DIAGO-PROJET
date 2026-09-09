<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id');
            $table->string('source_type', 10);
            $table->unsignedBigInteger('source_id');
            $table->string('destination_type', 10);
            $table->unsignedBigInteger('destination_id');
            $table->decimal('amount', 15, 2);
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->date('transfer_date');
            $table->timestamps();

            $table->foreign('entreprise_id')->references('id')->on('entreprises')->cascadeOnDelete();
            $table->index(['entreprise_id', 'transfer_date']);
            $table->index(['source_type', 'source_id']);
            $table->index(['destination_type', 'destination_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_transfers');
    }
};
