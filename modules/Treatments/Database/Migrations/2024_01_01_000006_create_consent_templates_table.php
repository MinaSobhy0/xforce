<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();

            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->jsonb('content');
            $table->string('version', 10)->default('1.0');
            $table->integer('valid_days')->nullable()->comment('Null means no expiration');
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_witness')->default(false);
            $table->boolean('requires_patient_signature')->default(true);
            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_templates');
    }
};
