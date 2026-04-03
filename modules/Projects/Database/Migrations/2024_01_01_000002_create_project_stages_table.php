<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->jsonb('name');
            $table->string('status_type')->default('todo'); // todo, in_progress, review, done
            $table->integer('sort_order')->default(0);
            $table->string('color', 7)->default('#6B7280');
            $table->boolean('fold_by_default')->default(false);
            $table->boolean('is_final')->default(false);
            $table->timestamps();

            // Indexes
            $table->index('project_id');
            $table->index('status_type');
            $table->index(['project_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_stages');
    }
};
