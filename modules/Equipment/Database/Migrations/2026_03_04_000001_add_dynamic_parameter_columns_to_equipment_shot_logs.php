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
        Schema::table('equipment_shot_logs', function (Blueprint $table) {
            $table->jsonb('cumulative_data')->nullable()->after('notes')
                ->comment('Cumulative parameter values recorded this session');
            $table->jsonb('all_parameters')->nullable()->after('cumulative_data')
                ->comment('All equipment parameter values for this session');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment_shot_logs', function (Blueprint $table) {
            $table->dropColumn(['cumulative_data', 'all_parameters']);
        });
    }
};
