<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Realign time_off_allocations with Odoo's hr.leave.allocation shape:
 *  - owner becomes staff_profile_id (hr.employee equivalent), not user_id
 *  - validity becomes a date range (date_from, date_to), not year/month
 *
 * Old rows are backfilled: user_id -> staff_profile_id; year/month -> date range.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('time_off_allocations')) {
            return;
        }

        Schema::table('time_off_allocations', function (Blueprint $table) {
            if (! Schema::hasColumn('time_off_allocations', 'staff_profile_id')) {
                $table->foreignId('staff_profile_id')->nullable()->after('tenant_id');
            }
            if (! Schema::hasColumn('time_off_allocations', 'date_from')) {
                $table->date('date_from')->nullable()->after('time_off_type_id');
            }
            if (! Schema::hasColumn('time_off_allocations', 'date_to')) {
                $table->date('date_to')->nullable()->after('date_from');
            }
        });

        $hasUserId = Schema::hasColumn('time_off_allocations', 'user_id');
        $hasYear = Schema::hasColumn('time_off_allocations', 'year');
        $hasMonth = Schema::hasColumn('time_off_allocations', 'month');

        if ($hasUserId) {
            DB::statement('
                UPDATE time_off_allocations AS a
                SET staff_profile_id = sp.id
                FROM staff_profiles AS sp
                WHERE sp.user_id = a.user_id
                  AND a.staff_profile_id IS NULL
            ');
        }

        if ($hasYear && $hasMonth) {
            DB::statement("
                UPDATE time_off_allocations
                SET
                    date_from = CASE
                        WHEN month IS NULL
                            THEN make_date(year, 1, 1)
                        ELSE make_date(year, month, 1)
                    END,
                    date_to = CASE
                        WHEN month IS NULL
                            THEN make_date(year, 12, 31)
                        ELSE (make_date(year, month, 1) + INTERVAL '1 month' - INTERVAL '1 day')::date
                    END
                WHERE date_from IS NULL OR date_to IS NULL
            ");
        } elseif ($hasYear) {
            DB::statement('
                UPDATE time_off_allocations
                SET date_from = make_date(year, 1, 1),
                    date_to   = make_date(year, 12, 31)
                WHERE date_from IS NULL OR date_to IS NULL
            ');
        }

        // Drop orphans that can't be backfilled (no matching staff profile).
        // These would block the NOT NULL constraint. Safer to drop than leave half-migrated.
        DB::statement('DELETE FROM time_off_allocations WHERE staff_profile_id IS NULL');

        // Drop old unique index before changing columns.
        $this->dropIndexIfExists('time_off_allocations', 'time_off_allocation_unique');

        Schema::table('time_off_allocations', function (Blueprint $table) use ($hasUserId, $hasYear, $hasMonth) {
            if ($hasUserId) {
                try {
                    $table->dropForeign(['user_id']);
                } catch (\Throwable $e) {
                    // FK may not exist on some tenant schemas
                }
                $table->dropColumn('user_id');
            }
            if ($hasMonth) {
                $table->dropColumn('month');
            }
            if ($hasYear) {
                $table->dropColumn('year');
            }

            $table->foreignId('staff_profile_id')->nullable(false)->change();
            $table->date('date_from')->nullable(false)->change();
            // date_to stays nullable: matches Odoo's indefinite allocations (date_to = false).
        });

        Schema::table('time_off_allocations', function (Blueprint $table) {
            $table->foreign('staff_profile_id')
                ->references('id')
                ->on('staff_profiles')
                ->cascadeOnDelete();

            $table->unique(
                ['tenant_id', 'staff_profile_id', 'time_off_type_id', 'date_from', 'date_to'],
                'time_off_allocation_unique'
            );

            $table->index(['staff_profile_id', 'date_from', 'date_to'], 'time_off_allocation_lookup_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('time_off_allocations')) {
            return;
        }

        Schema::table('time_off_allocations', function (Blueprint $table) {
            if (! Schema::hasColumn('time_off_allocations', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('tenant_id');
            }
            if (! Schema::hasColumn('time_off_allocations', 'year')) {
                $table->integer('year')->nullable()->after('time_off_type_id');
            }
            if (! Schema::hasColumn('time_off_allocations', 'month')) {
                $table->integer('month')->nullable()->after('year');
            }
        });

        DB::statement('
            UPDATE time_off_allocations AS a
            SET user_id = sp.user_id
            FROM staff_profiles AS sp
            WHERE sp.id = a.staff_profile_id
              AND a.user_id IS NULL
        ');

        DB::statement('
            UPDATE time_off_allocations
            SET year  = EXTRACT(YEAR FROM date_from)::int,
                month = CASE
                    WHEN date_from = make_date(EXTRACT(YEAR FROM date_from)::int, 1, 1)
                     AND date_to   = make_date(EXTRACT(YEAR FROM date_from)::int, 12, 31)
                        THEN NULL
                    ELSE EXTRACT(MONTH FROM date_from)::int
                END
            WHERE year IS NULL
        ');

        $this->dropIndexIfExists('time_off_allocations', 'time_off_allocation_unique');
        $this->dropIndexIfExists('time_off_allocations', 'time_off_allocation_lookup_idx');

        Schema::table('time_off_allocations', function (Blueprint $table) {
            try {
                $table->dropForeign(['staff_profile_id']);
            } catch (\Throwable $e) {
                // ignore
            }
            $table->dropColumn(['staff_profile_id', 'date_from', 'date_to']);

            $table->foreignId('user_id')->nullable(false)->change();
            $table->integer('year')->nullable(false)->change();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(
                ['tenant_id', 'user_id', 'time_off_type_id', 'year', 'month'],
                'time_off_allocation_unique'
            );
        });
    }

    protected function dropIndexIfExists(string $table, string $index): void
    {
        $exists = DB::selectOne(
            'SELECT 1 AS ok FROM pg_indexes WHERE indexname = ? AND tablename = ?',
            [$index, $table]
        );
        if (! $exists) {
            return;
        }

        // On fresh tenant schemas the unique was created as a table CONSTRAINT
        // (backed by this index), which PostgreSQL refuses to DROP INDEX.
        $constraint = DB::selectOne(
            'SELECT 1 AS ok FROM information_schema.table_constraints
             WHERE constraint_name = ? AND table_name = ?',
            [$index, $table]
        );

        if ($constraint) {
            DB::statement("ALTER TABLE \"{$table}\" DROP CONSTRAINT \"{$index}\"");
        } else {
            DB::statement("DROP INDEX IF EXISTS \"{$index}\"");
        }
    }
};
