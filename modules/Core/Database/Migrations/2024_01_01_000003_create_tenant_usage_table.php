<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_usage', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->integer('users')->default(0);
            $table->integer('patients')->default(0);
            $table->integer('appointments')->default(0);
            $table->integer('treatments')->default(0);
            $table->integer('storage_mb')->default(0);
            $table->integer('api_requests')->default(0);
            $table->integer('email_sent')->default(0);
            $table->integer('sms_sent')->default(0);
            $table->integer('reports_generated')->default(0);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->json('monthly_stats')->nullable();
            $table->json('yearly_stats')->nullable();
            $table->timestamps();

            // No FK to tenants - schema isolation handles tenant context
            $table->unique('tenant_id');
            $table->index(['last_activity_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_usage');
    }
};