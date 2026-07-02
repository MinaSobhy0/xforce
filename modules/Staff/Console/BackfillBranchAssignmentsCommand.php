<?php

namespace Modules\Staff\Console;

use Illuminate\Console\Command;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Modules\Auth\Models\UserBranchRole;
use Modules\Core\Models\Tenant;
use Modules\Core\Services\TenantSchemaSwitcher;
use Modules\Staff\Models\StaffProfile;

/**
 * Backfill UserBranchRole assignments for employees who have a staff
 * profile with a branch_id set but no active branch-role row.
 *
 * Pre-existing data drift: before the "role_id NOT NULL violation" fix
 * (3cd69d51), the branch-role picker in the User → Branch Roles tab
 * silently dropped guarded fields when it hit create(), so assignments
 * either 500'd on the NOT NULL constraint or never got made in the
 * first place. Employees that were imported/seeded around that window
 * ended up with a branch on their staff_profile but nothing in
 * user_branch_roles — which the mobile app + branch-scoped queries
 * treat as "no branch access", so the app shows nothing.
 *
 *   php artisan staff:backfill-branch-assignments --tenant=demo
 *   php artisan staff:backfill-branch-assignments --tenant=demo --role=staff
 *   php artisan staff:backfill-branch-assignments --tenant=demo --dry531
 *
 * Idempotent: a user who already has an active branch-role row is
 * skipped even if it's on a different branch (existing manual
 * assignment wins). Source of the branch: staff_profiles.branch_id.
 */
class BackfillBranchAssignmentsCommand extends Command
{
    protected $signature = 'staff:backfill-branch-assignments
                            {--tenant= : Tenant slug to backfill (required)}
                            {--role=staff : Default role name to assign when the user has no Spatie role}
                            {--dry-run : Print what would happen without writing}';

    protected $description = 'Create UserBranchRole rows for employees whose staff_profile has a branch_id but who have no active branch-role assignment.';

    public function handle(TenantSchemaSwitcher $switcher): int
    {
        $slug = $this->option('tenant');
        if (! $slug) {
            $this->error('--tenant is required (slug of the tenant to backfill).');

            return self::FAILURE;
        }

        $tenant = Tenant::where('slug', $slug)->first();
        if (! $tenant) {
            $this->error("No tenant with slug '{$slug}'.");

            return self::FAILURE;
        }

        $switcher->switchTo($tenant);

        $defaultRoleName = (string) $this->option('role');
        $defaultRole = Role::where('name', $defaultRoleName)->first();
        if (! $defaultRole) {
            $this->error("Default role '{$defaultRoleName}' not found in tenant {$slug} — pass --role=<existing role name>.");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        // Load candidates in one shot: users with a staff_profile that has a
        // branch_id, who have zero active branch-role rows.
        $candidates = User::query()
            ->whereHas('staffProfile', fn ($q) => $q->whereNotNull('branch_id'))
            ->whereDoesntHave('branchRoles', fn ($q) => $q->where('is_active', true))
            ->with(['staffProfile', 'roles'])
            ->get();

        if ($candidates->isEmpty()) {
            $this->info("Nothing to backfill — every employee already has an active branch-role assignment.");

            return self::SUCCESS;
        }

        $this->info("Found {$candidates->count()} employees to backfill in tenant '{$slug}'.");
        if ($dryRun) {
            $this->warn('DRY RUN — no rows will be created.');
        }

        $created = 0;
        $skipped = 0;

        foreach ($candidates as $user) {
            $branchId = $user->staffProfile?->branch_id;
            if (! $branchId) {
                $skipped++;

                continue;
            }

            // Prefer the user's first Spatie-assigned role; fall back to
            // the --role default. Both are Modules\Auth\Models\Role instances.
            $roleId = $user->roles->first()?->id ?? $defaultRole->id;

            if ($dryRun) {
                $this->line("  would assign user #{$user->id} ({$user->full_name}) → branch #{$branchId} with role #{$roleId}");
                $created++;

                continue;
            }

            UserBranchRole::assign([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'branch_id' => $branchId,
                'role_id' => $roleId,
                'is_primary' => true,
                'is_active' => true,
                'assigned_at' => now(),
                'assigned_by' => null, // system backfill, no acting user
            ]);

            $created++;
        }

        $this->info("Done. {$created} rows " . ($dryRun ? 'would be created' : 'created') . ", {$skipped} skipped.");

        return self::SUCCESS;
    }
}
