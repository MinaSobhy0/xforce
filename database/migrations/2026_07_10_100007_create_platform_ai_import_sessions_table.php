<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-step AI import wizard session. When staff uploads a CSV/XLSX
 * to a list, we snapshot the sample rows here, send them to the LLM
 * for column classification + normalization proposals, and hold the
 * result for confirmation. Confirming materializes into
 * platform_email_list_members; cancelling drops it.
 *
 * Persisted across page loads so staff can walk away from the wizard
 * and come back to review the AI's proposals later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_ai_import_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->nullable()->constrained('platform_email_lists')->nullOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable();

            $table->string('source_filename');
            $table->integer('source_row_count')->default(0);
            $table->jsonb('sample_rows')->nullable();

            // What the LLM proposed. Overridable by the user in the preview step.
            $table->jsonb('ai_mapping')->nullable();          // column → target field
            $table->jsonb('ai_normalizations')->nullable();   // per-column rules
            $table->jsonb('ai_flagged_rows')->nullable();     // suspicious / duplicate rows

            $table->string('ai_model', 100)->nullable();
            $table->integer('ai_tokens_input')->nullable();
            $table->integer('ai_tokens_output')->nullable();
            $table->integer('ai_cost_usd_cents')->nullable();

            $table->string('status', 20)->default('preview');
            $table->integer('materialized_count')->default(0);

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_ai_import_sessions');
    }
};
