<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->string('code')->unique();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained('project_stages')->restrictOnDelete();
            $table->foreignId('parent_task_id')->nullable()->constrained('project_tasks')->cascadeOnDelete();
            $table->foreignId('milestone_id')->nullable()->constrained('project_milestones')->nullOnDelete();
            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority')->default('medium');
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('deadline')->nullable();
            $table->date('completed_date')->nullable();
            $table->integer('estimated_hours')->nullable();
            $table->integer('actual_hours')->default(0);
            $table->integer('progress_percent')->default(0);
            $table->integer('sort_order')->default(0);
            $table->jsonb('custom_fields')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('project_id');
            $table->index('stage_id');
            $table->index('parent_task_id');
            $table->index('milestone_id');
            $table->index('assigned_to_id');
            $table->index('created_by_id');
            $table->index('priority');
            $table->index('deadline');
            $table->index('completed_date');
            $table->index(['project_id', 'stage_id']);
            $table->index(['project_id', 'assigned_to_id']);
            $table->index(['stage_id', 'sort_order']);
            $table->index(['assigned_to_id', 'completed_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_tasks');
    }
};
