<?php

namespace App\Console\Commands;

use App\Models\SubscriptionPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Core\Models\Tenant;

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

            // Create owner user if email provided and not skipped
            $ownerEmail = $this->option('email');
            $ownerPassword = null;
            $ownerId = null;

            if ($ownerEmail && !$this->option('skip-owner')) {
                $ownerPassword = $this->option('password') ?: Str::random(12);
                $ownerId = $this->createOwnerUser($tenant, $schemaName, $ownerEmail, $ownerPassword);

                if ($ownerId) {
                    // Update tenant with owner_user_id
                    $tenant->update(['owner_user_id' => $ownerId]);
                    $this->info("Owner user created: {$ownerEmail}");
                }
            }

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

            if ($ownerEmail && $ownerId) {
                $this->newLine();
                $this->info("Owner Account Credentials:");
                $this->table(
                    ['Property', 'Value'],
                    [
                        ['Email', $ownerEmail],
                        ['Password', $ownerPassword],
                        ['Login URL', "https://{$slug}.x-linic.com/admin"],
                    ]
                );
                $this->warn("Please save these credentials - the password cannot be retrieved later!");
            }

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Failed to create tenant: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Create the owner user in the tenant schema.
     */
    protected function createOwnerUser(Tenant $tenant, string $schemaName, string $email, string $password): ?string
    {
        try {
            // Switch to tenant schema
            DB::statement("SET search_path TO \"{$schemaName}\"");

            $userId = Str::uuid()->toString();
            $nameParts = explode('@', $email);
            $name = ucfirst($nameParts[0]);

            // Insert user directly using raw SQL to avoid model complications
            DB::table('users')->insert([
                'id' => $userId,
                'tenant_id' => $tenant->id,
                'first_name' => $name,
                'last_name' => 'Admin',
                'email' => $email,
                'username' => $nameParts[0],
                'password' => Hash::make($password),
                'status' => 'active',
                'language' => $tenant->locale ?? 'ar',
                'timezone' => $tenant->timezone ?? 'Africa/Cairo',
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assign super_admin role if roles table exists
            try {
                $superAdminRole = DB::table('roles')->where('name', 'super_admin')->first();
                if ($superAdminRole) {
                    DB::table('model_has_roles')->insert([
                        'role_id' => $superAdminRole->id,
                        'model_type' => 'Modules\\Auth\\Models\\User',
                        'model_id' => $userId,
                    ]);
                }
            } catch (\Exception $e) {
                // Roles table might not exist yet, skip role assignment
            }

            // Reset search path
            DB::statement("SET search_path TO public");

            return $userId;

        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            $this->warn("Could not create owner user: " . $e->getMessage());
            return null;
        }
    }
}
