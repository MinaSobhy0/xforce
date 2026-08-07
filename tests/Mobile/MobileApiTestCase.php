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

        foreach (['attendance_breaks', 'attendance_violations', 'attendance_type_settings', 'practitioner_schedule_assignments', 'work_schedules'] as $table) {
            if (in_array($table, $existing, true)) {
                $conn->statement("TRUNCATE TABLE \"{$schema}\".\"{$table}\" RESTART IDENTITY CASCADE");
            }
        }

        // Sanctum tokens live in the central public schema.
        DB::connection('pgsql')->statement('TRUNCATE TABLE public.personal_access_tokens RESTART IDENTITY CASCADE');

        // Direct permission/role grants are keyed on model_id. The harness
        // truncates `users` with RESTART IDENTITY, so a later test's user
        // reuses an earlier one's id and silently inherits its grants —
        // which is enough to make an authorization test pass for the wrong
        // reason. Clear the pivots so every actor starts with nothing.
        foreach (['model_has_permissions', 'model_has_roles'] as $pivot) {
            if (in_array($pivot, $existing, true)) {
                $conn->statement("TRUNCATE TABLE \"{$schema}\".\"{$pivot}\" CASCADE");
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Tenant settings persist on public.tenants across tests/runs —
        // reset the keys the suite mutates so every test starts from defaults.
        $settings = $this->tenant->settings ?? [];
        unset($settings['attendance']['multiple_check_in'], $settings['payslip_display']);
        $this->tenant->settings = $settings;
        $this->tenant->save();
        app()->instance('currentTenant', $this->tenant->fresh());
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

    /**
     * Issue a request carrying a caller-supplied bearer token rather than
     * one minted by tokenFor(). Needed by the tenant-binding tests, which
     * deliberately present tokens the normal login flow would never hand
     * out (wrong tenant stamp, or none at all).
     */
    public function apiWithRawToken(string $method, string $uri, string $plainTextToken, array $data = [])
    {
        $this->app['auth']->forgetGuards();

        $response = $this->json($method, '/api/v2/'.ltrim($uri, '/'), $data, [
            'X-Tenant-Slug' => self::TENANT_SLUG,
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$plainTextToken,
        ]);

        $this->initializeTenantContext();

        return $response;
    }

    protected function tokenFor(User $user): string
    {
        return $this->tokens[$user->id] ??= $this->issueToken($user);
    }

    /**
     * Mirror AuthController::createToken — including the tenant stamp that
     * EnsureTokenMatchesTenant requires. Tokens without it are treated as
     * cross-tenant replays and rejected with 401, so a bare createToken()
     * here would not model the real login flow.
     */
    protected function issueToken(User $user): string
    {
        $newToken = $user->createToken('mobile-test');

        $newToken->accessToken->forceFill(['tenant_id' => $this->tenant->id])->save();

        return $newToken->plainTextToken;
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

    /**
     * Assign a fixed work schedule to a staff member (Carbon dow keys,
     * 0=Sunday). Default: Sunday-Thursday working, Friday+Saturday off.
     */
    public function assignSchedule(StaffProfile $staff, array $workingDows = [0, 1, 2, 3, 4]): \Modules\Booking\Models\WorkSchedule
    {
        $weekly = [];
        foreach (range(0, 6) as $d) {
            $weekly[$d] = in_array($d, $workingDows, true)
                ? ['is_working' => true, 'start_time' => '09:00', 'end_time' => '17:00']
                : ['is_working' => false];
        }

        $schedule = \Modules\Booking\Models\WorkSchedule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'QA Schedule '.uniqid(),
            'schedule_type' => 'fixed',
            'weekly_hours' => $weekly,
            'working_days' => array_values($workingDows),
            'is_active' => true,
        ]);

        \Modules\Booking\Models\PractitionerScheduleAssignment::create([
            'tenant_id' => $this->tenant->id,
            'staff_profile_id' => $staff->id,
            'work_schedule_id' => $schedule->id,
            'is_active' => true,
            'is_primary' => true,
        ]);

        return $schedule;
    }

    /**
     * Mirror the synced Odoo xs.attendance.config into tenant settings.
     */
    public function setMultipleCheckIn(bool $enabled, string $scope = 'all', array $employeeOdooIds = []): void
    {
        $this->tenant->setSetting('attendance.multiple_check_in', [
            'enabled' => $enabled,
            'scope' => $scope,
            'employee_odoo_ids' => $employeeOdooIds,
        ]);
        $this->tenant->save();
        app()->instance('currentTenant', $this->tenant->fresh());
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
