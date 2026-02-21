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
        Schema::table('practitioner_schedule_assignments', function (Blueprint $table) {
            // Add staff_profile_id column
            $table->uuid('staff_profile_id')->nullable()->after('tenant_id');
            $table->foreign('staff_profile_id')
                ->references('id')
                ->on('staff_profiles')
                ->onDelete('cascade');
        });

        // Migrate data: convert user_id to staff_profile_id
        DB::statement("
            UPDATE practitioner_schedule_assignments psa
            SET staff_profile_id = sp.id
            FROM staff_profiles sp
            WHERE sp.user_id = psa.user_id
            AND sp.branch_id = psa.branch_id
        ");

        // Drop old user_id column and foreign key
        Schema::table('practitioner_schedule_assignments', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        // Make staff_profile_id required after migration
        Schema::table('practitioner_schedule_assignments', function (Blueprint $table) {
            $table->uuid('staff_profile_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('practitioner_schedule_assignments', function (Blueprint $table) {
            // Add user_id back
            $table->uuid('user_id')->nullable()->after('tenant_id');
        });

        // Migrate data back: convert staff_profile_id to user_id
        DB::statement("
            UPDATE practitioner_schedule_assignments psa
            SET user_id = sp.user_id
            FROM staff_profiles sp
            WHERE sp.id = psa.staff_profile_id
        ");

        Schema::table('practitioner_schedule_assignments', function (Blueprint $table) {
            $table->dropForeign(['staff_profile_id']);
            $table->dropColumn('staff_profile_id');
            $table->uuid('user_id')->nullable(false)->change();
        });
    }
};
