<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_task_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('project_tags')->cascadeOnDelete();
            $table->timestamps();

            // Unique constraint to prevent duplicates
            $table->unique(['task_id', 'tag_id']);

            // Indexes
            $table->index('task_id');
            $table->index('tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_task_tag');
    }
};
