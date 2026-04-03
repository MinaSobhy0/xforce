<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odoo_sync_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('entity_mapping_id')
                ->constrained('odoo_entity_mappings')
                ->cascadeOnDelete();
            $table->string('local_model'); // XForce model class
            $table->unsignedBigInteger('local_id'); // XForce record ID
            $table->unsignedBigInteger('odoo_id'); // Odoo record ID
            $table->string('sync_status')->default('pending'); // pending, synced, error, conflict
            $table->string('last_sync_direction')->nullable(); // Last sync direction
            $table->timestamp('last_synced_at')->nullable();
            $table->string('local_checksum')->nullable(); // MD5 of local data
            $table->string('odoo_checksum')->nullable(); // MD5 of Odoo data
            $table->boolean('is_archived')->default(false); // Odoo archived status
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('entity_mapping_id');
            $table->index('local_model');
            $table->index('local_id');
            $table->index('odoo_id');
            $table->index('sync_status');
            $table->unique(['entity_mapping_id', 'local_id'], 'unique_local_sync_record');
            $table->unique(['entity_mapping_id', 'odoo_id'], 'unique_odoo_sync_record');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_sync_records');
    }
};
