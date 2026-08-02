<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\Models\User;
use Modules\Booking\Models\WorkSchedule;
use Modules\Core\Models\Branch;
use Modules\Staff\Models\StaffProfile;
use XLinic\Framework\Core\Model\BaseModel;

class Attendance extends BaseModel
{
    use SoftDeletes;

    protected $table = 'attendances';

    protected $fillable = [
        'tenant_id',
        'staff_profile_id',
        'branch_id',
        'working_schedule_id',
        'attendance_date',
        'check_in_time',
        'check_out_time',
        'attendance_type',
        'status',
        'working_hours',
        'late_hours',
        'early_hours',
        'overtime_hours',
        'late_reason',
        'early_checkout_reason',
        'notes',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
        'odoo_id',
        'odoo_synced_at',
        'check_in_latitude',
        'check_in_longitude',
        'check_out_latitude',
        'check_out_longitude',
        'is_offline_entry',
        'location_verified',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'check_in_time' => 'datetime:H:i:s',
        'check_out_time' => 'datetime:H:i:s',
        'working_hours' => 'decimal:2',
        'late_hours' => 'decimal:2',
        'early_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'check_in_latitude' => 'decimal:7',
        'check_in_longitude' => 'decimal:7',
        'check_out_latitude' => 'decimal:7',
        'check_out_longitude' => 'decimal:7',
        'is_offline_entry' => 'boolean',
        'location_verified' => 'boolean',
    ];

    protected $attributes = [
        'attendance_type' => self::TYPE_MANUAL,
        'status' => self::STATUS_PRESENT,
        'working_hours' => 0,
        'late_hours' => 0,
        'early_hours' => 0,
        'overtime_hours' => 0,
    ];

    // Attendance Types (check-in methods)
    public const TYPE_MANUAL = 'manual';

    public const TYPE_GEOFENCE = 'geofence';

    public const TYPE_QR_STATIC = 'qr_static';

    public const TYPE_QR_DYNAMIC = 'qr_dynamic';

    public const TYPE_BIOMETRIC = 'biometric';

    public const TYPES = [
        self::TYPE_MANUAL => 'Manual',
        self::TYPE_GEOFENCE => 'Geofence',
        self::TYPE_QR_STATIC => 'Static QR',
        self::TYPE_QR_DYNAMIC => 'Dynamic QR',
        self::TYPE_BIOMETRIC => 'Biometric',
    ];

    // Types that can be configured via settings. Manual is special: with no
    // stored row it defaults to ENABLED (it is the historical baseline
    // method) — see AttendanceTypeSetting::isManualEnabled().
    public const CONFIGURABLE_TYPES = [
        self::TYPE_MANUAL,
        self::TYPE_GEOFENCE,
        self::TYPE_QR_STATIC,
        self::TYPE_QR_DYNAMIC,
        self::TYPE_BIOMETRIC,
    ];

    public const TYPE_COLORS = [
        self::TYPE_MANUAL => 'gray',
        self::TYPE_GEOFENCE => 'info',
        self::TYPE_QR_STATIC => 'primary',
        self::TYPE_QR_DYNAMIC => 'primary',
        self::TYPE_BIOMETRIC => 'success',
    ];

    // Attendance Status
    public const STATUS_PRESENT = 'present';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_HALF_DAY = 'half_day';

    public const STATUS_LEAVE = 'leave';

    public const STATUSES = [
        self::STATUS_PRESENT => 'Present',
        self::STATUS_ABSENT => 'Absent',
        self::STATUS_HALF_DAY => 'Half Day',
        self::STATUS_LEAVE => 'On Leave',
    ];

    public const STATUS_COLORS = [
        self::STATUS_PRESENT => 'success',
        self::STATUS_ABSENT => 'danger',
        self::STATUS_HALF_DAY => 'warning',
        self::STATUS_LEAVE => 'info',
    ];

    /**
     * Whether this staff member may punch multiple check-in/out pairs per
     * day. Mirrors Odoo's xs.attendance.config (synced into tenant settings
     * by SyncAttendanceConfigFromOdoo): a global switch, optionally scoped
     * to specific employees/departments (departments pre-expanded to
     * employee odoo ids at sync time). Default: single pair per day.
     */
    public static function multiplePunchesAllowedFor($staffProfile): bool
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $config = $tenant?->getSetting('attendance.multiple_check_in');

        if (! is_array($config) || empty($config['enabled'])) {
            return false;
        }

        if (($config['scope'] ?? 'all') === 'all') {
            return true;
        }

        return $staffProfile && $staffProfile->odoo_id
            && in_array((int) $staffProfile->odoo_id, array_map('intval', (array) ($config['employee_odoo_ids'] ?? [])), true);
    }

    /**
     * Odoo hr.attendance carries full datetimes in check_in/check_out; the
     * local schema splits them into attendance_date (DATE) + TIME columns.
     * The datetime transformer has already converted to the tenant timezone
     * by the time this hook runs.
     */
    public static function applyOdooImport(array $data, $mapping = null, ?array $odooData = null): array
    {
        foreach (['check_in_time', 'check_out_time'] as $field) {
            $value = $data[$field] ?? null;

            if ($value === false) {
                // Odoo's false-for-empty (open attendance has no check_out).
                $data[$field] = null;

                continue;
            }

            if (is_string($value) && strlen($value) > 8) {
                $dt = \Carbon\Carbon::parse($value);
                if ($field === 'check_in_time') {
                    $data['attendance_date'] = $dt->toDateString();
                }
                $data[$field] = $dt->format('H:i:s');
            }
        }

        return $data;
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the staff profile.
     */
    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'staff_profile_id');
    }

    /**
     * Get the branch.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Get the working schedule (uses Booking module's WorkSchedule).
     */
    public function workingSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'working_schedule_id');
    }

    /**
     * Get the attendance logs.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    /**
     * Get the attendance breaks.
     */
    public function breaks(): HasMany
    {
        return $this->hasMany(AttendanceBreak::class);
    }

    /**
     * Get the violations.
     */
    public function violations(): HasMany
    {
        return $this->hasMany(AttendanceViolation::class);
    }

    /**
     * Get the approver.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the creator.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the updater.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope for specific date.
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('attendance_date', $date);
    }

    /**
     * Scope for date range.
     */
    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('attendance_date', [$startDate, $endDate]);
    }

    /**
     * Scope for specific staff.
     */
    public function scopeForStaff($query, $staffProfileId)
    {
        return $query->where('staff_profile_id', $staffProfileId);
    }

    /**
     * Scope for present status.
     */
    public function scopePresent($query)
    {
        return $query->where('status', self::STATUS_PRESENT);
    }

    /**
     * Scope for absent status.
     */
    public function scopeAbsent($query)
    {
        return $query->where('status', self::STATUS_ABSENT);
    }

    /**
     * Scope for today.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('attendance_date', today());
    }

    /**
     * Scope for this month.
     */
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('attendance_date', now()->month)
            ->whereYear('attendance_date', now()->year);
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if already checked out.
     */
    public function isCheckedOut(): bool
    {
        return $this->check_out_time !== null;
    }

    /**
     * Check if checked in.
     */
    public function isCheckedIn(): bool
    {
        return $this->check_in_time !== null;
    }

    /**
     * Get the latest log.
     */
    public function latestLog(): ?AttendanceLog
    {
        return $this->logs()->latest()->first();
    }

    /**
     * Calculate working hours from check-in and check-out times.
     */
    public function calculateWorkingHours(): float
    {
        if (! $this->check_in_time || ! $this->check_out_time) {
            return 0;
        }

        $checkIn = \Carbon\Carbon::parse($this->check_in_time);
        $checkOut = \Carbon\Carbon::parse($this->check_out_time);

        // Subtract break time
        $totalBreakMinutes = $this->breaks()->sum('duration_minutes');

        $totalMinutes = $checkOut->diffInMinutes($checkIn) - $totalBreakMinutes;

        return round($totalMinutes / 60, 2);
    }

    /**
     * Update working hours.
     */
    public function updateWorkingHours(): void
    {
        $this->working_hours = $this->calculateWorkingHours();
        $this->save();
    }

    /**
     * Check out the attendance.
     */
    public function checkOut($time = null, ?string $userId = null): bool
    {
        if ($this->isCheckedOut()) {
            return false;
        }

        $this->check_out_time = $time ?? now()->format('H:i:s');
        $this->updated_by = $userId ?? auth()->id();
        $this->updateWorkingHours();

        return $this->save();
    }

    /**
     * Approve the attendance.
     */
    public function approve(?string $userId = null): bool
    {
        $this->approved_by = $userId ?? auth()->id();
        $this->approved_at = now();

        return $this->save();
    }

    /**
     * Check if approved.
     */
    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * Get formatted time range.
     */
    public function getTimeRangeAttribute(): string
    {
        $checkIn = $this->check_in_time ? \Carbon\Carbon::parse($this->check_in_time)->format('h:i A') : '--:--';
        $checkOut = $this->check_out_time ? \Carbon\Carbon::parse($this->check_out_time)->format('h:i A') : '--:--';

        return "{$checkIn} - {$checkOut}";
    }

    /**
     * Get violations count.
     */
    public function getViolationsCountAttribute(): int
    {
        return $this->violations()->count();
    }

    /**
     * Check if has violations.
     */
    public function hasViolations(): bool
    {
        return $this->violations()->exists();
    }
}
