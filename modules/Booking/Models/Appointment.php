<?php

namespace Modules\Booking\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use XLinic\Framework\Core\Model\Traits\HasSequence;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\Models\User;
use Modules\Booking\Events\AppointmentCompleted;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Room;
use Modules\Packages\Models\PackageSubscription;
use Modules\Patients\Models\Patient;
use Modules\Services\Models\Service;
use Modules\Booking\Models\Visit;

class Appointment extends BaseModel
{
    use HasTenancy, HasActivity, HasSequence, SoftDeletes;

    protected string $sequenceCode = 'appointment';
    protected string $sequenceColumn = 'code';

    protected $fillable = [
        'tenant_id',
        'code',
        'patient_id',
        'service_id',
        'branch_id',
        'practitioner_id',
        'room_id',
        'equipment_id',
        'package_subscription_id',
        'is_package_session',
        'date',
        'start_time',
        'end_time',
        'duration_minutes',
        'status',
        'price_minor',
        'quantity',
        'discount_minor',
        'discount_type',
        'discount_reason',
        'notes',
        'internal_notes',
        'cancellation_reason',
        'rescheduled_from_id',
        'source',
        'confirmed_at',
        'checked_in_at',
        'started_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'duration_minutes' => 'integer',
        'price_minor' => 'integer',
        'quantity' => 'decimal:2',
        'discount_minor' => 'integer',
        'is_package_session' => 'boolean',
        'confirmed_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';
    public const STATUS_RESCHEDULED = 'rescheduled';

    public const STATUSES = [
        self::STATUS_SCHEDULED => 'Scheduled',
        self::STATUS_CONFIRMED => 'Confirmed',
        self::STATUS_CHECKED_IN => 'Checked In',
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_NO_SHOW => 'No Show',
        self::STATUS_RESCHEDULED => 'Rescheduled',
    ];

    public const STATUS_COLORS = [
        self::STATUS_SCHEDULED => 'info',
        self::STATUS_CONFIRMED => 'primary',
        self::STATUS_CHECKED_IN => 'warning',
        self::STATUS_IN_PROGRESS => 'secondary',
        self::STATUS_COMPLETED => 'success',
        self::STATUS_CANCELLED => 'danger',
        self::STATUS_NO_SHOW => 'gray',
        self::STATUS_RESCHEDULED => 'warning',
    ];

    // Source constants
    public const SOURCE_WALK_IN = 'walk_in';
    public const SOURCE_PHONE = 'phone';
    public const SOURCE_WEBSITE = 'website';
    public const SOURCE_MOBILE_APP = 'mobile_app';
    public const SOURCE_REFERRAL = 'referral';
    public const SOURCE_SOCIAL_MEDIA = 'social_media';

    public const SOURCES = [
        self::SOURCE_WALK_IN => 'Walk-in',
        self::SOURCE_PHONE => 'Phone',
        self::SOURCE_WEBSITE => 'Website',
        self::SOURCE_MOBILE_APP => 'Mobile App',
        self::SOURCE_REFERRAL => 'Referral',
        self::SOURCE_SOCIAL_MEDIA => 'Social Media',
    ];

    // Discount type constants
    public const DISCOUNT_FIXED = 'fixed';
    public const DISCOUNT_PERCENT = 'percent';

    public const DISCOUNT_TYPES = [
        self::DISCOUNT_FIXED => 'Fixed Amount',
        self::DISCOUNT_PERCENT => 'Percentage',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Appointment $appointment) {
            if (empty($appointment->status)) {
                $appointment->status = self::STATUS_SCHEDULED;
            }
            if (empty($appointment->source)) {
                $appointment->source = self::SOURCE_PHONE;
            }
        });
    }

    // Relationships
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'practitioner_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(\Modules\Equipment\Models\Equipment::class);
    }

    public function packageSubscription(): BelongsTo
    {
        return $this->belongsTo(PackageSubscription::class);
    }

    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'rescheduled_from_id');
    }

    public function serviceNote(): HasOne
    {
        return $this->hasOne(AppointmentServiceNote::class);
    }

    public function treatmentPlanAppointment(): HasOne
    {
        return $this->hasOne(\Modules\TreatmentPlans\Models\TreatmentPlanAppointment::class);
    }

    public function visits(): BelongsToMany
    {
        return $this->belongsToMany(Visit::class, 'visit_appointments')
            ->withTimestamps();
    }

    /**
     * Get the current/latest visit for this appointment
     */
    public function getCurrentVisitAttribute(): ?Visit
    {
        return $this->visits()->latest('check_in_at')->first();
    }

    public function sessionData(): HasOne
    {
        return $this->hasOne(TreatmentSessionData::class);
    }

    public function consumables(): HasMany
    {
        return $this->hasMany(SessionConsumable::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(SessionProduct::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(\Modules\Prescriptions\Models\Prescription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(\Modules\Billing\Models\Payment::class);
    }

    /**
     * Get the invoice for this appointment.
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(\Modules\Billing\Models\Invoice::class);
    }

    /**
     * Get total consumables cost for this appointment.
     */
    public function getTotalConsumablesCostAttribute(): int
    {
        return $this->consumables->sum('total_cost_minor');
    }

    /**
     * Get total products value for this appointment.
     */
    public function getTotalProductsValueAttribute(): int
    {
        return $this->products->sum('total_price_minor');
    }

    // Accessors
    public function getStartDateTimeAttribute(): ?\Carbon\Carbon
    {
        if (!$this->date || !$this->start_time) {
            return null;
        }
        return $this->date->copy()->setTimeFrom($this->start_time);
    }

    public function getEndDateTimeAttribute(): ?\Carbon\Carbon
    {
        if (!$this->date || !$this->end_time) {
            return null;
        }
        return $this->date->copy()->setTimeFrom($this->end_time);
    }

    public function getFormattedTimeAttribute(): string
    {
        if (!$this->start_time) {
            return '';
        }
        $start = $this->start_time->format('H:i');
        $end = $this->end_time ? $this->end_time->format('H:i') : '';
        return $end ? "{$start} - {$end}" : $start;
    }

    public function getNetPriceAttribute(): int
    {
        return max(0, ($this->price_minor ?? 0) - $this->getDiscountAmountMinor());
    }

    /**
     * Calculate the actual discount amount in minor units.
     * For percentage discounts, calculate from price_minor.
     * For fixed discounts, return discount_minor directly.
     */
    public function getDiscountAmountMinor(): int
    {
        $discountMinor = $this->discount_minor ?? 0;

        if ($discountMinor <= 0) {
            return 0;
        }

        if ($this->discount_type === self::DISCOUNT_PERCENT) {
            return (int) round(($this->price_minor ?? 0) * $discountMinor / 100);
        }

        return $discountMinor;
    }

    /**
     * Check if a discount has been applied.
     */
    public function hasDiscount(): bool
    {
        return ($this->discount_minor ?? 0) > 0;
    }

    /**
     * Get formatted discount display string.
     */
    public function getDiscountDisplayAttribute(): string
    {
        if (!$this->hasDiscount()) {
            return '';
        }

        if ($this->discount_type === self::DISCOUNT_PERCENT) {
            return $this->discount_minor . '%';
        }

        return number_format($this->discount_minor / 100, 2);
    }

    public function getPaidAmountAttribute(): int
    {
        return (int) $this->payments()->sum('amount_minor');
    }

    public function getRemainingBalanceAttribute(): int
    {
        return max(0, $this->net_price - $this->paid_amount);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    public function getSourceLabelAttribute(): string
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }

    // State machine transitions
    public function canTransitionTo(string $status): bool
    {
        $transitions = [
            self::STATUS_SCHEDULED => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED, self::STATUS_RESCHEDULED],
            self::STATUS_CONFIRMED => [self::STATUS_CHECKED_IN, self::STATUS_CANCELLED, self::STATUS_NO_SHOW, self::STATUS_RESCHEDULED],
            self::STATUS_CHECKED_IN => [self::STATUS_IN_PROGRESS, self::STATUS_CANCELLED],
            self::STATUS_IN_PROGRESS => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
            self::STATUS_COMPLETED => [],
            self::STATUS_CANCELLED => [],
            self::STATUS_NO_SHOW => [],
            self::STATUS_RESCHEDULED => [],
        ];

        return in_array($status, $transitions[$this->status] ?? []);
    }

    public function transitionTo(string $status, ?string $reason = null): bool
    {
        if (!$this->canTransitionTo($status)) {
            return false;
        }

        $timestampField = match ($status) {
            self::STATUS_CONFIRMED => 'confirmed_at',
            self::STATUS_CHECKED_IN => 'checked_in_at',
            self::STATUS_IN_PROGRESS => 'started_at',
            self::STATUS_COMPLETED => 'completed_at',
            self::STATUS_CANCELLED => 'cancelled_at',
            default => null,
        };

        $data = ['status' => $status];

        if ($timestampField) {
            $data[$timestampField] = now();
        }

        if ($status === self::STATUS_CANCELLED && $reason) {
            $data['cancellation_reason'] = $reason;
        }

        $this->fill($data);
        return $this->save();
    }

    // State actions
    public function confirm(): bool
    {
        return $this->transitionTo(self::STATUS_CONFIRMED);
    }

    public function checkIn(): bool
    {
        $result = $this->transitionTo(self::STATUS_CHECKED_IN);
        if ($result && $this->patient) {
            $this->patient->recordVisit();

            // Find or create a visit and link this appointment
            $visitService = app(\Modules\Booking\Services\VisitService::class);
            $visit = $visitService->findOrCreateVisit(
                $this->patient,
                $this->branch,
                Visit::SOURCE_APPOINTMENT
            );
            $visitService->addAppointment($visit, $this);
        }
        return $result;
    }

    public function start(): bool
    {
        return $this->transitionTo(self::STATUS_IN_PROGRESS);
    }

    public function complete(): bool
    {
        $result = $this->transitionTo(self::STATUS_COMPLETED);

        if ($result) {
            AppointmentCompleted::dispatch($this);
        }

        return $result;
    }

    public function cancel(?string $reason = null): bool
    {
        return $this->transitionTo(self::STATUS_CANCELLED, $reason);
    }

    public function markNoShow(): bool
    {
        return $this->transitionTo(self::STATUS_NO_SHOW);
    }

    public function reschedule(array $newData): ?Appointment
    {
        if (!$this->canTransitionTo(self::STATUS_RESCHEDULED)) {
            return null;
        }

        // Create new appointment
        $newAppointment = self::create(array_merge([
            'tenant_id' => $this->tenant_id,
            'patient_id' => $this->patient_id,
            'service_id' => $this->service_id,
            'branch_id' => $this->branch_id,
            'practitioner_id' => $this->practitioner_id,
            'price_minor' => $this->price_minor,
            'discount_minor' => $this->discount_minor,
            'notes' => $this->notes,
            'source' => $this->source,
            'rescheduled_from_id' => $this->id,
        ], $newData));

        // Mark current as rescheduled
        $this->transitionTo(self::STATUS_RESCHEDULED);

        return $newAppointment;
    }

    // State checks
    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isCheckedIn(): bool
    {
        return $this->status === self::STATUS_CHECKED_IN;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isNoShow(): bool
    {
        return $this->status === self::STATUS_NO_SHOW;
    }

    public function isRescheduled(): bool
    {
        return $this->status === self::STATUS_RESCHEDULED;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_SCHEDULED,
            self::STATUS_CONFIRMED,
            self::STATUS_CHECKED_IN,
            self::STATUS_IN_PROGRESS,
        ]);
    }

    public function isPast(): bool
    {
        return $this->date && $this->date->isPast();
    }

    public function isToday(): bool
    {
        return $this->date && $this->date->isToday();
    }

    public function isUpcoming(): bool
    {
        return $this->date && $this->date->isFuture();
    }

    // Scopes
    public function scopeToday($query)
    {
        return $query->whereDate('date', today());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('date', '>=', today())
            ->whereIn('status', [self::STATUS_SCHEDULED, self::STATUS_CONFIRMED]);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeForDateRange($query, $start, $end)
    {
        return $query->whereBetween('date', [$start, $end]);
    }

    public function scopeForBranch($query, ?string $branchId)
    {
        if ($branchId === null) {
            return $query;
        }
        return $query->where('branch_id', $branchId);
    }

    public function scopeForPractitioner($query, string $practitionerId)
    {
        return $query->where('practitioner_id', $practitionerId);
    }

    public function scopeForRoom($query, string $roomId)
    {
        return $query->where('room_id', $roomId);
    }

    public function scopeForPatient($query, string $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeForService($query, string $serviceId)
    {
        return $query->where('service_id', $serviceId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            self::STATUS_SCHEDULED,
            self::STATUS_CONFIRMED,
            self::STATUS_CHECKED_IN,
            self::STATUS_IN_PROGRESS,
        ]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeNoShow($query)
    {
        return $query->where('status', self::STATUS_NO_SHOW);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('date')->orderBy('start_time');
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('code', 'ilike', "%{$term}%")
                ->orWhereHas('patient', function ($pq) use ($term) {
                    $pq->where('first_name', 'ilike', "%{$term}%")
                        ->orWhere('last_name', 'ilike', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
        });
    }

    public function scopePackageSessions($query)
    {
        return $query->where('is_package_session', true);
    }

    public function scopeRegularSessions($query)
    {
        return $query->where('is_package_session', false);
    }

    // Package session helpers
    public function isPackageSession(): bool
    {
        return $this->is_package_session === true && $this->package_subscription_id !== null;
    }

    public function hasPackageSubscription(): bool
    {
        return $this->package_subscription_id !== null;
    }

    public function getPackageSessionLabel(): ?string
    {
        if (!$this->isPackageSession() || !$this->packageSubscription) {
            return null;
        }

        $used = $this->packageSubscription->sessions_used;
        $total = $this->packageSubscription->package?->total_sessions ?? 0;

        return "Session {$used}/{$total}";
    }
}
