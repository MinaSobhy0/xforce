<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add EquipmentType fields to Equipment table
        Schema::table('equipment', function (Blueprint $table) {
            $table->string('manufacturer')->nullable()->after('name');
            $table->string('model')->nullable()->after('manufacturer');
            $table->string('category')->default('other')->after('model');
            $table->jsonb('specifications')->nullable()->after('category');
            $table->integer('max_shots')->nullable()->after('specifications');
            $table->string('image_url')->nullable()->after('max_shots');
        });

        // Step 2: Migrate data from equipment_types to equipment
        DB::statement("
            UPDATE equipment e
            SET
                manufacturer = et.manufacturer,
                model = et.model,
                category = et.category,
                specifications = et.specifications,
                max_shots = et.max_shots,
                image_url = et.image_url
            FROM equipment_types et
            WHERE e.equipment_type_id = et.id
        ");

        // Step 3: Make name translatable (JSON) if not already
        // Note: name is already a string, we'll keep it as string for simplicity
        // If translation is needed, we can add a separate translated_name column

        // Step 4: Drop the equipment_type_id foreign key and column
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropForeign(['equipment_type_id']);
            $table->dropColumn('equipment_type_id');
        });

        // Step 5: Add indexes
        Schema::table('equipment', function (Blueprint $table) {
            $table->index('category');
            $table->index('manufacturer');
        });
    }

    public function down(): void
    {
        // Re-add equipment_type_id
        Schema::table('equipment', function (Blueprint $table) {
            $table->uuid('equipment_type_id')->nullable()->after('name');
            $table->foreign('equipment_type_id')
                ->references('id')
                ->on('equipment_types')
                ->nullOnDelete();
        });

        // Drop the new columns
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropIndex(['manufacturer']);
            $table->dropColumn([
                'manufacturer',
                'model',
                'category',
                'specifications',
                'max_shots',
                'image_url',
            ]);
        });
    }
};
