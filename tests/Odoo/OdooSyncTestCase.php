<?php

namespace Tests\Odoo;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\Tenant;
use Modules\Core\Models\TenantStatus;
use Modules\Core\Services\TenantService;
use Modules\OdooIntegration\Services\Api\OdooApiFactory;
use Modules\OdooIntegration\Services\RealtimeSyncManager;
use Tests\Odoo\Support\FakeOdooApiFactory;
use Tests\Odoo\Support\FakeOdooClient;
use Tests\TestCase;
use XLinic\Framework\Core\Tenancy\TenantManager;

/**
 * Base test case for Odoo sync tests.
 *
 * Runs against the dedicated `xforce_testing` database (enforced below).
 * On first use it runs the central migrations and provisions one tenant
 * (schema `tenant_odootest`) through the real TenantService flow — module
 * migrations, seeders, default branch — so sync tests execute against the
 * same schema a production tenant gets.
 *
 * Every test starts with the tenant's HR + odoo_* tables truncated, a fresh
 * FakeOdooClient bound through the OdooApiFactory container instance, and
 * tenant/branch context initialized.
 */
abstract class OdooSyncTestCase extends TestCase
{
    protected const TENANT_SLUG = 'odootest';

    protected static bool $environmentReady = false;

    public Tenant $tenant;

    public FakeOdooClient $odoo;

    /** Tenant-schema tables wiped before each test (only those that exist). */
    protected const RESET_TABLES = [
        'odoo_sync_conflicts',
        'odoo_sync_records',
        'odoo_sync_logs',
        'odoo_field_mappings',
        'odoo_entity_mappings',
        'odoo_connections',
        'attendances',
        'attendance_logs',
        'practitioner_time_off',
        'time_off_allocations',
        'time_off_types',
        'payroll_lines',
        'payroll_runs',
        'salary_rules',
        'salary_structures',
        'salary_rule_categories',
        'staff_profiles',
        'users',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->guardAgainstWrongDatabase();
        $this->prepareEnvironmentOnce();

        $this->tenant = Tenant::query()
            ->where('slug', self::TENANT_SLUG)
            ->firstOrFail();

        $this->initializeTenantContext();
        $this->resetTenantData();

        $this->odoo = new FakeOdooClient;
        $this->app->instance(OdooApiFactory::class, new FakeOdooApiFactory($this->odoo));

        RealtimeSyncManager::flush();

        // Static per-request caches keyed by Odoo id go stale once tables
        // are truncated with RESTART IDENTITY.
        \Modules\Payroll\Models\PayrollLine::flushOdooImportCaches();
    }

    protected function tearDown(): void
    {
        RealtimeSyncManager::flush();
        app(TenantManager::class)->clearCurrentTenant();

        parent::tearDown();
    }

    /**
     * Hard stop if env overrides did not land (e.g. a cached production
     * config) — these tests truncate tables and must never see live data.
     */
    protected function guardAgainstWrongDatabase(): void
    {
        $db = config('database.connections.pgsql.database');

        if ($db !== 'xforce_testing') {
            self::fail(
                "Refusing to run Odoo sync tests against database '{$db}'. ".
                'Expected xforce_testing — check phpunit.xml env overrides and remove any cached config.'
            );
        }
    }

    protected function prepareEnvironmentOnce(): void
    {
        if (self::$environmentReady) {
            return;
        }

        if (! Schema::connection('pgsql')->hasTable('tenants')) {
            self::fail(
                'Test database is missing the public schema structure. '.
                'Restore it with: pg_dump --schema-only --schema=public xforce_master | psql xforce_testing'
            );
        }

        $exists = Tenant::query()->where('slug', self::TENANT_SLUG)->exists();

        if (! $exists) {
            $tenant = Tenant::create([
                'name' => 'Odoo Sync Test Clinic',
                'slug' => self::TENANT_SLUG,
                'status' => TenantStatus::ACTIVE,
            ]);

            // Production tenant schemas carry their own copy of the tenants
            // table (tenant-schema FKs like departments.tenant_id resolve
            // against it). Recreate that layout before provisioning.
            $schema = $tenant->database_name;
            $pgsql = DB::connection('pgsql');
            $pgsql->statement("CREATE SCHEMA IF NOT EXISTS \"{$schema}\"");
            $pgsql->statement("CREATE TABLE IF NOT EXISTS \"{$schema}\".tenants (LIKE public.tenants INCLUDING ALL)");
            $pgsql->statement(
                "INSERT INTO \"{$schema}\".tenants SELECT * FROM public.tenants WHERE id = ? ON CONFLICT DO NOTHING",
                [$tenant->id]
            );

            app(TenantService::class)->createTenantDatabase($tenant);
        } else {
            $this->resumeProvisioningIfIncomplete();
        }

        self::$environmentReady = true;
    }

    /**
     * A previous run may have died mid-provisioning (migration failure).
     * If any module migration is missing from the tenant's ledger, run
     * provisioning again — it skips migrations that already ran.
     */
    protected function resumeProvisioningIfIncomplete(): void
    {
        $tenant = Tenant::query()->where('slug', self::TENANT_SLUG)->firstOrFail();
        $schema = $tenant->database_name;

        try {
            $ran = collect(DB::connection('pgsql')->select(
                "SELECT migration FROM \"{$schema}\".migrations"
            ))->pluck('migration')->all();
        } catch (\Throwable) {
            $ran = [];
        }

        $platformOnly = [
            'create_tenants_table',
            'create_tenant_subscriptions_table',
            'create_tenant_usage_table',
            'create_tenant_modules_table',
            'create_gift_card_journal',
        ];

        $pending = collect(glob(base_path('modules/*/Database/Migrations/*.php')))
            ->map(fn (string $f) => pathinfo($f, PATHINFO_FILENAME))
            ->reject(fn (string $name) => collect($platformOnly)->contains(
                fn (string $pattern) => str_contains($name, $pattern)
            ))
            ->diff($ran);

        if ($pending->isNotEmpty()) {
            app(TenantService::class)->createTenantDatabase($tenant);
        }
    }

    /**
     * Point both the default and tenant connections at the test tenant's
     * schema — mirrors what TenantService/IdentifyTenant middleware do in
     * production ("public" stays on the path so central tables resolve).
     */
    protected function initializeTenantContext(): void
    {
        $schema = $this->tenant->database_name;

        // Configure the search_path at connection-config level so it is
        // re-applied on every (re)connect — a session-level SET alone is lost
        // whenever Laravel reconnects. Models without an explicit $connection
        // use the default pgsql connection, so both must be tenant-first.
        config([
            'database.connections.pgsql.search_path' => "{$schema},public",
            'database.connections.tenant.search_path' => "{$schema},public",
        ]);

        DB::purge('pgsql');
        DB::purge('tenant');
        DB::connection('tenant')->statement("SET search_path TO \"{$schema}\", public");
        DB::connection('pgsql')->statement("SET search_path TO \"{$schema}\", public");

        app(TenantManager::class)->setCurrentTenant($this->tenant);
        $this->app->instance('currentTenant', $this->tenant);

        $branch = DB::connection('tenant')->table('branches')->orderBy('id')->first();
        if ($branch) {
            session(['current_branch_ids' => [$branch->id]]);
        }
    }

    protected function resetTenantData(): void
    {
        $conn = DB::connection('tenant');
        $schema = $this->tenant->database_name;

        $existing = collect($conn->select(
            'SELECT table_name FROM information_schema.tables WHERE table_schema = ?',
            [$schema]
        ))->pluck('table_name')->all();

        $toTruncate = array_values(array_intersect(self::RESET_TABLES, $existing));

        if ($toTruncate === []) {
            return;
        }

        $quoted = implode(', ', array_map(
            fn (string $t) => "\"{$schema}\".\"{$t}\"",
            $toTruncate
        ));

        $conn->statement("TRUNCATE TABLE {$quoted} RESTART IDENTITY CASCADE");
    }
}
