<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('entreprise_id')->constrained('users')->nullOnDelete();
            $table->string('first_name')->nullable()->after('full_name');
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('cnps_number')->nullable();
            $table->string('gender')->nullable();
            $table->string('contract_type')->nullable();
            $table->string('nationality')->nullable();
            $table->date('birth_date')->nullable();
            $table->date('registered_at')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->string('phone')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('salary_category')->nullable();
            $table->string('address')->nullable();
            $table->string('permanent_address')->nullable();
            $table->string('qualification')->nullable();
            $table->text('experience')->nullable();
            $table->text('remark')->nullable();
            $table->string('paternity_leave')->nullable();
            $table->string('maternity_leave')->nullable();
            $table->string('annual_leave')->nullable();
            $table->string('account_title')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('photo_path')->nullable();
            $table->json('documents')->nullable();
        });
    }

    public function down()
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'user_id', 'first_name', 'father_name', 'mother_name', 'cnps_number', 'gender',
                'contract_type', 'nationality', 'birth_date', 'registered_at', 'contract_end_date',
                'phone', 'emergency_contact', 'marital_status', 'salary_category', 'address',
                'permanent_address', 'qualification', 'experience', 'remark', 'paternity_leave',
                'maternity_leave', 'annual_leave', 'account_title', 'bank_account_number',
                'bank_name', 'ifsc_code', 'bank_branch', 'facebook_url', 'twitter_url',
                'linkedin_url', 'instagram_url', 'photo_path', 'documents',
            ]);
        });
    }
};
