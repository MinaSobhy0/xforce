<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = $this->getConnection() ?: config('database.default');

        if (!Schema::connection($connection)->hasColumn('branches', 'latitude')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->decimal('latitude', 10, 7)->nullable()->after('google_maps_url');
            });
        }
        if (!Schema::connection($connection)->hasColumn('branches', 'longitude')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            });
        }
        if (!Schema::connection($connection)->hasColumn('branches', 'geofence_radius')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->integer('geofence_radius')->nullable()->after('longitude')->comment('Geofence radius in meters');
            });
        }
    }

    public function down(): void
    {
        $connection = $this->getConnection() ?: config('database.default');

        $columnsToDrop = [];
        foreach (['latitude', 'longitude', 'geofence_radius'] as $col) {
            if (Schema::connection($connection)->hasColumn('branches', $col)) {
                $columnsToDrop[] = $col;
            }
        }
        if (!empty($columnsToDrop)) {
            Schema::table('branches', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }
};
