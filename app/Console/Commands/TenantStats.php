<?php

namespace App\Console\Commands;

use App\Models\SubscriptionPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Tenant;

class TenantStats extends Command
{
    protected $signature = 'tenant:stats
                            {--tenant= : Show stats for specific tenant}
                            {--detailed : Show detailed statistics}';

    protected $description = 'Show tenant statistics and usage information';

    public function handle(): int
    {
        if ($tenantIdentifier = $this->option('tenant')) {
            return $this->showTenantStats($tenantIdentifier);
        }

        return $this->showOverview();
    }

    protected function showOverview(): int
    {
        $this->info("=== Tenant Statistics Overview ===");
        $this->newLine();

        // Status breakdown
        $statusCounts = Tenant::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $this->info("Tenants by Status:");
        $this->table(
            ['Status', 'Count'],
            collect($statusCounts)->map(fn($count, $status) => [$status, $count])->toArray()
        );

        // Plan breakdown
        $planCounts = Tenant::query()
            ->leftJoin('subscription_plans', 'tenants.subscription_plan_id', '=', 'subscription_plans.id')
            ->selectRaw('COALESCE(subscription_plans.code, \'No Plan\') as plan, COUNT(*) as count')
            ->groupBy('subscription_plans.code')
            ->pluck('count', 'plan')
            ->toArray();

        $this->info("Tenants by Plan:");
        $this->table(
            ['Plan', 'Count'],
            collect($planCounts)->map(fn($count, $plan) => [$plan, $count])->toArray()
        );

        // Recent activity
        $this->info("Recent Signups (Last 30 Days):");
        $recentSignups = Tenant::where('created_at', '>=', now()->subDays(30))->count();
        $this->line("  {$recentSignups} new tenants");

        // Trial expirations
        $expiringTrials = Tenant::where('status', 'trial')
            ->where('trial_ends_at', '<=', now()->addDays(7))
            ->where('trial_ends_at', '>', now())
            ->count();

        $expiredTrials = Tenant::where('status', 'trial')
            ->where('trial_ends_at', '<', now())
            ->count();

        $this->newLine();
        $this->info("Trial Status:");
        $this->line("  Expiring in 7 days: {$expiringTrials}");
        $this->line("  Already expired: {$expiredTrials}");

        if ($this->option('detailed')) {
            $this->showDetailedStats();
        }

        return 0;
    }

    protected function showTenantStats(string $identifier): int
    {
        $tenant = Tenant::where('id', $identifier)
            ->orWhere('slug', $identifier)
            ->first();

        if (!$tenant) {
            $this->error("Tenant not found: {$identifier}");
            return 1;
        }

        $this->info("=== Statistics for: {$tenant->name} ===");
        $this->newLine();

        // Basic info
        $this->table(
            ['Property', 'Value'],
            [
                ['ID', $tenant->id],
                ['Slug', $tenant->slug],
                ['Status', $tenant->status],
                ['Plan', $tenant->plan?->code ?? 'None'],
                ['Created', $tenant->created_at?->format('Y-m-d H:i')],
                ['Trial Ends', $tenant->trial_ends_at?->format('Y-m-d') ?? 'N/A'],
                ['Subscription Expires', $tenant->subscription_expires_at?->format('Y-m-d') ?? 'N/A'],
            ]
        );

        // Get usage from tenant_usage table
        $usage = DB::table('tenant_usage')->where('tenant_id', $tenant->id)->first();

        if ($usage) {
            $this->newLine();
            $this->info("Usage Statistics:");
            $this->table(
                ['Metric', 'Current', 'Limit'],
                [
                    ['Users', $usage->users_count ?? 0, $tenant->getEffectiveLimit('users') ?? 'Unlimited'],
                    ['Branches', $usage->branches_count ?? 0, $tenant->getEffectiveLimit('branches') ?? 'Unlimited'],
                    ['Patients', $usage->patients_count ?? 0, 'Unlimited'],
                    ['Storage', $this->formatBytes($usage->storage_used_bytes ?? 0), $this->formatBytes(($tenant->getEffectiveLimit('storage_mb') ?? 0) * 1024 * 1024)],
                ]
            );

            $this->newLine();
            $this->info("Monthly Stats:");
            $this->table(
                ['Metric', 'This Month'],
                [
                    ['Appointments', $usage->appointments_this_month ?? 0],
                    ['New Patients', $usage->new_patients_this_month ?? 0],
                    ['Treatments', $usage->treatments_this_month ?? 0],
                    ['Revenue', 'EGP ' . number_format(($usage->revenue_this_month_minor ?? 0) / 100, 2)],
                    ['WhatsApp Messages', $usage->whatsapp_messages_count ?? 0],
                    ['SMS Messages', $usage->sms_messages_count ?? 0],
                    ['Emails Sent', $usage->emails_sent_count ?? 0],
                ]
            );
        } else {
            $this->warn("No usage data available for this tenant.");
        }

        return 0;
    }

    protected function showDetailedStats(): void
    {
        $this->newLine();
        $this->info("=== Detailed Statistics ===");

        // MRR calculation
        $mrr = DB::table('tenants')
            ->join('subscription_plans', 'tenants.subscription_plan_id', '=', 'subscription_plans.id')
            ->where('tenants.status', 'active')
            ->sum('subscription_plans.price_monthly_minor');

        $this->line("Monthly Recurring Revenue (MRR): EGP " . number_format($mrr / 100, 2));

        // Top tenants by usage
        $this->newLine();
        $this->info("Top 5 Tenants by Patient Count:");
        $topTenants = DB::table('tenant_usage')
            ->join('tenants', 'tenant_usage.tenant_id', '=', 'tenants.id')
            ->orderByDesc('patients_count')
            ->limit(5)
            ->select('tenants.name', 'tenant_usage.patients_count', 'tenant_usage.appointments_this_month')
            ->get();

        if ($topTenants->isNotEmpty()) {
            $this->table(
                ['Tenant', 'Patients', 'Appointments (This Month)'],
                $topTenants->map(fn($t) => [$t->name, $t->patients_count, $t->appointments_this_month])->toArray()
            );
        }
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
}
