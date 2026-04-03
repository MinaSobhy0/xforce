<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->foreignId('depends_on_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->string('dependency_type')->default('finish_to_start'); // finish_to_start, start_to_start, finish_to_finish, start_to_finish
            $table->integer('lag_days')->default(0);
            $table->timestamps();

            // Unique constraint to prevent duplicate dependencies
            $table->unique(['task_id', 'depends_on_task_id']);

            // Indexes
            $table->index('task_id');
            $table->index('depends_on_task_id');
            $table->index('dependency_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_task_dependencies');
    }
};
