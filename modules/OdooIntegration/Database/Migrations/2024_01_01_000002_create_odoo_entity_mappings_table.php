<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odoo_entity_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('odoo_connection_id')
                ->constrained('odoo_connections')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('local_model'); // XForce model class
            $table->string('local_table'); // XForce table name
            $table->string('odoo_model'); // Odoo model name (e.g., 'res.users')
            $table->string('sync_direction')->default('import'); // import, export, bidirectional
            $table->string('sync_frequency')->default('manual'); // manual, hourly, daily, realtime
            $table->string('conflict_resolution')->default('manual'); // newest_wins, local_wins, odoo_wins, manual
            $table->integer('batch_size')->default(100);
            $table->integer('priority')->default(50); // Sync order (lower = first)
            $table->boolean('is_active')->default(true);
            $table->jsonb('filter_conditions')->nullable(); // Odoo domain filters
            $table->jsonb('settings')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('odoo_connection_id');
            $table->index('odoo_model');
            $table->index('local_model');
            $table->index('is_active');
            $table->index('priority');
            $table->unique(['odoo_connection_id', 'local_model', 'odoo_model'], 'unique_entity_mapping');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_entity_mappings');
    }
};
