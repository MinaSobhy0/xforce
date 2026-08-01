<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * H-10 prerequisite: ensure every active staff user has a branch assignment so
 * enabling EnforceBranchAccess (BRANCH_ACCESS_ENFORCE=true) does not 403 them.
 *
 * For each active tenant, every active user (users table = staff; patients are
 * separate and unaffected) that has no active+valid user_branch_roles row is
 * assigned to ALL of the tenant's active branches (main branch flagged primary),
 * reusing the user's existing role. This preserves current visibility (zero
 * lockout); going forward, enforcement 403s only NEW unassigned users until an
 * admin scopes them — which is where real per-branch isolation is set for
 * multi-branch tenants.
 *
 * Idempotent: users that already have an active+valid assignment are skipped.
 */
class BackfillBranchAssignments extends Command
{
    protected $signature = 'branch:backfill-assignments
        {--dry-run : Report what would be created without writing}
        {--tenant= : Restrict to a single tenant slug}';

    protected $description = 'Assign active staff users without a branch role to their tenant branches (H-10)';

    private const USER_MODEL = \Modules\Auth\Models\User::class;

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $only = $this->option('tenant');

        if ($dry) {
            $this->warn('DRY RUN — no rows will be created.');
        }

        $tenants = DB::connection('pgsql')->table('tenants')->where('status', 'active')->get(['id', 'slug', 'database_name']);
        $grand = 0;

        foreach ($tenants as $t) {
            if (! preg_match('/^[a-z][a-z0-9_]*$/', (string) $t->database_name)) {
                $this->warn("skip invalid schema: {$t->database_name}");

                continue;
            }
            if ($only !== null && $t->slug !== $only) {
                continue;
            }

            try {
                DB::statement('SET search_path TO "'.$t->database_name.'"');

                $branches = DB::table('branches')->where('is_active', true)
                    ->orderBy('is_main', 'desc')->orderBy('id')->pluck('id')->all();

                if (empty($branches)) {
                    $this->line(str_pad($t->slug, 12).' (no active branches — skipped)');

                    continue;
                }

                // Users who already have an active, non-expired assignment.
                $assigned = DB::table('user_branch_roles')->where('is_active', true)
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->distinct()->pluck('user_id')->all();

                $users = DB::table('users')->whereNull('deleted_at')->whereNotIn('id', $assigned)->pluck('id')->all();
                $fallbackRole = DB::table('roles')->min('id');

                $usersDone = 0;
                $created = 0;

                foreach ($users as $uid) {
                    $roleId = DB::table('model_has_roles')
                        ->where('model_type', self::USER_MODEL)->where('model_id', $uid)
                        ->value('role_id') ?? $fallbackRole;

                    if ($roleId === null) {
                        $this->warn("  user {$uid}: no role available — skipped");

                        continue;
                    }

                    $usersDone++;

                    foreach ($branches as $i => $bid) {
                        if (! $dry) {
                            DB::table('user_branch_roles')->insert([
                                'tenant_id' => $t->id,
                                'user_id' => $uid,
                                'branch_id' => $bid,
                                'role_id' => $roleId,
                                'is_primary' => $i === 0,
                                'is_active' => true,
                                'assigned_at' => now(),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                        $created++;
                    }
                }

                $this->line(str_pad($t->slug, 12).' branches='.count($branches)
                    ." users_assigned={$usersDone} rows_created={$created}");
                $grand += $created;
            } catch (\Throwable $e) {
                $this->error("{$t->slug}: ".$e->getMessage());
            } finally {
                DB::statement('SET search_path TO public');
            }
        }

        $this->info(($dry ? 'Would create' : 'Created')." {$grand} assignment row(s).");

        return self::SUCCESS;
    }
}
