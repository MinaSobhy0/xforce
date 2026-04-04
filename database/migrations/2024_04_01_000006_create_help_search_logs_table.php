<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Central schema - search analytics for knowledge base.
     */
    public function up(): void
    {
        Schema::connection('pgsql')->create('help_search_logs', function (Blueprint $table) {
            $table->id();
            $table->string('query'); // Search query text
            $table->string('locale', 10)->default('en');
            $table->integer('results_count')->default(0);
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('session_id')->nullable();
            $table->string('panel')->nullable(); // 'tenant', 'super-admin', 'admin'
            $table->string('screen_key')->nullable(); // Where the search was initiated
            $table->unsignedBigInteger('clicked_article_id')->nullable()->index();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamps();

            $table->index('query');
            $table->index('results_count');
            $table->index('created_at');
        });

        // Create index for search query analytics (requires pg_trgm extension)
        try {
            DB::connection('pgsql')->statement("CREATE EXTENSION IF NOT EXISTS pg_trgm");
            DB::connection('pgsql')->statement("
                CREATE INDEX help_search_logs_query_trgm ON help_search_logs USING GIN (query gin_trgm_ops)
            ");
        } catch (\Exception $e) {
            // pg_trgm extension not available - skip this index
            \Illuminate\Support\Facades\Log::warning('Could not create pg_trgm index for help_search_logs: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('help_search_logs');
    }
};
