<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\Models\User;
use XLinic\Framework\Core\Model\BaseModel;

class AttendanceLog extends BaseModel
{
    use SoftDeletes;

    protected $table = 'attendance_logs';

    protected $fillable = [
        'tenant_id',
        'attendance_id',
        'type',
        'latitude',
        'longitude',
        'altitude',
        'horizontal_accuracy',
        'vertical_accuracy',
        'speed',
        'address',
        'source',
        'device_info',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'id' => 'string',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'altitude' => 'decimal:2',
        'horizontal_accuracy' => 'decimal:2',
        'vertical_accuracy' => 'decimal:2',
        'speed' => 'decimal:2',
        'device_info' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'source' => self::SOURCE_MANUAL,
    ];

    // Log Types
    public const TYPE_CHECK_IN = 'check_in';
    public const TYPE_CHECK_OUT = 'check_out';
    public const TYPE_BREAK_START = 'break_start';
    public const TYPE_BREAK_END = 'break_end';

    public const TYPES = [
        self::TYPE_CHECK_IN => 'Check In',
        self::TYPE_CHECK_OUT => 'Check Out',
        self::TYPE_BREAK_START => 'Break Start',
        self::TYPE_BREAK_END => 'Break End',
    ];

    public const TYPE_COLORS = [
        self::TYPE_CHECK_IN => 'success',
        self::TYPE_CHECK_OUT => 'danger',
        self::TYPE_BREAK_START => 'warning',
        self::TYPE_BREAK_END => 'info',
    ];

    // Source
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_MOBILE = 'mobile';
    public const SOURCE_BIOMETRIC = 'biometric';
    public const SOURCE_WEB = 'web';

    public const SOURCES = [
        self::SOURCE_MANUAL => 'Manual Entry',
        self::SOURCE_MOBILE => 'Mobile App',
        self::SOURCE_BIOMETRIC => 'Biometric Device',
        self::SOURCE_WEB => 'Web Portal',
    ];

    public const SOURCE_COLORS = [
        self::SOURCE_MANUAL => 'gray',
        self::SOURCE_MOBILE => 'info',
        self::SOURCE_BIOMETRIC => 'success',
        self::SOURCE_WEB => 'primary',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the attendance record.
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class, 'attendance_id');
    }

    /**
     * Get the creator.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope for check-in logs.
     */
    public function scopeCheckIns($query)
    {
        return $query->where('type', self::TYPE_CHECK_IN);
    }

    /**
     * Scope for check-out logs.
     */
    public function scopeCheckOuts($query)
    {
        return $query->where('type', self::TYPE_CHECK_OUT);
    }

    /**
     * Scope by source.
     */
    public function scopeFromSource($query, string $source)
    {
        return $query->where('source', $source);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Get formatted location.
     */
    public function getFormattedLocationAttribute(): string
    {
        if (!$this->latitude || !$this->longitude) {
            return 'N/A';
        }

        return "{$this->latitude}, {$this->longitude}";
    }

    /**
     * Get Google Maps URL.
     */
    public function getGoogleMapsUrlAttribute(): ?string
    {
        if (!$this->latitude || !$this->longitude) {
            return null;
        }

        return "https://www.google.com/maps?q={$this->latitude},{$this->longitude}";
    }

    /**
     * Check if has location data.
     */
    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Get type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Get source label.
     */
    public function getSourceLabelAttribute(): string
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }
}
