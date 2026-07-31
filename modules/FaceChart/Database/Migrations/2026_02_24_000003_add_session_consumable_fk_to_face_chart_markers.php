<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Companion to 2024_04_17_000001_add_consumable_link_to_face_chart_markers.
 *
 * During fresh tenant provisioning, migrations run globally sorted by
 * filename, so the 2024-dated FaceChart migration executes before
 * session_consumables exists (created 2026_02_24 in Booking). That earlier
 * migration now skips the FK when the table is missing; this one adds it
 * once session_consumables is guaranteed to exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('face_chart_markers')
            || ! Schema::hasTable('session_consumables')
            || ! Schema::hasColumn('face_chart_markers', 'session_consumable_id')) {
            return;
        }

        if ($this->foreignKeyExists()) {
            return;
        }

        Schema::table('face_chart_markers', function (Blueprint $table) {
            $table->foreign('session_consumable_id')
                ->references('id')
                ->on('session_consumables')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if ($this->foreignKeyExists()) {
            Schema::table('face_chart_markers', function (Blueprint $table) {
                $table->dropForeign(['session_consumable_id']);
            });
        }
    }

    protected function foreignKeyExists(): bool
    {
        $result = DB::selectOne("
            SELECT EXISTS (
                SELECT 1
                FROM information_schema.table_constraints
                WHERE table_name = 'face_chart_markers'
                  AND constraint_name = 'face_chart_markers_session_consumable_id_foreign'
                  AND table_schema = ANY (current_schemas(false))
            ) AS exists
        ");

        return (bool) ($result->exists ?? false);
    }
};
