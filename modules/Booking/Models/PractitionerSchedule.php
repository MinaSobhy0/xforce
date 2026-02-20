<?php

namespace Modules\Booking\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\User;
use Modules\Core\Models\Branch;

class PractitionerSchedule extends BaseModel
{
    use HasTenancy;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'branch_id',
        'day_of_week',
        'start_time',
        'end_time',
        'break_start',
        'break_end',
        'is_available',
        'max_appointments',
        'notes',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_available' => 'boolean',
        'max_appointments' => 'integer',
    ];

    // Days of week (0 = Sunday, 6 = Saturday)
    public const DAYS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    public const DAYS_SHORT = [
        0 => 'Sun',
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
    ];

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function getDayNameAttribute(): string
    {
        return self::DAYS[$this->day_of_week] ?? '';
    }

    public function getDayShortAttribute(): string
    {
        return self::DAYS_SHORT[$this->day_of_week] ?? '';
    }

    public function getFormattedHoursAttribute(): string
    {
        if (!$this->is_available) {
            return 'Off';
        }
        return "{$this->start_time} - {$this->end_time}";
    }

    public function getFormattedBreakAttribute(): ?string
    {
        if (!$this->break_start || !$this->break_end) {
            return null;
        }
        return "{$this->break_start} - {$this->break_end}";
    }

    public function isAvailableAt(string $time): bool
    {
        if (!$this->is_available) {
            return false;
        }

        // Check if within working hours
        if ($time < $this->start_time || $time >= $this->end_time) {
            return false;
        }

        // Check if during break
        if ($this->break_start && $this->break_end) {
            if ($time >= $this->break_start && $time < $this->break_end) {
                return false;
            }
        }

        return true;
    }

    public function getWorkingMinutesAttribute(): int
    {
        if (!$this->is_available) {
            return 0;
        }

        $start = strtotime($this->start_time);
        $end = strtotime($this->end_time);
        $total = ($end - $start) / 60;

        // Subtract break time
        if ($this->break_start && $this->break_end) {
            $breakStart = strtotime($this->break_start);
            $breakEnd = strtotime($this->break_end);
            $total -= ($breakEnd - $breakStart) / 60;
        }

        return max(0, (int) $total);
    }

    // Scopes
    public function scopeForPractitioner($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForBranch($query, string $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeForDay($query, int $dayOfWeek)
    {
        return $query->where('day_of_week', $dayOfWeek);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('day_of_week')->orderBy('start_time');
    }
}
