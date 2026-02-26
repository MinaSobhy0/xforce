<?php

namespace Modules\Booking\Models;

use XLinic\Framework\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Branch;

class BookingConfig extends BaseModel
{
    // Note: No HasTenancy trait - in schema-per-tenant, data is isolated by schema
    // tenant_id column is not needed

    protected $table = 'booking_configs';

    protected $fillable = [
        'branch_id',
        // Step 1: Service & Time
        'slot_duration',
        'slot_interval',
        'buffer_minutes',
        'working_hours_start',
        'working_hours_end',
        'break_enabled',
        'break_start',
        'break_end',
        // Step 2: Doctor
        'check_doctor_schedule',
        'check_doctor_timeoff',
        'max_per_doctor_daily',
        'allow_doctor_overlap',
        // Step 3: Room
        'room_assignment',
        'check_room_availability',
        'allow_room_overlap',
        // Step 4: Equipment
        'equipment_assignment',
        'check_equipment_availability',
        'allow_equipment_overlap',
        // Advance Booking
        'min_advance_hours',
        'max_advance_days',
        'allow_same_day',
        'same_day_cutoff',
    ];

    protected $casts = [
        'slot_duration' => 'integer',
        'slot_interval' => 'integer',
        'buffer_minutes' => 'integer',
        'break_enabled' => 'boolean',
        'check_doctor_schedule' => 'boolean',
        'check_doctor_timeoff' => 'boolean',
        'max_per_doctor_daily' => 'integer',
        'allow_doctor_overlap' => 'boolean',
        'check_room_availability' => 'boolean',
        'allow_room_overlap' => 'boolean',
        'check_equipment_availability' => 'boolean',
        'allow_equipment_overlap' => 'boolean',
        'min_advance_hours' => 'integer',
        'max_advance_days' => 'integer',
        'allow_same_day' => 'boolean',
    ];

    // Room assignment options
    public const ROOM_FROM_SERVICE = 'service';
    public const ROOM_AUTO_ASSIGN = 'auto';
    public const ROOM_MANUAL = 'manual';

    public const ROOM_ASSIGNMENT_OPTIONS = [
        self::ROOM_FROM_SERVICE => 'From Service Configuration',
        self::ROOM_AUTO_ASSIGN => 'Auto-assign Available Room',
        self::ROOM_MANUAL => 'Manual Selection',
    ];

    // Equipment assignment options
    public const EQUIPMENT_FROM_SERVICE = 'service';
    public const EQUIPMENT_AUTO_ASSIGN = 'auto';

    public const EQUIPMENT_ASSIGNMENT_OPTIONS = [
        self::EQUIPMENT_FROM_SERVICE => 'From Service Configuration',
        self::EQUIPMENT_AUTO_ASSIGN => 'Auto-assign Available Equipment',
    ];

    // Default configuration values
    public const DEFAULTS = [
        'slot_duration' => 30,
        'slot_interval' => null,
        'buffer_minutes' => 5,
        'working_hours_start' => '09:00',
        'working_hours_end' => '21:00',
        'break_enabled' => false,
        'break_start' => '12:00',
        'break_end' => '13:00',
        'check_doctor_schedule' => true,
        'check_doctor_timeoff' => true,
        'max_per_doctor_daily' => null,
        'allow_doctor_overlap' => false,
        'room_assignment' => self::ROOM_FROM_SERVICE,
        'check_room_availability' => true,
        'allow_room_overlap' => false,
        'equipment_assignment' => self::EQUIPMENT_FROM_SERVICE,
        'check_equipment_availability' => true,
        'allow_equipment_overlap' => false,
        'min_advance_hours' => 2,
        'max_advance_days' => 60,
        'allow_same_day' => true,
        'same_day_cutoff' => null,
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    // ==========================================
    // STATIC METHODS
    // ==========================================

    /**
     * Get configuration for a specific branch, falling back to tenant default.
     */
    public static function getForBranch(?string $branchId = null): self
    {
        // Try branch-specific config first
        if ($branchId) {
            $config = static::where('branch_id', $branchId)->first();
            if ($config) {
                return $config;
            }
        }

        // Fall back to tenant-wide config
        $config = static::whereNull('branch_id')->first();

        // Create default if none exists
        if (!$config) {
            $config = static::createDefault();
        }

        return $config;
    }

    /**
     * Get or create default configuration for a branch.
     */
    public static function createDefault(?string $branchId = null): self
    {
        return static::firstOrCreate(
            ['branch_id' => $branchId],
            self::DEFAULTS
        );
    }

    /**
     * Get a specific config value with fallback.
     */
    public static function getValue(string $key, ?string $branchId = null, $default = null)
    {
        $config = static::getForBranch($branchId);
        return $config->{$key} ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    // ==========================================
    // ACCESSORS
    // ==========================================

    /**
     * Get effective slot interval (uses slot_duration if not set).
     */
    public function getEffectiveSlotIntervalAttribute(): int
    {
        return $this->slot_interval ?? $this->slot_duration;
    }

    /**
     * Get break times array if enabled.
     */
    public function getBreakTimesAttribute(): ?array
    {
        if (!$this->break_enabled || !$this->break_start || !$this->break_end) {
            return null;
        }

        return [
            'start' => $this->break_start,
            'end' => $this->break_end,
        ];
    }

    /**
     * Get working hours array.
     */
    public function getWorkingHoursAttribute(): array
    {
        return [
            'start' => $this->working_hours_start ?? '09:00',
            'end' => $this->working_hours_end ?? '21:00',
        ];
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Check if room availability should be validated.
     */
    public function shouldCheckRoomAvailability(): bool
    {
        return $this->check_room_availability && !$this->allow_room_overlap;
    }

    /**
     * Check if equipment availability should be validated.
     */
    public function shouldCheckEquipmentAvailability(): bool
    {
        return $this->check_equipment_availability && !$this->allow_equipment_overlap;
    }

    /**
     * Check if doctor availability should be validated.
     */
    public function shouldCheckDoctorAvailability(): bool
    {
        return $this->check_doctor_schedule && !$this->allow_doctor_overlap;
    }
}
