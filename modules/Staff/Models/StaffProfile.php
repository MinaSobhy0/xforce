<?php

namespace Modules\Staff\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Auth\Models\User;
use Modules\Booking\Models\PractitionerScheduleAssignment;
use Modules\Booking\Models\WorkSchedule;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\Payroll\Models\EmployeeSalaryComponent;
use Modules\Payroll\Models\EmployeeSalaryStructure;
use Spatie\Translatable\HasTranslations;
use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use XLinic\Framework\Core\Model\Traits\HasSequence;

class StaffProfile extends BaseModel
{
    use HasActivity;
    use HasSequence;
    use HasTranslations;

    /**
     * The column that stores the sequence number.
     */
    protected string $sequenceColumn = 'employee_number';

    /**
     * The sequence code used for generating numbers.
     */
    protected string $sequenceCode = 'EMP';

    /**
     * The sequence prefix (e.g., EMP-000001).
     */
    protected string $sequencePrefix = 'EMP';

    /**
     * The sequence format.
     */
    protected string $sequenceFormat = '{prefix}-{number:6}';

    protected $table = 'staff_profiles';

    public array $translatable = ['bio', 'specializations'];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'branch_id',
        'department_id',
        'commission_plan_id',
        'allowed_check_in_methods',
        'allowed_geofence_locations',
        'employee_number',
        'job_title',
        'bio',
        'specializations',
        'commission_type', // Deprecated - use commission_plan_id
        'commission_percentage', // Deprecated - use commission_plan_id
        'base_salary_minor',
        'hire_date',
        'contract_end_date',
        'is_active',
        'hr_responsible_user_id',
        'time_off_approver_user_id',
        'attendance_approver_user_id',
        'odoo_id',
        'odoo_synced_at',
    ];

    protected $casts = [
        'bio' => 'array',
        'specializations' => 'array',
        'allowed_check_in_methods' => 'array',
        'allowed_geofence_locations' => 'array',
        'commission_percentage' => 'decimal:2',
        'base_salary_minor' => 'integer',
        'hire_date' => 'date',
        'contract_end_date' => 'date',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'commission_type' => self::COMMISSION_PERCENTAGE,
        'commission_percentage' => 10.00,
        'base_salary_minor' => 0,
        'is_active' => true,
    ];

    // Commission types
    public const COMMISSION_FLAT = 'flat';

    public const COMMISSION_PERCENTAGE = 'percentage';

    public const COMMISSION_TIERED = 'tiered';

    public const COMMISSION_TYPES = [
        self::COMMISSION_FLAT => 'Flat Amount',
        self::COMMISSION_PERCENTAGE => 'Percentage',
        self::COMMISSION_TIERED => 'Tiered',
    ];

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the branch.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the department.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The HR person responsible for this employee (local-only — not sourced from Odoo).
     */
    public function hrResponsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_responsible_user_id');
    }

    /**
     * The user who approves this employee's time-off requests.
     * Mirrors Odoo `hr.employee.leave_manager_id`.
     */
    public function timeOffApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'time_off_approver_user_id');
    }

    /**
     * The user who approves this employee's attendance.
     * Mirrors Odoo `hr.employee.attendance_manager_id`.
     */
    public function attendanceApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attendance_approver_user_id');
    }

    /**
     * Get the assigned commission plan.
     */
    public function commissionPlan(): BelongsTo
    {
        return $this->belongsTo(CommissionPlan::class, 'commission_plan_id');
    }

    /**
     * Get commission rules (deprecated - use commissionPlan instead).
     *
     * @deprecated Use commissionPlan()->serviceRules() instead
     */
    public function commissionRules(): HasMany
    {
        return $this->hasMany(StaffCommission::class);
    }

    /**
     * Get commission records.
     */
    public function commissionRecords(): HasMany
    {
        return $this->hasMany(StaffCommissionRecord::class);
    }

    /**
     * Get schedule assignments for this staff member.
     */
    public function scheduleAssignments(): HasMany
    {
        return $this->hasMany(PractitionerScheduleAssignment::class, 'staff_profile_id');
    }

    /**
     * Get work schedules for this staff member.
     */
    public function workSchedules(): BelongsToMany
    {
        return $this->belongsToMany(WorkSchedule::class, 'practitioner_schedule_assignments', 'staff_profile_id', 'work_schedule_id')
            ->withPivot(['effective_from', 'effective_until', 'day_overrides', 'is_primary', 'is_active', 'notes'])
            ->withTimestamps();
    }

    /**
     * Get active work schedules for this staff member.
     */
    public function activeWorkSchedules(): BelongsToMany
    {
        return $this->workSchedules()
            ->wherePivot('is_active', true)
            ->where(function ($query) {
                $query->whereNull('practitioner_schedule_assignments.effective_from')
                    ->orWhere('practitioner_schedule_assignments.effective_from', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('practitioner_schedule_assignments.effective_until')
                    ->orWhere('practitioner_schedule_assignments.effective_until', '>=', now());
            });
    }

    /**
     * Get the primary work schedule for this staff member.
     */
    public function primaryWorkSchedule(): ?WorkSchedule
    {
        return $this->activeWorkSchedules()
            ->wherePivot('is_primary', true)
            ->first();
    }

    /**
     * Get all salary structure assignments.
     */
    public function salaryStructures(): HasMany
    {
        return $this->hasMany(EmployeeSalaryStructure::class, 'staff_profile_id');
    }

    /**
     * Get the current salary structure assignment.
     */
    public function currentSalaryStructure(): HasOne
    {
        return $this->hasOne(EmployeeSalaryStructure::class, 'staff_profile_id')
            ->where('is_current', true);
    }

    /**
     * Get all salary components for this employee.
     */
    public function salaryComponents(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class, 'staff_profile_id');
    }

    /**
     * Get active and effective salary components.
     */
    public function activeSalaryComponents(): HasMany
    {
        return $this->salaryComponents()->active()->effective();
    }

    /**
     * Scope to active staff only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get total earnings for a period.
     */
    public function getEarningsForPeriod(string $startDate, string $endDate): int
    {
        return $this->commissionRecords()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', StaffCommissionRecord::STATUS_APPROVED)
            ->sum('amount_minor');
    }

    /**
     * Get pending earnings.
     */
    public function getPendingEarningsAttribute(): int
    {
        return $this->commissionRecords()
            ->where('status', StaffCommissionRecord::STATUS_PENDING)
            ->sum('amount_minor');
    }

    /**
     * Get total paid earnings.
     */
    public function getTotalPaidAttribute(): int
    {
        return $this->commissionRecords()
            ->where('status', StaffCommissionRecord::STATUS_PAID)
            ->sum('amount_minor');
    }

    /**
     * Get base salary in major units.
     */
    public function getBaseSalaryAttribute(): float
    {
        return $this->base_salary_minor / 100;
    }

    /**
     * Calculate commission for an amount.
     *
     * @param  int  $amountMinor  Revenue amount in minor units
     * @param  string|null  $serviceId  Optional service ID for specific rules
     * @param  string|null  $categoryId  Optional category ID for fallback rules
     * @return int Commission amount in minor units
     */
    public function calculateCommission(int $amountMinor, ?string $serviceId = null, ?string $categoryId = null): int
    {
        // Use commission plan if assigned
        if ($this->commission_plan_id && $this->commissionPlan) {
            return $this->commissionPlan->calculateCommission($amountMinor, $serviceId, $categoryId);
        }

        // Fallback to legacy commission settings (deprecated)
        return $this->calculateLegacyCommission($amountMinor, $serviceId);
    }

    /**
     * Calculate commission using legacy per-staff settings.
     *
     * @deprecated This method is for backward compatibility only.
     */
    protected function calculateLegacyCommission(int $amountMinor, ?string $serviceId = null): int
    {
        // Check if there's a specific commission rule for this service
        if ($serviceId) {
            $rule = $this->commissionRules()
                ->where('service_id', $serviceId)
                ->where('is_active', true)
                ->first();

            if ($rule) {
                return $rule->calculateAmount($amountMinor);
            }
        }

        // Use default commission settings
        switch ($this->commission_type) {
            case self::COMMISSION_FLAT:
                return (int) ($this->commission_percentage * 100);

            case self::COMMISSION_PERCENTAGE:
                return (int) ($amountMinor * $this->commission_percentage / 100);

            case self::COMMISSION_TIERED:
                $tierRule = $this->commissionRules()
                    ->where('commission_type', 'tiered')
                    ->where('tier_from_minor', '<=', $amountMinor)
                    ->where(function ($q) use ($amountMinor) {
                        $q->whereNull('tier_to_minor')
                            ->orWhere('tier_to_minor', '>=', $amountMinor);
                    })
                    ->where('is_active', true)
                    ->first();

                if ($tierRule) {
                    return $tierRule->calculateAmount($amountMinor);
                }

                return (int) ($amountMinor * $this->commission_percentage / 100);

            default:
                return 0;
        }
    }

    /**
     * Check if staff has a commission plan assigned.
     */
    public function hasCommissionPlan(): bool
    {
        return $this->commission_plan_id !== null;
    }

    // ==========================================
    // Attendance Settings Methods
    // ==========================================

    /**
     * Available check-in method types.
     */
    public const CHECK_IN_METHODS = [
        'manual' => 'Manual',
        'geofence' => 'Geofence (Location)',
        'qr_static' => 'Static QR Code',
        'qr_dynamic' => 'Dynamic QR Code',
        'biometric' => 'Biometric',
    ];

    /**
     * Check if a specific check-in method is allowed for this staff.
     * If allowed_check_in_methods is null, all methods are allowed.
     */
    public function isCheckInMethodAllowed(string $method): bool
    {
        $allowedMethods = $this->allowed_check_in_methods;

        // Null means all methods allowed
        if ($allowedMethods === null) {
            return true;
        }

        return in_array($method, $allowedMethods);
    }

    /**
     * Get the list of allowed check-in methods.
     * If null, returns all available methods.
     */
    public function getAllowedCheckInMethods(): array
    {
        return $this->allowed_check_in_methods ?? array_keys(self::CHECK_IN_METHODS);
    }

    /**
     * Check if a specific geofence location (branch) is allowed.
     * If allowed_geofence_locations is null, all locations are allowed.
     */
    public function isGeofenceLocationAllowed(int|string $branchId): bool
    {
        $allowedLocations = $this->allowed_geofence_locations;

        // Null means all locations allowed
        if ($allowedLocations === null) {
            return true;
        }

        return in_array((int) $branchId, array_map('intval', $allowedLocations));
    }

    /**
     * Get the list of allowed geofence location IDs (branch IDs).
     * Returns null if all locations are allowed.
     */
    public function getAllowedGeofenceLocations(): ?array
    {
        return $this->allowed_geofence_locations;
    }

    /**
     * Check if geofence check-in is required (i.e., only geofence is allowed).
     */
    public function requiresGeofenceCheckIn(): bool
    {
        $allowedMethods = $this->allowed_check_in_methods;

        if ($allowedMethods === null) {
            return false;
        }

        return count($allowedMethods) === 1 && in_array('geofence', $allowedMethods);
    }

    /**
     * Set allowed check-in methods.
     */
    public function setAllowedCheckInMethods(?array $methods): self
    {
        $this->allowed_check_in_methods = $methods;

        return $this;
    }

    /**
     * Set allowed geofence locations.
     */
    public function setAllowedGeofenceLocations(?array $branchIds): self
    {
        $this->allowed_geofence_locations = $branchIds ? array_map('intval', $branchIds) : null;

        return $this;
    }

    /**
     * Post-transform hook on Odoo import.
     *
     * staff_profiles.user_id is NOT NULL, but many Odoo employees don't have
     * a linked res.users (labor-force employees who don't log into Odoo) — or
     * the linked user wasn't synced yet. Either way the relation transformer
     * would skip the employee and we'd never get them locally.
     *
     * Here we ensure a local User exists: if the relation resolved, fine;
     * otherwise create a placeholder User from the Odoo employee's own data.
     * The placeholder is non-loginable (random password) until an admin
     * promotes it.
     */
    public static function applyOdooImport(array $data, $mapping = null, ?array $odooData = null): array
    {
        // Odoo returns "" for empty string fields, so ?? alone doesn't fall through.
        $nonEmpty = static fn ($v) => is_string($v) && trim($v) !== '' ? $v : null;
        $rawEmail = $nonEmpty($odooData['work_email'] ?? null);
        $rawName = $nonEmpty($odooData['name'] ?? null);
        $rawPhone = $nonEmpty($odooData['mobile_phone'] ?? null) ?: $nonEmpty($odooData['work_phone'] ?? null);
        $odooEmployeeId = $odooData['id'] ?? null;
        $tenantId = $data['tenant_id'] ?? $mapping?->tenant_id ?? current_tenant_id();

        // Branch 0 — full sync of an already-linked staff: reuse the existing
        // user_id instead of trying to create a new placeholder. This guards
        // against the duplicate-email error when the relation transformer
        // returns null because the Odoo employee has no user_id but we have a
        // local placeholder from a prior sync.
        if (empty($data['user_id']) && $odooEmployeeId) {
            $existingUserId = self::query()
                ->where('odoo_id', $odooEmployeeId)
                ->value('user_id');
            if ($existingUserId) {
                $data['user_id'] = $existingUserId;
            }
        }

        // Branch 1 — relation already resolved: refresh the linked User's contact
        // fields from Odoo so updates flow through on each delta sync.
        if (! empty($data['user_id'])) {
            self::syncUserContact($data['user_id'], $rawName, $rawEmail, $rawPhone);

            return $data;
        }

        // Branch 2 — no local user. Try to find one by work_email before creating.
        if ($rawEmail) {
            $existingUserId = User::query()->where('email', $rawEmail)->value('id');
            if ($existingUserId) {
                $data['user_id'] = $existingUserId;
                self::syncUserContact($existingUserId, $rawName, $rawEmail, $rawPhone);

                return $data;
            }
        }

        // Branch 3 — fetch name lazily if the field mapping doesn't include it.
        if (! $rawName && $odooEmployeeId && $mapping) {
            try {
                $connection = $mapping->connection;
                if ($connection) {
                    $client = app(\Modules\OdooIntegration\Services\Api\OdooApiFactory::class)->make($connection);
                    $client->authenticate();
                    $rows = $client->read('hr.employee', [(int) $odooEmployeeId], ['name']);
                    $rawName = $rows[0]['name'] ?? null;
                }
            } catch (\Throwable $e) {
                // Fall through to defaults — placeholder still works.
            }
        }

        [$first, $last] = self::splitName($rawName, $odooEmployeeId);
        $email = $rawEmail ?: 'employee-'.($odooEmployeeId ?? uniqid()).'@local.invalid';

        $user = User::create([
            'tenant_id' => $tenantId,
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'phone' => $rawPhone ?: null,
            'password' => bcrypt(\Illuminate\Support\Str::random(40)),
        ]);

        $data['user_id'] = $user->id;

        return $data;
    }

    /**
     * Refresh User contact fields from Odoo without overwriting non-placeholder data.
     * Only fills empty columns — never clobbers values the admin entered locally.
     */
    protected static function syncUserContact(int $userId, ?string $rawName, ?string $rawEmail, ?string $rawPhone): void
    {
        $user = User::find($userId);
        if (! $user) {
            return;
        }

        $updates = [];

        if ($rawPhone && empty($user->phone)) {
            $updates['phone'] = $rawPhone;
        }

        // Placeholder emails generated by this hook are safe to overwrite.
        if ($rawEmail && (empty($user->email) || str_ends_with((string) $user->email, '@local.invalid'))) {
            $updates['email'] = $rawEmail;
        }

        // Stub names ('Employee #123') and empty names are safe to overwrite.
        if (is_string($rawName) && trim($rawName) !== '' &&
            ($user->first_name === 'Employee' || empty($user->first_name))) {
            [$first, $last] = self::splitName($rawName, null);
            $updates['first_name'] = $first;
            $updates['last_name'] = $last;
        }

        if (! empty($updates)) {
            $user->update($updates);
        }
    }

    /**
     * Split a "First Last Middle ..." string into ($first, $last). Returns a
     * sentinel based on the Odoo id when the input is empty.
     *
     * @return array{0: string, 1: string}
     */
    protected static function splitName(?string $rawName, ?int $odooEmployeeId): array
    {
        if (is_string($rawName) && trim($rawName) !== '') {
            $parts = preg_split('/\s+/', trim($rawName), 2);

            return [$parts[0] ?? 'Employee', $parts[1] ?? ''];
        }

        return ['Employee', $odooEmployeeId ? "#{$odooEmployeeId}" : 'Unknown'];
    }
}
