<?php

namespace Tests\Mobile;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Attendance\Models\Attendance;
use Modules\Attendance\Models\AttendanceTypeSetting;
use Modules\Auth\Models\User;
use Modules\Booking\Models\TimeOffAllocation;
use Modules\Booking\Models\TimeOffType;
use Modules\Staff\Models\StaffProfile;
use Tests\Odoo\OdooSyncTestCase;
use Tests\Odoo\Support\OdooScenario;

/**
 * Base for mobile API tests.
 *
 * Reuses the Odoo sync harness (isolated xforce_testing DB, provisioned
 * tenant_odootest, FakeOdooClient) and adds HTTP-level helpers: staff users
 * with Sanctum tokens, X-Tenant-Slug headers, geofence fixtures, and
 * Odoo-synced time-off fixtures so "balance mirrors Odoo exactly" is tested
 * through the real import pipeline, not hand-inserted rows.
 */
abstract class MobileApiTestCase extends OdooSyncTestCase
{
    /** @var array<int, string> token cache per user id */
    private array $tokens = [];

    private ?\Modules\OdooIntegration\Models\OdooConnection $odooConnection = null;

    /** @var array<string, \Modules\OdooIntegration\Models\OdooEntityMapping> */
    private array $entityMappings = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tokens = [];
        $this->odooConnection = null;
        $this->entityMappings = [];

        // Tables the mobile suite touches beyond the sync harness's list.
        $conn = DB::connection('tenant');
        $schema = $this->tenant->database_name;
        $existing = collect($conn->select(
            'SELECT table_name FROM information_schema.tables WHERE table_schema = ?',
            [$schema]
        ))->pluck('table_name')->all();

        foreach (['attendance_breaks', 'attendance_violations', 'attendance_type_settings'] as $table) {
            if (in_array($table, $existing, true)) {
                $conn->statement("TRUNCATE TABLE \"{$schema}\".\"{$table}\" RESTART IDENTITY CASCADE");
            }
        }

        // Sanctum tokens live in the central public schema.
        DB::connection('pgsql')->statement('TRUNCATE TABLE public.personal_access_tokens RESTART IDENTITY CASCADE');
    }

    // ------------------------------------------------------------------
    //  HTTP
    // ------------------------------------------------------------------

    public function api(string $method, string $uri, array $data = [], ?User $as = null)
    {
        $headers = [
            'X-Tenant-Slug' => self::TENANT_SLUG,
            'Accept' => 'application/json',
        ];

        if ($as) {
            $headers['Authorization'] = 'Bearer '.$this->tokenFor($as);
        }

        // Guards cache the resolved user for the process — flush so each
        // simulated request authenticates from its own bearer token.
        $this->app['auth']->forgetGuards();

        $response = $this->json($method, '/api/v2/'.ltrim($uri, '/'), $data, $headers);

        // The request cycle leaves the shared test connections pointing at
        // altered search_paths (middleware SET + reconnects). Restore the
        // tenant-first context so model assertions after an api() call see
        // the tenant schema again.
        $this->initializeTenantContext();

        return $response;
    }

    protected function tokenFor(User $user): string
    {
        return $this->tokens[$user->id] ??= $user->createToken('mobile-test')->plainTextToken;
    }

    // ------------------------------------------------------------------
    //  Actors
    // ------------------------------------------------------------------

    /**
     * Create a login-able staff member: User + StaffProfile on the default
     * branch. Returns [User, StaffProfile].
     */
    public function createStaffUser(array $profileAttrs = [], array $userAttrs = []): array
    {
        static $seq = 0;
        $seq++;

        $user = User::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Staff',
            'last_name' => "Member{$seq}",
            'email' => "staff{$seq}.".uniqid().'@mobiletest.local',
            'password' => Hash::make('secret-password'),
        ], $userAttrs));

        $branchId = DB::connection('tenant')->table('branches')->orderBy('id')->value('id');

        $profile = StaffProfile::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'branch_id' => $branchId,
            'job_title' => 'Therapist',
            'is_active' => true,
            // Production default is geofence-only (StaffProfile::booted);
            // the suite exercises every method, so widen it here.
            'allowed_check_in_methods' => ['manual', 'geofence', 'qr_static', 'qr_dynamic', 'biometric'],
        ], $profileAttrs));

        return [$user, $profile->fresh()];
    }

    // ------------------------------------------------------------------
    //  Odoo-synced time-off fixtures
    // ------------------------------------------------------------------

    /**
     * The scenario connection and entity mappings are unique-keyed, so they
     * are created once per test and reused across fixture helpers.
     */
    public function odooConnection(): \Modules\OdooIntegration\Models\OdooConnection
    {
        return $this->odooConnection ??= OdooScenario::connection();
    }

    public function entityMapping(string $scenarioMethod): \Modules\OdooIntegration\Models\OdooEntityMapping
    {
        return $this->entityMappings[$scenarioMethod] ??= OdooScenario::{$scenarioMethod}($this->odooConnection());
    }

    /**
     * Import a leave type from the fake Odoo through the real sync pipeline.
     */
    public function syncLeaveType(array $overrides = []): TimeOffType
    {
        $mapping = $this->entityMapping('timeOffTypes');

        $odooId = $this->odoo->seed('hr.leave.type', array_merge([
            'name' => 'Annual Leave',
            'code' => 'ANNUAL',
            'request_unit' => 'day',
            'leave_validation_type' => 'hr',
            'active' => true,
        ], $overrides));

        runOdooSync($mapping);

        return TimeOffType::where('odoo_id', $odooId)->firstOrFail();
    }

    /**
     * Give the staff profile an Odoo identity and import an allocation for
     * it through the real sync pipeline.
     */
    public function syncAllocation(StaffProfile $staff, TimeOffType $type, array $overrides = []): TimeOffAllocation
    {
        if (! $staff->odoo_id) {
            $staff->update(['odoo_id' => 90000 + $staff->id]);
        }

        $mapping = $this->entityMapping('timeOffAllocations');

        $odooId = $this->odoo->seed('hr.leave.allocation', array_merge([
            'employee_id' => [$staff->odoo_id, 'Employee'],
            'holiday_status_id' => [$type->odoo_id, $type->code],
            'number_of_days' => 10.0,
            'leaves_taken' => 0.0,
            'date_from' => now()->startOfYear()->toDateString(),
            'date_to' => now()->endOfYear()->toDateString(),
            'state' => 'validate',
        ], $overrides));

        runOdooSync($mapping);

        return TimeOffAllocation::where('odoo_id', $odooId)->firstOrFail();
    }

    // ------------------------------------------------------------------
    //  Attendance fixtures
    // ------------------------------------------------------------------

    /** Clinic reference point used by geofence tests (Cairo downtown). */
    protected const GEO_LAT = 30.0444;

    protected const GEO_LNG = 31.2357;

    /** ~2km away — safely outside any radius the tests configure. */
    protected const FAR_LAT = 30.0625;

    protected const FAR_LNG = 31.2357;

    public function enableGeofence(int $radiusMeters = 150): void
    {
        AttendanceTypeSetting::create([
            'tenant_id' => $this->tenant->id,
            'type' => Attendance::TYPE_GEOFENCE,
            'is_enabled' => true,
            'settings' => [
                'radius_meters' => $radiusMeters,
                'locations' => [[
                    'name' => 'Main Clinic',
                    'lat' => self::GEO_LAT,
                    'lng' => self::GEO_LNG,
                    'radius' => $radiusMeters,
                ]],
            ],
        ]);
    }
}
