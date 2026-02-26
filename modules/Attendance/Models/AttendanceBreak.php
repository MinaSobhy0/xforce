<?php

namespace Modules\Attendance\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use XLinic\Framework\Core\Model\BaseModel;

class AttendanceBreak extends BaseModel
{
    use SoftDeletes;

    protected $table = 'attendance_breaks';

    protected $fillable = [
        'tenant_id',
        'attendance_id',
        'start_time',
        'end_time',
        'duration_minutes',
        'reason',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration_minutes' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'duration_minutes' => 0,
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

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope for ongoing breaks (no end time).
     */
    public function scopeOngoing($query)
    {
        return $query->whereNull('end_time');
    }

    /**
     * Scope for completed breaks.
     */
    public function scopeCompleted($query)
    {
        return $query->whereNotNull('end_time');
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Calculate duration in minutes.
     */
    public function calculateDuration(): int
    {
        if (!$this->start_time) {
            return 0;
        }

        $endTime = $this->end_time ?? Carbon::now();
        $startTime = Carbon::parse($this->start_time);

        return $startTime->diffInMinutes($endTime);
    }

    /**
     * End the break.
     */
    public function endBreak(): bool
    {
        if ($this->end_time !== null) {
            return false; // Already ended
        }

        $this->end_time = now();
        $this->duration_minutes = $this->calculateDuration();

        return $this->save();
    }

    /**
     * Check if break is ongoing.
     */
    public function isOngoing(): bool
    {
        return $this->end_time === null;
    }

    /**
     * Check if break is completed.
     */
    public function isCompleted(): bool
    {
        return $this->end_time !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Get formatted duration.
     */
    public function getFormattedDurationAttribute(): string
    {
        $hours = intdiv($this->duration_minutes, 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }

    /**
     * Get time range.
     */
    public function getTimeRangeAttribute(): string
    {
        $start = $this->start_time ? Carbon::parse($this->start_time)->format('h:i A') : '--:--';
        $end = $this->end_time ? Carbon::parse($this->end_time)->format('h:i A') : 'Ongoing';

        return "{$start} - {$end}";
    }

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */

    protected static function boot()
    {
        parent::boot();

        // Auto-calculate duration when end_time is set
        static::saving(function ($break) {
            if ($break->end_time && !$break->duration_minutes) {
                $break->duration_minutes = $break->calculateDuration();
            }
        });
    }
}
