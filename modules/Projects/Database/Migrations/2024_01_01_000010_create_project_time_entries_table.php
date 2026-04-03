<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('project_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('hours', 5, 2);
            $table->jsonb('description')->nullable();
            $table->boolean('is_billable')->default(false);
            $table->integer('hourly_rate_minor')->default(0);
            $table->timestamp('timer_started_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('project_id');
            $table->index('task_id');
            $table->index('user_id');
            $table->index('date');
            $table->index('is_billable');
            $table->index('timer_started_at');
            $table->index(['project_id', 'user_id']);
            $table->index(['project_id', 'date']);
            $table->index(['user_id', 'date']);
            $table->index(['task_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_time_entries');
    }
};
