<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('event_type', 50);
            $table->text('description');
            $table->jsonb('metadata')->nullable();
            $table->string('causer_type', 255)->nullable();
            $table->uuid('causer_id')->nullable();
            $table->timestamp('created_at');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->index(['tenant_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_activity_logs');
    }
};
