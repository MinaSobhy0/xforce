<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Central schema - individual steps for screen guides.
     */
    public function up(): void
    {
        Schema::connection('pgsql')->create('help_guide_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guide_id')->constrained('help_screen_guides')->cascadeOnDelete();
            $table->jsonb('title'); // {'en': '...', 'ar': '...'}
            $table->jsonb('content'); // {'en': '...', 'ar': '...'}
            $table->string('target_selector'); // CSS selector for element to highlight
            $table->string('placement')->default('bottom'); // top, bottom, left, right, auto
            $table->integer('sort_order')->default(0);
            $table->string('action_type')->nullable(); // 'click', 'input', 'hover', null for info only
            $table->string('action_selector')->nullable(); // Selector for action element if different
            $table->jsonb('options')->nullable(); // Additional options like highlight style, padding, etc.
            $table->boolean('is_required')->default(false); // Must complete this step
            $table->timestamps();

            $table->index('guide_id');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('help_guide_steps');
    }
};
