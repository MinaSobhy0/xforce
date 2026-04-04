<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Central schema - feedback on help articles (helpful/not helpful).
     */
    public function up(): void
    {
        Schema::connection('pgsql')->create('help_article_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('help_articles')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->boolean('is_helpful');
            $table->text('comment')->nullable(); // Optional feedback comment
            $table->string('session_id')->nullable()->index(); // For anonymous feedback
            $table->ipAddress('ip_address')->nullable();
            $table->timestamps();

            $table->index('article_id');
            $table->index(['article_id', 'is_helpful']);
            // Prevent duplicate feedback from same user/session
            $table->unique(['article_id', 'tenant_id', 'user_id'], 'article_feedback_user_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('help_article_feedback');
    }
};
