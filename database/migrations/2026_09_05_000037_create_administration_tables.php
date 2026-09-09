<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tenant = function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entreprise_id')->constrained()->cascadeOnDelete();
        };

        Schema::create('admin_visitors', function (Blueprint $table) use ($tenant): void {
            $tenant($table);
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('purpose')->nullable();
            $table->string('host')->nullable();
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            $table->string('status')->default('expected');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('admin_calls', function (Blueprint $table) use ($tenant): void {
            $tenant($table);
            $table->string('contact_name');
            $table->string('phone')->nullable();
            $table->string('subject')->nullable();
            $table->string('direction')->default('incoming');
            $table->dateTime('call_at')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->string('status')->default('planned');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('admin_correspondences', function (Blueprint $table) use ($tenant): void {
            $tenant($table);
            $table->string('reference');
            $table->string('type')->default('incoming');
            $table->string('subject');
            $table->string('sender')->nullable();
            $table->string('recipient')->nullable();
            $table->date('received_at')->nullable();
            $table->string('status')->default('received');
            $table->string('file_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('admin_meetings', function (Blueprint $table) use ($tenant): void {
            $tenant($table);
            $table->string('title');
            $table->string('location')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->string('organizer')->nullable();
            $table->text('attendees')->nullable();
            $table->string('status')->default('planned');
            $table->longText('minutes')->nullable();
            $table->timestamps();
        });
        Schema::create('admin_documents', function (Blueprint $table) use ($tenant): void {
            $tenant($table);
            $table->string('title');
            $table->string('category')->nullable();
            $table->date('document_date')->nullable();
            $table->string('status')->default('active');
            $table->string('file_path')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_documents');
        Schema::dropIfExists('admin_meetings');
        Schema::dropIfExists('admin_correspondences');
        Schema::dropIfExists('admin_calls');
        Schema::dropIfExists('admin_visitors');
    }
};
