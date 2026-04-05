<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add endpoint coordinates for surface-following arrows.
     */
    public function up(): void
    {
        Schema::table('face_chart_markers', function (Blueprint $table) {
            // Arrow endpoint coordinates (arrow goes from marker x,y,z to end_x,end_y,end_z)
            $table->decimal('arrow_end_x', 10, 6)->nullable()->after('depth_mm');
            $table->decimal('arrow_end_y', 10, 6)->nullable()->after('arrow_end_x');
            $table->decimal('arrow_end_z', 10, 6)->nullable()->after('arrow_end_y');
        });

        // Clear existing direction data since we're changing the system
        \DB::table('face_chart_markers')->update([
            'direction_x' => null,
            'direction_y' => null,
            'direction_z' => null,
            'depth_mm' => null,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('face_chart_markers', function (Blueprint $table) {
            $table->dropColumn(['arrow_end_x', 'arrow_end_y', 'arrow_end_z']);
        });
    }
};
