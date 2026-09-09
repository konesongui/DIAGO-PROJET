<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('gender')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('qualification')->nullable();
            $table->text('experience')->nullable();
            $table->text('remark')->nullable();
            $table->string('permanent_address')->nullable();
            $table->string('account_title')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('matricule')->nullable();
            $table->string('job_title')->nullable();
            $table->string('services')->nullable();
            $table->string('manager')->nullable();
            $table->string('cnps')->nullable();
            $table->decimal('base_salary', 15, 2)->nullable();
            $table->string('contract_type')->nullable();
            $table->string('workstation')->nullable();
            $table->string('location')->nullable();
            $table->date('registered_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'emergency_contact', 'gender', 'birth_date', 'marital_status',
                'father_name', 'mother_name', 'qualification', 'experience', 'remark',
                'permanent_address', 'account_title', 'bank_name', 'bank_branch',
                'bank_account_number', 'ifsc_code', 'facebook_url', 'twitter_url',
                'linkedin_url', 'instagram_url', 'matricule', 'job_title', 'services',
                'manager', 'cnps', 'base_salary', 'contract_type', 'workstation',
                'location', 'registered_at',
            ]);
        });
    }
};
