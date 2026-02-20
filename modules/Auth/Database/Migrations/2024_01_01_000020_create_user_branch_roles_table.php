<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create user_branch_roles table for branch-scoped role assignments.
     *
     * This allows users to have different roles in different branches.
     * For example, a user might be a "manager" in Branch A but only
     * a "practitioner" in Branch B.
     */
    public function up(): void
    {
        Schema::create('user_branch_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('user_id');
            $table->uuid('branch_id');
            $table->unsignedBigInteger('role_id'); // bigint to match Spatie roles

            $table->boolean('is_primary')->default(false); // Primary branch for this user
            $table->boolean('is_active')->default(true);
            $table->timestamp('assigned_at')->nullable();
            $table->uuid('assigned_by')->nullable();
            $table->timestamp('expires_at')->nullable(); // For temporary assignments

            $table->timestamps();
            $table->softDeletes();

            // No FK to tenants - schema isolation handles tenant context
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('assigned_by')->references('id')->on('users')->onDelete('set null');

            // Each user can have only one role per branch
            $table->unique(['user_id', 'branch_id', 'role_id'], 'user_branch_role_unique');

            // Indexes for common queries
            $table->index(['user_id', 'is_active']);
            $table->index(['branch_id', 'role_id', 'is_active']);
            $table->index(['tenant_id', 'user_id', 'is_primary']);
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_branch_roles');
    }
};
