<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add kanban_state and remaining_hours to project_tasks
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->string('kanban_state')->default('normal')->after('sort_order');
            $table->decimal('remaining_hours', 8, 2)->nullable()->after('actual_hours');

            $table->index('kanban_state');
        });

        // Add timer_accumulated_seconds and submission_id to project_time_entries
        Schema::table('project_time_entries', function (Blueprint $table) {
            $table->integer('timer_accumulated_seconds')->default(0)->after('timer_started_at');
            $table->boolean('timer_running')->default(false)->after('timer_accumulated_seconds');
            $table->foreignId('submission_id')->nullable()->after('timer_running');

            $table->index('submission_id');
            $table->index('timer_running');
        });

        // Add privacy to projects
        Schema::table('projects', function (Blueprint $table) {
            $table->string('privacy')->default('employees')->after('color');

            $table->index('privacy');
        });

        // Add hourly_rate_minor to project_members
        Schema::table('project_members', function (Blueprint $table) {
            $table->integer('hourly_rate_minor')->default(0)->after('role');
        });

        // Create timesheet_submissions table for weekly approval workflow
        Schema::create('timesheet_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('week_start');
            $table->date('week_end');
            $table->decimal('total_hours', 8, 2)->default(0);
            $table->string('status')->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Unique constraint - one submission per user per week
            $table->unique(['user_id', 'week_start']);

            // Indexes
            $table->index('user_id');
            $table->index('status');
            $table->index('approved_by');
            $table->index('week_start');
            $table->index(['user_id', 'status']);
            $table->index(['status', 'submitted_at']);
        });

        // Add foreign key for submission_id after timesheet_submissions table exists
        Schema::table('project_time_entries', function (Blueprint $table) {
            $table->foreign('submission_id')
                ->references('id')
                ->on('timesheet_submissions')
                ->nullOnDelete();
        });

        // Add Odoo sync fields to timesheet_submissions
        Schema::table('timesheet_submissions', function (Blueprint $table) {
            $table->unsignedBigInteger('odoo_id')->nullable();
            $table->timestamp('odoo_synced_at')->nullable();

            $table->index('odoo_id');
        });
    }

    public function down(): void
    {
        // Drop foreign key first
        Schema::table('project_time_entries', function (Blueprint $table) {
            $table->dropForeign(['submission_id']);
        });

        // Drop timesheet_submissions table
        Schema::dropIfExists('timesheet_submissions');

        // Remove added columns from project_members
        Schema::table('project_members', function (Blueprint $table) {
            $table->dropColumn('hourly_rate_minor');
        });

        // Remove added columns from projects
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['privacy']);
            $table->dropColumn('privacy');
        });

        // Remove added columns from project_time_entries
        Schema::table('project_time_entries', function (Blueprint $table) {
            $table->dropIndex(['submission_id']);
            $table->dropIndex(['timer_running']);
            $table->dropColumn(['timer_accumulated_seconds', 'timer_running', 'submission_id']);
        });

        // Remove added columns from project_tasks
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropIndex(['kanban_state']);
            $table->dropColumn(['kanban_state', 'remaining_hours']);
        });
    }
};
