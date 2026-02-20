<?php

namespace Modules\Booking\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\User;
use Modules\Core\Models\Branch;
use Modules\Patients\Models\Patient;
use Modules\Treatments\Models\Treatment;

class Waitlist extends BaseModel
{
    use HasTenancy;

    protected $table = 'waitlist';

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'treatment_id',
        'branch_id',
        'practitioner_id',
        'preferred_days',
        'preferred_times',
        'priority',
        'notes',
        'status',
        'notified_at',
        'expires_at',
    ];

    protected $casts = [
        'preferred_days' => 'array',
        'preferred_times' => 'array',
        'priority' => 'integer',
        'notified_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_WAITING = 'waiting';
    public const STATUS_NOTIFIED = 'notified';
    public const STATUS_BOOKED = 'booked';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_WAITING => 'Waiting',
        self::STATUS_NOTIFIED => 'Notified',
        self::STATUS_BOOKED => 'Booked',
        self::STATUS_EXPIRED => 'Expired',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const STATUS_COLORS = [
        self::STATUS_WAITING => 'warning',
        self::STATUS_NOTIFIED => 'info',
        self::STATUS_BOOKED => 'success',
        self::STATUS_EXPIRED => 'gray',
        self::STATUS_CANCELLED => 'danger',
    ];

    // Priority levels
    public const PRIORITY_LOW = 1;
    public const PRIORITY_NORMAL = 2;
    public const PRIORITY_HIGH = 3;
    public const PRIORITY_URGENT = 4;

    public const PRIORITIES = [
        self::PRIORITY_LOW => 'Low',
        self::PRIORITY_NORMAL => 'Normal',
        self::PRIORITY_HIGH => 'High',
        self::PRIORITY_URGENT => 'Urgent',
    ];

    // Preferred times
    public const TIME_MORNING = 'morning';
    public const TIME_AFTERNOON = 'afternoon';
    public const TIME_EVENING = 'evening';
    public const TIME_ANY = 'any';

    public const PREFERRED_TIMES = [
        self::TIME_MORNING => 'Morning (9am - 12pm)',
        self::TIME_AFTERNOON => 'Afternoon (12pm - 5pm)',
        self::TIME_EVENING => 'Evening (5pm - 9pm)',
        self::TIME_ANY => 'Any time',
    ];

    protected static function booted(): void
    {
        static::creating(function (Waitlist $waitlist) {
            if (empty($waitlist->status)) {
                $waitlist->status = self::STATUS_WAITING;
            }
            if (empty($waitlist->priority)) {
                $waitlist->priority = self::PRIORITY_NORMAL;
            }
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'practitioner_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITIES[$this->priority] ?? 'Normal';
    }

    public function getFormattedPreferredDaysAttribute(): string
    {
        if (empty($this->preferred_days)) {
            return 'Any day';
        }

        return collect($this->preferred_days)
            ->map(fn ($day) => PractitionerSchedule::DAYS[$day] ?? $day)
            ->implode(', ');
    }

    public function getFormattedPreferredTimesAttribute(): string
    {
        if (empty($this->preferred_times)) {
            return 'Any time';
        }

        return collect($this->preferred_times)
            ->map(fn ($time) => self::PREFERRED_TIMES[$time] ?? $time)
            ->implode(', ');
    }

    public function isWaiting(): bool
    {
        return $this->status === self::STATUS_WAITING;
    }

    public function isNotified(): bool
    {
        return $this->status === self::STATUS_NOTIFIED;
    }

    public function isBooked(): bool
    {
        return $this->status === self::STATUS_BOOKED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_WAITING, self::STATUS_NOTIFIED]);
    }

    public function markNotified(): bool
    {
        if (!$this->isWaiting()) {
            return false;
        }

        return $this->update([
            'status' => self::STATUS_NOTIFIED,
            'notified_at' => now(),
        ]);
    }

    public function markBooked(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        return $this->update([
            'status' => self::STATUS_BOOKED,
        ]);
    }

    public function markExpired(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        return $this->update([
            'status' => self::STATUS_EXPIRED,
        ]);
    }

    public function cancel(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        return $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);
    }

    public function matchesSlot(\Carbon\Carbon $datetime): bool
    {
        // Check day preference
        if (!empty($this->preferred_days)) {
            if (!in_array($datetime->dayOfWeek, $this->preferred_days)) {
                return false;
            }
        }

        // Check time preference
        if (!empty($this->preferred_times) && !in_array(self::TIME_ANY, $this->preferred_times)) {
            $hour = $datetime->hour;
            $matchesTime = false;

            if (in_array(self::TIME_MORNING, $this->preferred_times) && $hour >= 9 && $hour < 12) {
                $matchesTime = true;
            }
            if (in_array(self::TIME_AFTERNOON, $this->preferred_times) && $hour >= 12 && $hour < 17) {
                $matchesTime = true;
            }
            if (in_array(self::TIME_EVENING, $this->preferred_times) && $hour >= 17 && $hour < 21) {
                $matchesTime = true;
            }

            if (!$matchesTime) {
                return false;
            }
        }

        return true;
    }

    // Scopes
    public function scopeForPatient($query, string $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeForTreatment($query, string $treatmentId)
    {
        return $query->where('treatment_id', $treatmentId);
    }

    public function scopeForBranch($query, string $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeForPractitioner($query, string $practitionerId)
    {
        return $query->where('practitioner_id', $practitionerId);
    }

    public function scopeWaiting($query)
    {
        return $query->where('status', self::STATUS_WAITING);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_WAITING, self::STATUS_NOTIFIED]);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc')->orderBy('created_at');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('priority', 'desc')->orderBy('created_at');
    }
}
