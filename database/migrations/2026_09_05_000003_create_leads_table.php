<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('contact_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('new');
            $table->decimal('value', 12, 2)->default(0);
            $table->string('source')->default('site');
            $table->timestamp('next_step_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
