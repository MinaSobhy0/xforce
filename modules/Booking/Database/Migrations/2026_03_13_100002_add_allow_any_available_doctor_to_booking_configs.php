<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('booking_configs', 'allow_any_available_doctor')) {
            Schema::table('booking_configs', function (Blueprint $table) {
                $table->boolean('allow_any_available_doctor')
                    ->default(false)
                    ->after('allow_doctor_overlap');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('booking_configs', 'allow_any_available_doctor')) {
            Schema::table('booking_configs', function (Blueprint $table) {
                $table->dropColumn('allow_any_available_doctor');
            });
        }
    }
};
