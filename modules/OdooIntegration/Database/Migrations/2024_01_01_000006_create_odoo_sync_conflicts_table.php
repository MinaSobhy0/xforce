<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odoo_sync_conflicts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('sync_record_id')
                ->constrained('odoo_sync_records')
                ->cascadeOnDelete();
            $table->foreignId('entity_mapping_id')
                ->constrained('odoo_entity_mappings')
                ->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending, resolved, dismissed
            $table->string('conflict_type'); // both_modified, deleted_locally, deleted_remotely
            $table->jsonb('local_data'); // Local record snapshot
            $table->jsonb('odoo_data'); // Odoo record snapshot
            $table->jsonb('diff')->nullable(); // Field differences
            $table->string('resolution')->nullable(); // keep_local, keep_odoo, merge, skip
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('sync_record_id');
            $table->index('entity_mapping_id');
            $table->index('status');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_sync_conflicts');
    }
};
