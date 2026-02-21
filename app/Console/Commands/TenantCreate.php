<?php

namespace App\Console\Commands;

use App\Models\SubscriptionPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Models\Tenant;

class TenantCreate extends Command
{
    protected $signature = 'tenant:create
                            {name : The tenant/clinic name}
                            {--slug= : Custom slug (auto-generated if not provided)}
                            {--email= : Contact email}
                            {--plan= : Subscription plan code}
                            {--trial-days=14 : Number of trial days}
                            {--owner-id= : User ID of the owner}';

    protected $description = 'Create a new tenant with database schema';

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
            DB::beginTransaction();

            // Create tenant record
            $tenant = Tenant::create([
                'id' => Str::uuid(),
                'name' => $name,
                'slug' => $slug,
                'domain' => $slug . '.x-linic.com',
                'database_name' => 'tenant_' . str_replace('-', '_', $slug),
                'status' => 'trial',
                'subscription_plan_id' => $plan?->id,
                'subscription_status' => 'trial',
                'trial_ends_at' => now()->addDays((int) $this->option('trial-days')),
                'contact_email' => $this->option('email'),
                'owner_user_id' => $this->option('owner-id'),
                'timezone' => 'Africa/Cairo',
                'locale' => 'ar',
                'currency' => 'EGP',
            ]);

            $this->info("Tenant record created with ID: {$tenant->id}");

            // Create PostgreSQL schema for tenant
            $schemaName = 'tenant_' . str_replace('-', '_', $slug);
            DB::statement("CREATE SCHEMA IF NOT EXISTS \"{$schemaName}\"");
            $this->info("Database schema '{$schemaName}' created.");

            // Run migrations for the tenant schema
            $this->info("Running migrations for tenant schema...");
            $this->call('tenant:migrate', ['--tenant' => $tenant->id]);

            // Optionally seed default data
            if ($this->confirm('Do you want to seed default data for this tenant?', true)) {
                $this->call('tenant:seed', ['--tenant' => $tenant->id]);
            }

            DB::commit();

            $this->newLine();
            $this->info("Tenant created successfully!");
            $this->table(
                ['Property', 'Value'],
                [
                    ['ID', $tenant->id],
                    ['Name', $tenant->name],
                    ['Slug', $tenant->slug],
                    ['Domain', $tenant->domain],
                    ['Schema', $schemaName],
                    ['Status', $tenant->status],
                    ['Plan', $plan?->code ?? 'None'],
                    ['Trial Ends', $tenant->trial_ends_at?->format('Y-m-d')],
                ]
            );

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Failed to create tenant: " . $e->getMessage());
            return 1;
        }
    }
}
