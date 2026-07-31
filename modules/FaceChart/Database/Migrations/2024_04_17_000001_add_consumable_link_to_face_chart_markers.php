<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('face_chart_markers', function (Blueprint $table) {
            if (! Schema::hasColumn('face_chart_markers', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable()->after('service_id');
                $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
                $table->index('product_id');
            }

            if (! Schema::hasColumn('face_chart_markers', 'session_consumable_id')) {
                $table->unsignedBigInteger('session_consumable_id')->nullable()->after('product_id');
                $table->index('session_consumable_id');

                // session_consumables is created by a later-dated Booking
                // migration (2026_02_24), so during fresh tenant provisioning
                // (globally sorted by filename) it does not exist yet. The FK
                // is added by 2026_02_24_000003_add_session_consumable_fk_to_face_chart_markers.
                if (Schema::hasTable('session_consumables')) {
                    $table->foreign('session_consumable_id')->references('id')->on('session_consumables')->nullOnDelete();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('face_chart_markers', function (Blueprint $table) {
            if (Schema::hasColumn('face_chart_markers', 'session_consumable_id')) {
                $table->dropForeign(['session_consumable_id']);
                $table->dropColumn('session_consumable_id');
            }
            if (Schema::hasColumn('face_chart_markers', 'product_id')) {
                $table->dropForeign(['product_id']);
                $table->dropColumn('product_id');
            }
        });
    }
};
