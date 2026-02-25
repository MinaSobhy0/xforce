<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practitioner_time_off', function (Blueprint $table) {
            $table->uuid('time_off_type_id')->nullable()->after('branch_id');
            $table->decimal('days_requested', 5, 1)->nullable()->after('is_full_day');

            $table->foreign('time_off_type_id')
                ->references('id')
                ->on('time_off_types')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('practitioner_time_off', function (Blueprint $table) {
            $table->dropForeign(['time_off_type_id']);
            $table->dropColumn(['time_off_type_id', 'days_requested']);
        });
    }
};
