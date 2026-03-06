<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('source_location_id')->nullable()->after('destination_branch_id');
            $table->foreignId('destination_location_id')->nullable()->after('source_location_id');

            $table->foreign('source_location_id')
                ->references('id')
                ->on('stock_locations')
                ->nullOnDelete();

            $table->foreign('destination_location_id')
                ->references('id')
                ->on('stock_locations')
                ->nullOnDelete();

            $table->index(['source_location_id']);
            $table->index(['destination_location_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['source_location_id']);
            $table->dropForeign(['destination_location_id']);
            $table->dropColumn(['source_location_id', 'destination_location_id']);
        });
    }
};
