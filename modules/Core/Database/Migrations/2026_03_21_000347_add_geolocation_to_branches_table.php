<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('google_maps_url');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->integer('geofence_radius')->nullable()->after('longitude')->comment('Geofence radius in meters');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'geofence_radius']);
        });
    }
};
