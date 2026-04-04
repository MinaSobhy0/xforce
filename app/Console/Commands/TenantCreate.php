<?php

namespace App\Console\Commands;

use App\Models\SubscriptionPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Models\Tenant;
use Modules\Core\Models\TenantStatus;
use Modules\Core\Services\TenantService;

class TenantCreate extends Command
{
    protected $signature = 'tenant:create
                            {name : The tenant/clinic name}
                            {--slug= : Custom slug (auto-generated if not provided)}
                            {--email= : Contact email (also used for owner account)}
                            {--password= : Owner password (auto-generated if not provided)}
                            {--plan= : Subscription plan code}
                            {--trial-days=14 : Number of trial days}
                            {--skip-owner : Skip creating owner user}';

    protected $description = 'Create a new tenant with database schema and owner user';

    public function __construct(
        protected TenantService $tenantService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->argument('name');
        $slug = $this->option('slug') ?: Str::slug($name);

        // Check if slug already exists
        if (Tenant::where('slug', $slug)->exists()) {
            $this->error("A tenant with slug '{$slug}' already exists.");
            return 1;
        }

        $this->info("Creating tenant: {$name}");

        // Get subscription plan
        $planCode = $this->option('plan');
        $plan = null;
        if ($planCode) {
            $plan = SubscriptionPlan::where('code', $planCode)->first();
            if (!$plan) {
                $this->error("Subscription plan '{$planCode}' not found.");
                return 1;
            }
        }

        try {
            // Use TenantService to create tenant with proper schema and migrations
            $tenant = $this->tenantService->create([
                'name' => $name,
                'slug' => $slug,
                'domain' => $slug . '.xforcehr.com',
                'contact_email' => $this->option('email'),
                'status' => TenantStatus::ACTIVE,
                'settings' => [
                    'subscription_plan_id' => $plan?->id,
                    'trial_ends_at' => now()->addDays((int) $this->option('trial-days'))->toISOString(),
                ],
            ]);

            $this->info("Tenant created with ID: {$tenant->id}");

            // Get owner credentials if created
            $ownerPassword = null;
            if ($tenant->contact_email && !$this->option('skip-owner')) {
                $ownerPassword = $this->option('password') ?: ($tenant->settings['initial_owner_password'] ?? null);
            }

            $this->newLine();
            $this->info("Tenant created successfully!");
            $this->table(
                ['Property', 'Value'],
                [
                    ['ID', $tenant->id],
                    ['Name', $tenant->name],
                    ['Slug', $tenant->slug],
                    ['Domain', $tenant->domain],
                    ['Schema', $tenant->database_name],
                    ['Status', $tenant->status->value],
                    ['Plan', $plan?->code ?? 'None'],
                ]
            );

            if ($tenant->contact_email && $ownerPassword) {
                $this->newLine();
                $this->info("Owner Account Credentials:");
                $this->table(
                    ['Property', 'Value'],
                    [
                        ['Email', $tenant->contact_email],
                        ['Password', $ownerPassword],
                        ['Login URL', "https://{$slug}.xforcehr.com/admin"],
                    ]
                );
                $this->warn("Please save these credentials - the password cannot be retrieved later!");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("Failed to create tenant: " . $e->getMessage());

            // Try to clean up if tenant was partially created
            try {
                $partialTenant = Tenant::where('slug', $slug)->first();
                if ($partialTenant) {
                    DB::statement("DROP SCHEMA IF EXISTS \"{$partialTenant->database_name}\" CASCADE");
                    $partialTenant->forceDelete();
                    $this->warn("Cleaned up partial tenant data.");
                }
            } catch (\Exception $cleanupError) {
                $this->warn("Could not clean up: " . $cleanupError->getMessage());
            }

            return 1;
        }
    }
}
