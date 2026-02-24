<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add new JSON columns
        Schema::table('parameter_presets', function (Blueprint $table) {
            $table->json('name')->nullable()->after('service_id');
            $table->json('values')->nullable()->after('description');
        });

        // Migrate existing data to new columns
        DB::statement("
            UPDATE parameter_presets
            SET name = jsonb_build_object('en', preset_name),
                values = preset_values,
                description = CASE
                    WHEN description IS NOT NULL AND description != ''
                    THEN jsonb_build_object('en', description)
                    ELSE NULL
                END
        ");

        // Drop old columns
        Schema::table('parameter_presets', function (Blueprint $table) {
            $table->dropColumn(['preset_name', 'preset_values']);
        });

        // Make name not nullable
        DB::statement("ALTER TABLE parameter_presets ALTER COLUMN name SET NOT NULL");
        DB::statement("ALTER TABLE parameter_presets ALTER COLUMN values SET NOT NULL");
        DB::statement("ALTER TABLE parameter_presets ALTER COLUMN values SET DEFAULT '{}'::jsonb");
    }

    public function down(): void
    {
        // Add back old columns
        Schema::table('parameter_presets', function (Blueprint $table) {
            $table->string('preset_name', 200)->nullable()->after('service_id');
            $table->json('preset_values')->nullable()->after('description');
        });

        // Migrate data back
        DB::statement("
            UPDATE parameter_presets
            SET preset_name = COALESCE(name->>'en', ''),
                preset_values = values,
                description = COALESCE(description->>'en', NULL)
        ");

        // Make preset_name not nullable
        DB::statement("ALTER TABLE parameter_presets ALTER COLUMN preset_name SET NOT NULL");
        DB::statement("ALTER TABLE parameter_presets ALTER COLUMN preset_values SET NOT NULL");

        // Drop new columns
        Schema::table('parameter_presets', function (Blueprint $table) {
            $table->dropColumn(['name', 'values']);
        });

        // Change description back to text
        DB::statement("ALTER TABLE parameter_presets ALTER COLUMN description TYPE text USING description->>'en'");
    }
};
