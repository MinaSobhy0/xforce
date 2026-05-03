<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Realign practitioner_time_off with Odoo's hr.leave shape:
 *  - owner becomes staff_profile_id (hr.employee equivalent), not user_id
 *
 * Mirrors the earlier TimeOffAllocation refactor so both leave-related models
 * share the same key. Old rows are backfilled via staff_profiles.user_id; rows
 * whose user has no staff profile in this tenant are dropped (they would block
 * the NOT NULL change anyway and are unlikely to be referenced from other places).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('practitioner_time_off')) {
            return;
        }

        Schema::table('practitioner_time_off', function (Blueprint $table) {
            if (! Schema::hasColumn('practitioner_time_off', 'staff_profile_id')) {
                $table->foreignId('staff_profile_id')->nullable()->after('tenant_id');
            }
        });

        $hasUserId = Schema::hasColumn('practitioner_time_off', 'user_id');

        if ($hasUserId) {
            DB::statement('
                UPDATE practitioner_time_off AS p
                SET staff_profile_id = sp.id
                FROM staff_profiles AS sp
                WHERE sp.user_id = p.user_id
                  AND p.staff_profile_id IS NULL
            ');
        }

        // Drop rows that cannot be backfilled — the user has no staff profile in
        // this tenant. Without this, the NOT NULL change below would fail.
        DB::statement('DELETE FROM practitioner_time_off WHERE staff_profile_id IS NULL');

        $this->dropIndexIfExists('practitioner_time_off', 'practitioner_time_off_user_id_index');
        $this->dropIndexIfExists('practitioner_time_off', 'practitioner_time_off_user_id_start_date_end_date_index');

        Schema::table('practitioner_time_off', function (Blueprint $table) use ($hasUserId) {
            if ($hasUserId) {
                try {
                    $table->dropForeign(['user_id']);
                } catch (\Throwable $e) {
                    // FK might not exist on some tenant schemas
                }
                $table->dropColumn('user_id');
            }

            $table->foreignId('staff_profile_id')->nullable(false)->change();
        });

        Schema::table('practitioner_time_off', function (Blueprint $table) {
            $table->foreign('staff_profile_id')
                ->references('id')
                ->on('staff_profiles')
                ->cascadeOnDelete();

            $table->index('staff_profile_id');
            $table->index(['staff_profile_id', 'start_date', 'end_date'], 'practitioner_time_off_staff_date_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('practitioner_time_off')) {
            return;
        }

        Schema::table('practitioner_time_off', function (Blueprint $table) {
            if (! Schema::hasColumn('practitioner_time_off', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('tenant_id');
            }
        });

        DB::statement('
            UPDATE practitioner_time_off AS p
            SET user_id = sp.user_id
            FROM staff_profiles AS sp
            WHERE sp.id = p.staff_profile_id
              AND p.user_id IS NULL
        ');

        $this->dropIndexIfExists('practitioner_time_off', 'practitioner_time_off_staff_date_idx');
        $this->dropIndexIfExists('practitioner_time_off', 'practitioner_time_off_staff_profile_id_index');

        Schema::table('practitioner_time_off', function (Blueprint $table) {
            try {
                $table->dropForeign(['staff_profile_id']);
            } catch (\Throwable $e) {
                // ignore
            }
            $table->dropColumn('staff_profile_id');

            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('user_id');
            $table->index(['user_id', 'start_date', 'end_date']);
        });
    }

    protected function dropIndexIfExists(string $table, string $index): void
    {
        $exists = DB::selectOne(
            'SELECT 1 AS ok FROM pg_indexes WHERE indexname = ? AND tablename = ?',
            [$index, $table]
        );
        if ($exists) {
            DB::statement("DROP INDEX IF EXISTS \"{$index}\"");
        }
    }
};
