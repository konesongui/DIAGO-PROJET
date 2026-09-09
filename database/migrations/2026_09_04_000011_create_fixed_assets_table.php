<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id');
            $table->string('name');
            $table->string('asset_category')->default('Autre');
            $table->string('reference')->nullable();
            $table->string('supplier')->nullable();
            $table->string('location')->nullable();
            $table->date('acquisition_date');
            $table->decimal('acquisition_value', 15, 2);
            $table->decimal('residual_value', 15, 2)->default(0);
            $table->unsignedSmallInteger('useful_life_years')->default(5);
            $table->string('status')->default('active');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('entreprise_id')->references('id')->on('entreprises')->cascadeOnDelete();
            $table->index(['entreprise_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
