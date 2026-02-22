<?php

namespace Modules\Attendance\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\Models\User;
use Modules\Core\Models\Branch;
use XLinic\Framework\Core\Model\BaseModel;

class WorkingSchedule extends BaseModel
{
    use SoftDeletes;

    protected $table = 'working_schedules';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'code',
        'description',
        'type',
        // Fixed Schedule
        'start_time',
        'end_time',
        'hours_per_day',
        'hours_per_week',
        // Grace Periods
        'grace_period_late_minutes',
        'grace_period_early_minutes',
        // Flexible Schedule
        'is_flexible',
        'flexible_start_from',
        'flexible_start_to',
        'flexible_end_from',
        'flexible_end_to',
        'flexible_min_hours_per_day',
        'flexible_max_hours_per_day',
        // Core Hours
        'core_hours_required',
        'core_hours_start',
        'core_hours_end',
        // Working Days
        'working_days',
        'days_per_week',
        // Break Settings
        'has_break',
        'break_duration_minutes',
        'break_start',
        'break_end',
        'flexible_break',
        // Overtime
        'allow_overtime',
        'max_overtime_per_day',
        'max_overtime_per_week',
        // Shift Rotation
        'is_rotating_shift',
        'rotation_cycle_days',
        'shift_pattern',
        // Remote/Hybrid
        'is_remote',
        'is_hybrid',
        'remote_days_per_week',
        'remote_days',
        // Status
        'status',
        'is_default',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
        'hours_per_day' => 'decimal:2',
        'hours_per_week' => 'decimal:2',
        'grace_period_late_minutes' => 'integer',
        'grace_period_early_minutes' => 'integer',
        'is_flexible' => 'boolean',
        'flexible_start_from' => 'datetime:H:i:s',
        'flexible_start_to' => 'datetime:H:i:s',
        'flexible_end_from' => 'datetime:H:i:s',
        'flexible_end_to' => 'datetime:H:i:s',
        'flexible_min_hours_per_day' => 'decimal:2',
        'flexible_max_hours_per_day' => 'decimal:2',
        'core_hours_required' => 'boolean',
        'core_hours_start' => 'datetime:H:i:s',
        'core_hours_end' => 'datetime:H:i:s',
        'working_days' => 'array',
        'days_per_week' => 'decimal:1',
        'has_break' => 'boolean',
        'break_duration_minutes' => 'integer',
        'break_start' => 'datetime:H:i:s',
        'break_end' => 'datetime:H:i:s',
        'flexible_break' => 'boolean',
        'allow_overtime' => 'boolean',
        'max_overtime_per_day' => 'decimal:2',
        'max_overtime_per_week' => 'decimal:2',
        'is_rotating_shift' => 'boolean',
        'rotation_cycle_days' => 'integer',
        'shift_pattern' => 'array',
        'is_remote' => 'boolean',
        'is_hybrid' => 'boolean',
        'remote_days_per_week' => 'integer',
        'remote_days' => 'array',
        'is_default' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'type' => self::TYPE_FIXED,
        'hours_per_day' => 8.00,
        'hours_per_week' => 40.00,
        'grace_period_late_minutes' => 0,
        'grace_period_early_minutes' => 0,
        'is_flexible' => false,
        'core_hours_required' => false,
        'days_per_week' => 5.0,
        'has_break' => true,
        'break_duration_minutes' => 60,
        'flexible_break' => false,
        'allow_overtime' => true,
        'is_rotating_shift' => false,
        'is_remote' => false,
        'is_hybrid' => false,
        'status' => self::STATUS_ACTIVE,
        'is_default' => false,
    ];

    // Schedule Types
    public const TYPE_FIXED = 'fixed';
    public const TYPE_FLEXIBLE = 'flexible';
    public const TYPE_SHIFT = 'shift';
    public const TYPE_COMPRESSED = 'compressed';
    public const TYPE_REMOTE = 'remote';

    public const TYPES = [
        self::TYPE_FIXED => 'Fixed Hours',
        self::TYPE_FLEXIBLE => 'Flexible Hours',
        self::TYPE_SHIFT => 'Shift Work',
        self::TYPE_COMPRESSED => 'Compressed Week',
        self::TYPE_REMOTE => 'Remote',
    ];

    public const TYPE_COLORS = [
        self::TYPE_FIXED => 'primary',
        self::TYPE_FLEXIBLE => 'info',
        self::TYPE_SHIFT => 'warning',
        self::TYPE_COMPRESSED => 'success',
        self::TYPE_REMOTE => 'gray',
    ];

    // Status
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_INACTIVE => 'Inactive',
    ];

    public const STATUS_COLORS = [
        self::STATUS_ACTIVE => 'success',
        self::STATUS_INACTIVE => 'gray',
    ];

    // Days of Week
    public const DAY_SUNDAY = 0;
    public const DAY_MONDAY = 1;
    public const DAY_TUESDAY = 2;
    public const DAY_WEDNESDAY = 3;
    public const DAY_THURSDAY = 4;
    public const DAY_FRIDAY = 5;
    public const DAY_SATURDAY = 6;

    public const DAYS = [
        self::DAY_SUNDAY => 'Sunday',
        self::DAY_MONDAY => 'Monday',
        self::DAY_TUESDAY => 'Tuesday',
        self::DAY_WEDNESDAY => 'Wednesday',
        self::DAY_THURSDAY => 'Thursday',
        self::DAY_FRIDAY => 'Friday',
        self::DAY_SATURDAY => 'Saturday',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the branch.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Get the attendance records.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'working_schedule_id');
    }

    /**
     * Get the attendance rules.
     */
    public function rules(): HasMany
    {
        return $this->hasMany(AttendanceRule::class, 'working_schedule_id');
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
     * Scope for active schedules.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for inactive schedules.
     */
    public function scopeInactive($query)
    {
        return $query->where('status', self::STATUS_INACTIVE);
    }

    /**
     * Scope for default schedule.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope for fixed schedules.
     */
    public function scopeFixed($query)
    {
        return $query->where('type', self::TYPE_FIXED);
    }

    /**
     * Scope for flexible schedules.
     */
    public function scopeFlexible($query)
    {
        return $query->where('type', self::TYPE_FLEXIBLE);
    }

    /**
     * Scope for shift schedules.
     */
    public function scopeShift($query)
    {
        return $query->where('type', self::TYPE_SHIFT);
    }

    /**
     * Scope by branch.
     */
    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if check-in time is late.
     */
    public function isLateCheckIn($checkInTime): bool
    {
        if (!$this->start_time) {
            return false;
        }

        $scheduledStart = Carbon::parse($this->start_time);
        $actualCheckIn = Carbon::parse($checkInTime);

        // Add grace period
        $graceEnd = $scheduledStart->copy()->addMinutes($this->grace_period_late_minutes);

        return $actualCheckIn->gt($graceEnd);
    }

    /**
     * Check if check-out time is early.
     */
    public function isEarlyCheckOut($checkOutTime): bool
    {
        if (!$this->end_time) {
            return false;
        }

        $scheduledEnd = Carbon::parse($this->end_time);
        $actualCheckOut = Carbon::parse($checkOutTime);

        // Subtract grace period
        $graceStart = $scheduledEnd->copy()->subMinutes($this->grace_period_early_minutes);

        return $actualCheckOut->lt($graceStart);
    }

    /**
     * Calculate late minutes.
     */
    public function calculateLateMinutes($checkInTime): int
    {
        if (!$this->start_time) {
            return 0;
        }

        $scheduledStart = Carbon::parse($this->start_time);
        $actualCheckIn = Carbon::parse($checkInTime);

        // Add grace period
        $graceEnd = $scheduledStart->copy()->addMinutes($this->grace_period_late_minutes);

        if ($actualCheckIn->lte($graceEnd)) {
            return 0;
        }

        return $actualCheckIn->diffInMinutes($graceEnd);
    }

    /**
     * Calculate early check-out minutes.
     */
    public function calculateEarlyMinutes($checkOutTime): int
    {
        if (!$this->end_time) {
            return 0;
        }

        $scheduledEnd = Carbon::parse($this->end_time);
        $actualCheckOut = Carbon::parse($checkOutTime);

        // Subtract grace period
        $graceStart = $scheduledEnd->copy()->subMinutes($this->grace_period_early_minutes);

        if ($actualCheckOut->gte($graceStart)) {
            return 0;
        }

        return $graceStart->diffInMinutes($actualCheckOut);
    }

    /**
     * Check if a day of week is a working day.
     */
    public function isWorkingDay(int $dayOfWeek): bool
    {
        $workingDays = $this->working_days ?? [1, 2, 3, 4, 5]; // Default: Mon-Fri

        return in_array($dayOfWeek, $workingDays);
    }

    /**
     * Check if a given date is a working day.
     */
    public function isWorkingDate($date): bool
    {
        $dayOfWeek = Carbon::parse($date)->dayOfWeek;

        return $this->isWorkingDay($dayOfWeek);
    }

    /**
     * Check if time is within working hours.
     */
    public function isWithinWorkingHours($time): bool
    {
        if (!$this->start_time || !$this->end_time) {
            return true;
        }

        $checkTime = Carbon::parse($time);
        $startTime = Carbon::parse($this->start_time);
        $endTime = Carbon::parse($this->end_time);

        return $checkTime->between($startTime, $endTime);
    }

    /**
     * Check if time is within core hours.
     */
    public function isWithinCoreHours($time): bool
    {
        if (!$this->core_hours_required || !$this->core_hours_start || !$this->core_hours_end) {
            return true;
        }

        $checkTime = Carbon::parse($time);
        $coreStart = Carbon::parse($this->core_hours_start);
        $coreEnd = Carbon::parse($this->core_hours_end);

        return $checkTime->between($coreStart, $coreEnd);
    }

    /**
     * Set this schedule as the default.
     */
    public function setAsDefault(): void
    {
        // Remove default from other schedules
        static::query()
            ->where('tenant_id', $this->tenant_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->is_default = true;
        $this->save();
    }

    /**
     * Calculate expected working hours for a date range.
     */
    public function calculateExpectedHours($startDate, $endDate): float
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $totalHours = 0;

        while ($start->lte($end)) {
            if ($this->isWorkingDay($start->dayOfWeek)) {
                $totalHours += $this->hours_per_day;
            }
            $start->addDay();
        }

        return $totalHours;
    }

    /**
     * Count working days in a date range.
     */
    public function countWorkingDays($startDate, $endDate): int
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $count = 0;

        while ($start->lte($end)) {
            if ($this->isWorkingDay($start->dayOfWeek)) {
                $count++;
            }
            $start->addDay();
        }

        return $count;
    }

    /**
     * Duplicate schedule with new name and code.
     */
    public function duplicate(string $name, string $code): self
    {
        $attributes = $this->toArray();
        unset($attributes['id'], $attributes['created_at'], $attributes['updated_at'], $attributes['deleted_at']);

        $attributes['name'] = $name;
        $attributes['code'] = $code;
        $attributes['is_default'] = false;

        return static::create($attributes);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Get formatted working hours.
     */
    public function getFormattedWorkingHoursAttribute(): string
    {
        return "{$this->hours_per_day}h/day, {$this->hours_per_week}h/week";
    }

    /**
     * Get formatted time range.
     */
    public function getFormattedTimeRangeAttribute(): string
    {
        if (!$this->start_time || !$this->end_time) {
            return 'Flexible';
        }

        $start = Carbon::parse($this->start_time)->format('h:i A');
        $end = Carbon::parse($this->end_time)->format('h:i A');

        return "{$start} - {$end}";
    }

    /**
     * Get working days list.
     */
    public function getWorkingDaysListAttribute(): string
    {
        $workingDays = $this->working_days ?? [];

        if (empty($workingDays)) {
            return 'None';
        }

        $dayNames = array_map(function ($day) {
            return self::DAYS[$day] ?? '';
        }, $workingDays);

        // Shorten if all weekdays
        if ($workingDays === [1, 2, 3, 4, 5]) {
            return 'Mon-Fri';
        }

        if ($workingDays === [0, 1, 2, 3, 4, 5, 6]) {
            return 'All Days';
        }

        return implode(', ', array_map(fn($d) => substr($d, 0, 3), $dayNames));
    }

    /**
     * Get type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Get break description.
     */
    public function getBreakDescriptionAttribute(): string
    {
        if (!$this->has_break) {
            return 'No Break';
        }

        $duration = $this->break_duration_minutes;

        if ($this->break_start && $this->break_end) {
            $start = Carbon::parse($this->break_start)->format('h:i A');
            $end = Carbon::parse($this->break_end)->format('h:i A');

            return "{$duration} min ({$start} - {$end})";
        }

        return "{$duration} min (Flexible)";
    }

    /**
     * Get active employee count.
     */
    public function getActiveEmployeeCountAttribute(): int
    {
        return $this->attendances()
            ->select('staff_profile_id')
            ->distinct()
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */

    protected static function boot()
    {
        parent::boot();

        // Set default working days if not provided
        static::creating(function ($schedule) {
            if (!$schedule->working_days) {
                // Default: Sunday-Thursday (Middle East) or Monday-Friday (adjust as needed)
                $schedule->working_days = [0, 1, 2, 3, 4]; // Sun-Thu
            }
        });

        // Ensure only one default per tenant
        static::saving(function ($schedule) {
            if ($schedule->is_default && $schedule->isDirty('is_default')) {
                static::query()
                    ->where('tenant_id', $schedule->tenant_id)
                    ->where('id', '!=', $schedule->id)
                    ->update(['is_default' => false]);
            }
        });
    }
}
