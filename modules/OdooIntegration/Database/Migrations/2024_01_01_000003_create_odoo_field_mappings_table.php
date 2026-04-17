<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odoo_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_mapping_id')
                ->constrained('odoo_entity_mappings')
                ->cascadeOnDelete();
            $table->string('local_field'); // XForce field name
            $table->string('odoo_field')->nullable(); // Odoo field name (nullable for default-only fields)
            $table->string('direction')->default('bidirectional'); // import, export, bidirectional
            $table->string('transform_type')->default('direct'); // direct, date, datetime, money, relation, enum, boolean, json, etc.
            $table->jsonb('transform_config')->nullable(); // Transform settings (e.g., enum mappings)
            $table->text('default_value')->nullable(); // Default if null
            $table->boolean('is_required')->default(false);
            $table->boolean('is_key_field')->default(false); // Key for matching records
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('entity_mapping_id');
            $table->index('is_active');
            $table->index('is_key_field');
            // Note: odoo_field can be null for default-only mappings
            $table->index(['entity_mapping_id', 'local_field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_field_mappings');
    }
};
