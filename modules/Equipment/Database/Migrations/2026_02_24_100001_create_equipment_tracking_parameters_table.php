<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_tracking_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('equipment_id');

            // Parameter identification
            $table->string('parameter_key', 50);
            $table->string('name', 100); // Display name

            // Value configuration
            $table->enum('value_type', ['integer', 'decimal', 'boolean', 'text', 'select'])->default('decimal');
            $table->string('unit', 20)->nullable(); // J, W, Hz, ms, °C, %, etc.
            $table->decimal('min_value', 12, 4)->nullable();
            $table->decimal('max_value', 12, 4)->nullable();
            $table->string('default_value', 255)->nullable(); // Text to support all value types
            $table->decimal('step', 8, 4)->nullable(); // Increment step for number inputs
            $table->json('options')->nullable(); // For select type: [{value, label}]

            // Tracking configuration
            $table->boolean('is_required')->default(false); // Must be recorded for session completion
            $table->boolean('is_cumulative')->default(false); // Values accumulate during session
            $table->boolean('track_in_session')->default(true); // Include in session tracking

            // Display configuration
            $table->text('description')->nullable(); // Help text for staff
            $table->string('category', 50)->nullable(); // For grouping: energy, timing, safety, etc.
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('source', 50)->nullable(); // 'template' or 'custom'

            $table->timestamps();

            // Foreign keys (tenant_id FK not needed - tenants table is in public schema)
            $table->foreign('equipment_id')
                ->references('id')
                ->on('equipment')
                ->onDelete('cascade');

            // Unique constraint
            $table->unique(['equipment_id', 'parameter_key']);

            // Indexes
            $table->index(['equipment_id', 'is_active']);
            $table->index(['tenant_id', 'equipment_id']);
        });

        // Add tracking_enabled flag to equipment table
        Schema::table('equipment', function (Blueprint $table) {
            $table->boolean('tracking_enabled')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn('tracking_enabled');
        });

        Schema::dropIfExists('equipment_tracking_parameters');
    }
};
