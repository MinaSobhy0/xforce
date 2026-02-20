<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();

            $table->string('name', 100);
            $table->string('model_type'); // Full model class name
            $table->jsonb('domain_filter')->nullable(); // JSON array of conditions
            $table->unsignedBigInteger('role_id')->nullable(); // Null if apply_to_all_roles is true (bigint to match Spatie roles)
            $table->boolean('apply_to_all_roles')->default(false);

            // Permissions this policy grants
            $table->boolean('perm_read')->default(true);
            $table->boolean('perm_create')->default(false);
            $table->boolean('perm_update')->default(false);
            $table->boolean('perm_delete')->default(false);

            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(10); // Lower = higher priority
            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // No FK to tenants - schema isolation handles tenant context
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');

            $table->index(['tenant_id', 'model_type', 'is_active']);
            $table->index(['role_id', 'is_active']);
            $table->index(['model_type', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_policies');
    }
};
