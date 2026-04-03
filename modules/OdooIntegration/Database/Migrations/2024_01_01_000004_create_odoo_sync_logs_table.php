<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odoo_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('connection_id')
                ->constrained('odoo_connections')
                ->cascadeOnDelete();
            $table->foreignId('entity_mapping_id')
                ->nullable()
                ->constrained('odoo_entity_mappings')
                ->nullOnDelete();
            $table->string('sync_type')->default('full'); // full, delta, single
            $table->string('direction'); // import, export
            $table->string('status')->default('pending'); // pending, running, completed, failed, cancelled
            $table->integer('records_processed')->default(0);
            $table->integer('records_created')->default(0);
            $table->integer('records_updated')->default(0);
            $table->integer('records_failed')->default(0);
            $table->integer('conflicts_detected')->default(0);
            $table->jsonb('errors')->nullable();
            $table->string('watermark')->nullable(); // Resume position
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('triggered_by')->nullable(); // User who triggered

            $table->timestamps();

            $table->index('tenant_id');
            $table->index('connection_id');
            $table->index('entity_mapping_id');
            $table->index('status');
            $table->index('started_at');
            $table->index(['tenant_id', 'status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_sync_logs');
    }
};
