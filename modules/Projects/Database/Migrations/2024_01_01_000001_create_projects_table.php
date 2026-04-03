<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->string('code')->unique();
            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable(); // Optional patient/customer reference
            $table->string('status')->default('planning');
            $table->string('priority')->default('medium');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('deadline')->nullable();
            $table->integer('budget_minor')->default(0);
            $table->integer('actual_cost_minor')->default(0);
            $table->string('color', 7)->default('#3B82F6');
            $table->jsonb('settings')->nullable();
            $table->boolean('allow_timesheets')->default(true);
            $table->boolean('is_template')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('branch_id');
            $table->index('manager_id');
            $table->index('status');
            $table->index('priority');
            $table->index('is_active');
            $table->index('is_template');
            $table->index(['status', 'is_active']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
