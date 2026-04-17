<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('face_chart_markers', function (Blueprint $table) {
            $table->enum('view_type', ['3d', '2d'])->default('3d')->after('z');
            $table->text('annotation_text')->nullable()->after('notes');
            $table->jsonb('annotation_style')->nullable()->after('annotation_text');
            $table->index(['patient_id', 'view_type']);
        });

        // Set all existing markers to 3D
        DB::table('face_chart_markers')->update(['view_type' => '3d']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('face_chart_markers', function (Blueprint $table) {
            $table->dropIndex(['patient_id', 'view_type']);
            $table->dropColumn(['view_type', 'annotation_text', 'annotation_style']);
        });
    }
};
