<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();

            $table->string('module_code', 50);
            $table->boolean('is_active')->default(true);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->uuid('activated_by')->nullable();
            $table->jsonb('settings')->nullable();
            $table->string('license_key')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // No FK to tenants - schema isolation handles tenant context
            $table->foreign('activated_by')->references('id')->on('users')->onDelete('set null');

            $table->unique(['tenant_id', 'module_code']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['module_code', 'is_active']);
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_modules');
    }
};
