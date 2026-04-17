<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('face_chart_markers', function (Blueprint $table) {
            if (!Schema::hasColumn('face_chart_markers', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable()->after('service_id');
                $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
                $table->index('product_id');
            }

            if (!Schema::hasColumn('face_chart_markers', 'session_consumable_id')) {
                $table->unsignedBigInteger('session_consumable_id')->nullable()->after('product_id');
                $table->foreign('session_consumable_id')->references('id')->on('session_consumables')->nullOnDelete();
                $table->index('session_consumable_id');
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
