<?php

namespace App\Console\Commands;

use App\Models\PlatformSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\Tenant;
use Modules\Core\Models\TenantStatus;

/**
 * Auto-suspend tenants whose subscription has been past-due longer than
 * the platform's `auto_suspend_after` threshold (Platform Settings →
 * Trial tab, default 7 days).
 *
 * A tenant is considered "past-due for N days" when:
 *   - subscription_expires_at is in the past, and
 *   - days_since(expired_at) >= auto_suspend_after
 *
 * Suspended tenants land on the friendly errors.tenant-suspended page
 * (see IdentifyTenant middleware) and the platform admin can reactivate
 * them from the tenant view.
 *
 * Trial tenants are *not* auto-suspended here — TenantStatus::PENDING /
 * trial_ends_at logic belongs to a separate flow.
 *
 * Schedule via routes/console.php:
 *   Schedule::command('tenants:auto-suspend')->dailyAt('03:00');
 */
class TenantsAutoSuspend extends Command
{
    protected $signature = 'tenants:auto-suspend
                            {--dry-run : Report what would be suspended without writing.}';

    protected $description = 'Suspend tenants whose subscription has been overdue beyond the auto_suspend_after threshold.';

    public function handle(): int
    {
        $thresholdDays = (int) PlatformSetting::get('auto_suspend_after', 7);
        if ($thresholdDays < 1) {
            $this->warn("auto_suspend_after is {$thresholdDays}; nothing to do.");

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($thresholdDays);
        $this->info("Auto-suspend threshold: {$thresholdDays} days. Cutoff: {$cutoff->toDateTimeString()}");

        $candidates = Tenant::query()
            ->where('status', TenantStatus::ACTIVE)
            ->whereNotNull('subscription_expires_at')
            ->where('subscription_expires_at', '<', $cutoff)
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No tenants past the suspend threshold.');

            return self::SUCCESS;
        }

        $suspended = 0;
        foreach ($candidates as $tenant) {
            $daysOverdue = (int) now()->diffInDays($tenant->subscription_expires_at, false) * -1;

            $this->line("  - {$tenant->slug} ({$tenant->name}): {$daysOverdue} days past due");

            if ($this->option('dry-run')) {
                continue;
            }

            // status + subscription_status are $guarded; use direct property
            // assignment + save() (the trusted-admin path we already use in
            // the Filament suspend action).
            $tenant->status = TenantStatus::SUSPENDED;
            $tenant->subscription_status = 'suspended';
            $tenant->save();

            activity()
                ->performedOn($tenant)
                ->withProperties([
                    'reason' => 'Auto-suspended: subscription overdue',
                    'days_overdue' => $daysOverdue,
                    'auto_suspend_after' => $thresholdDays,
                ])
                ->log('Tenant auto-suspended');

            Log::warning('Tenant auto-suspended due to overdue subscription', [
                'tenant_id' => $tenant->id,
                'slug' => $tenant->slug,
                'days_overdue' => $daysOverdue,
                'threshold' => $thresholdDays,
            ]);

            $suspended++;
        }

        $verb = $this->option('dry-run') ? 'would be' : 'were';
        $this->info(($suspended ?: $candidates->count())." tenant(s) {$verb} suspended.");

        return self::SUCCESS;
    }
}
