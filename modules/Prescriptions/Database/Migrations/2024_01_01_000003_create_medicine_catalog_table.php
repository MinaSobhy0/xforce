<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicine_catalog', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index(); // null = system default

            // Basic info
            $table->string('brand_name');
            $table->string('generic_name')->nullable();
            $table->string('manufacturer')->nullable();

            // Dosage info
            $table->string('strength')->nullable(); // e.g., "500", "250"
            $table->string('strength_unit')->nullable(); // mg, ml, g, etc.
            $table->string('form')->nullable(); // tablet, capsule, syrup, etc.

            // Default prescription settings
            $table->string('default_dosage')->nullable();
            $table->string('default_dosage_unit')->nullable();
            $table->string('default_frequency')->nullable();
            $table->integer('default_duration')->nullable();
            $table->string('default_duration_unit')->nullable();
            $table->string('default_route')->nullable();
            $table->string('default_instructions')->nullable();

            // Additional info
            $table->text('description')->nullable();
            $table->text('indications')->nullable(); // What it's used for
            $table->text('contraindications')->nullable(); // When not to use
            $table->text('side_effects')->nullable();
            $table->text('warnings')->nullable();

            // Classification
            $table->string('category')->nullable(); // antibiotic, analgesic, etc.
            $table->string('drug_class')->nullable();
            $table->boolean('is_controlled')->default(false);
            $table->boolean('requires_prescription')->default(true);

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // System defaults can't be deleted

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'brand_name']);
            $table->index(['tenant_id', 'generic_name']);
            $table->index(['tenant_id', 'category']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicine_catalog');
    }
};
