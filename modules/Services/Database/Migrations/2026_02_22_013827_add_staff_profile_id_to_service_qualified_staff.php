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
        Schema::table('service_qualified_staff', function (Blueprint $table) {
            // Add staff_profile_id column
            $table->uuid('staff_profile_id')->nullable()->after('service_id');
            $table->foreign('staff_profile_id')
                ->references('id')
                ->on('staff_profiles')
                ->onDelete('cascade');
        });

        // Migrate data: convert user_id to staff_profile_id
        // Match by user_id (staff_profile can be at any branch)
        DB::statement("
            UPDATE service_qualified_staff sqs
            SET staff_profile_id = (
                SELECT sp.id
                FROM staff_profiles sp
                WHERE sp.user_id = sqs.user_id
                LIMIT 1
            )
            WHERE EXISTS (
                SELECT 1 FROM staff_profiles sp WHERE sp.user_id = sqs.user_id
            )
        ");

        // Drop old user_id column
        Schema::table('service_qualified_staff', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        // Make staff_profile_id required after migration
        Schema::table('service_qualified_staff', function (Blueprint $table) {
            $table->uuid('staff_profile_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_qualified_staff', function (Blueprint $table) {
            // Add user_id back
            $table->uuid('user_id')->nullable()->after('service_id');
        });

        // Migrate data back: convert staff_profile_id to user_id
        DB::statement("
            UPDATE service_qualified_staff sqs
            SET user_id = sp.user_id
            FROM staff_profiles sp
            WHERE sp.id = sqs.staff_profile_id
        ");

        Schema::table('service_qualified_staff', function (Blueprint $table) {
            $table->dropForeign(['staff_profile_id']);
            $table->dropColumn('staff_profile_id');
            $table->uuid('user_id')->nullable(false)->change();
        });
    }
};
