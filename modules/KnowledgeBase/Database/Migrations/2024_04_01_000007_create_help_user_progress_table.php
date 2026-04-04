<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tenant schema - track guide completion per user within each tenant.
     */
    public function up(): void
    {
        Schema::create('help_user_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('guide_id')->index(); // References central help_screen_guides
            $table->string('screen_key')->index();
            $table->boolean('is_completed')->default(false);
            $table->boolean('is_skipped')->default(false);
            $table->integer('current_step')->default(0);
            $table->integer('total_steps')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Foreign key to users table in tenant schema
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            // Unique constraint per user per guide
            $table->unique(['user_id', 'guide_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('help_user_progress');
    }
};
