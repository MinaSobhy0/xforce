<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('username')->unique();
            $table->string('phone')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password');
            $table->string('avatar_url')->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended', 'pending'])->default('active');
            $table->string('language', 5)->default('en');
            $table->string('timezone')->default('UTC');

            // Two-factor authentication
            $table->boolean('two_factor_enabled')->default(false);
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            // Security fields
            $table->timestamp('last_login_at')->nullable();
            $table->ipAddress('last_login_ip')->nullable();
            $table->timestamp('password_expires_at')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();

            // Employee information
            $table->string('employee_id')->nullable();
            $table->string('department')->nullable();
            $table->string('job_title')->nullable();
            $table->date('hire_date')->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->text('address')->nullable();
            $table->json('emergency_contact')->nullable();
            $table->decimal('salary', 10, 2)->nullable();
            $table->decimal('commission_rate', 5, 4)->nullable();
            $table->json('work_schedule')->nullable();

            // Additional fields
            $table->json('permissions_override')->nullable();
            $table->json('settings')->nullable();
            $table->json('preferences')->nullable();
            $table->json('meta')->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys and indexes
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'email']);
            $table->unique(['tenant_id', 'employee_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['email_verified_at']);
            $table->index(['last_login_at']);
            $table->index(['password_expires_at']);
            $table->index(['locked_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};