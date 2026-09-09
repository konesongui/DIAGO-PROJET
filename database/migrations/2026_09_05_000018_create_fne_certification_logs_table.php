<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fne_certification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->string('certifiable_type');
            $table->unsignedBigInteger('certifiable_id');
            $table->string('document_type');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedSmallInteger('http_code')->nullable();
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['certifiable_type', 'certifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fne_certification_logs');
    }
};
