<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Central schema - platform-wide help articles.
     */
    public function up(): void
    {
        Schema::connection('pgsql')->create('help_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('help_categories')->nullOnDelete();
            $table->jsonb('title'); // {'en': '...', 'ar': '...'}
            $table->jsonb('content'); // {'en': '...', 'ar': '...'} - Rich text content
            $table->jsonb('excerpt')->nullable(); // Short summary
            $table->string('slug')->unique();
            $table->string('screen_key')->nullable()->index(); // e.g., 'patients.index', 'appointments.create'
            $table->string('panel')->nullable()->index(); // 'tenant', 'super-admin', 'admin'
            $table->jsonb('tags')->nullable(); // ['tag1', 'tag2']
            $table->jsonb('related_screens')->nullable(); // Additional screen keys
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->integer('view_count')->default(0);
            $table->integer('helpful_count')->default(0);
            $table->integer('not_helpful_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('category_id');
            $table->index('is_featured');
            $table->index('is_active');
            $table->index('sort_order');
        });

        // Create GIN index for full-text search on JSONB columns
        DB::connection('pgsql')->statement("
            CREATE INDEX help_articles_title_gin ON help_articles USING GIN (title jsonb_path_ops)
        ");
        DB::connection('pgsql')->statement("
            CREATE INDEX help_articles_content_gin ON help_articles USING GIN (content jsonb_path_ops)
        ");
        DB::connection('pgsql')->statement("
            CREATE INDEX help_articles_tags_gin ON help_articles USING GIN (tags jsonb_path_ops)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('help_articles');
    }
};
