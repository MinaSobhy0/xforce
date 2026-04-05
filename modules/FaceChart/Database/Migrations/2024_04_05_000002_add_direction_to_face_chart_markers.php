<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('face_chart_markers', function (Blueprint $table) {
            // Direction vector for injection angle (normalized direction)
            $table->decimal('direction_x', 10, 6)->nullable()->after('z');
            $table->decimal('direction_y', 10, 6)->nullable()->after('direction_x');
            $table->decimal('direction_z', 10, 6)->nullable()->after('direction_y');

            // Depth of injection in mm
            $table->decimal('depth_mm', 6, 2)->nullable()->after('direction_z');

            // Service category ID for icon/visual grouping
            $table->foreignId('service_category_id')
                ->nullable()
                ->after('service_id')
                ->constrained('service_categories')
                ->nullOnDelete();

            // Index for filtering by category
            $table->index(['patient_id', 'service_category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('face_chart_markers', function (Blueprint $table) {
            $table->dropIndex(['patient_id', 'service_category_id']);
            $table->dropConstrainedForeignId('service_category_id');
            $table->dropColumn(['direction_x', 'direction_y', 'direction_z', 'depth_mm']);
        });
    }
};
