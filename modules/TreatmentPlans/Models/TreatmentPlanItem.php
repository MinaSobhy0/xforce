<?php

namespace Modules\TreatmentPlans\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Auth\Models\User;
use Modules\Services\Models\Service;
use Modules\Booking\Models\Appointment;
use Modules\Packages\Models\Package;
use Modules\Billing\Models\InvoiceLine;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Carbon\Carbon;

class TreatmentPlanItem extends BaseModel
{
    use HasTenancy;

    protected $fillable = [
        'tenant_id',
        'treatment_plan_id',
        'item_type',
        'itemable_type',
        'itemable_id',
        'service_id',
        'recommended_sessions',
        'completed_sessions',
        'session_interval_days',
        'unit_price_minor',
        'discount_minor',
        'total_minor',
        'quantity',
        'completed_quantity',
        'invoiced_quantity',
        'is_delivered',
        'delivered_at',
        'preferred_practitioner_id',
        'preferred_day_of_week',
        'preferred_time_slot',
        'status',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'recommended_sessions' => 'integer',
        'completed_sessions' => 'integer',
        'session_interval_days' => 'integer',
        'unit_price_minor' => 'integer',
        'discount_minor' => 'integer',
        'total_minor' => 'integer',
        'quantity' => 'integer',
        'completed_quantity' => 'integer',
        'invoiced_quantity' => 'integer',
        'is_delivered' => 'boolean',
        'delivered_at' => 'datetime',
        'preferred_day_of_week' => 'array',
        'sort_order' => 'integer',
    ];

    // Item type constants
    public const TYPE_SERVICE = 'service';
    public const TYPE_PACKAGE = 'package';
    public const TYPE_PRODUCT = 'product';

    public const TYPES = [
        self::TYPE_SERVICE => 'Service',
        self::TYPE_PACKAGE => 'Package',
        self::TYPE_PRODUCT => 'Product',
    ];

    public const TYPE_COLORS = [
        self::TYPE_SERVICE => 'primary',
        self::TYPE_PACKAGE => 'success',
        self::TYPE_PRODUCT => 'warning',
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const STATUS_COLORS = [
        self::STATUS_PENDING => 'gray',
        self::STATUS_IN_PROGRESS => 'warning',
        self::STATUS_COMPLETED => 'success',
        self::STATUS_CANCELLED => 'danger',
    ];

    // Time slot constants
    public const TIME_SLOT_MORNING = 'morning';
    public const TIME_SLOT_AFTERNOON = 'afternoon';
    public const TIME_SLOT_EVENING = 'evening';

    public const TIME_SLOTS = [
        self::TIME_SLOT_MORNING => 'Morning (9AM - 12PM)',
        self::TIME_SLOT_AFTERNOON => 'Afternoon (12PM - 5PM)',
        self::TIME_SLOT_EVENING => 'Evening (5PM - 9PM)',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (TreatmentPlanItem $item) {
            if (empty($item->status)) {
                $item->status = self::STATUS_PENDING;
            }
            if (empty($item->item_type)) {
                $item->item_type = self::TYPE_SERVICE;
            }
            if (empty($item->session_interval_days) && $item->item_type === self::TYPE_SERVICE) {
                $item->session_interval_days = config('treatment_plans.default_session_interval_days', 7);
            }
            if (empty($item->quantity)) {
                $item->quantity = $item->recommended_sessions ?? 1;
            }
            // Calculate total
            $item->calculateTotal();
        });

        static::updating(function (TreatmentPlanItem $item) {
            $item->calculateTotal();
        });

        static::saved(function (TreatmentPlanItem $item) {
            // Recalculate treatment plan financials
            $item->treatmentPlan?->recalculateFinancials();
        });

        static::deleted(function (TreatmentPlanItem $item) {
            $item->treatmentPlan?->recalculateFinancials();
        });
    }

    /**
     * Calculate line total
     */
    public function calculateTotal(): void
    {
        $qty = $this->item_type === self::TYPE_SERVICE
            ? ($this->recommended_sessions ?? 1)
            : ($this->quantity ?? 1);

        $subtotal = ($this->unit_price_minor ?? 0) * $qty;
        $this->total_minor = max(0, $subtotal - ($this->discount_minor ?? 0));
    }

    // Relationships
    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Polymorphic relationship to itemable (Service, Package, or Product)
     */
    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    public function preferredPractitioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preferred_practitioner_id');
    }

    public function planAppointments(): HasMany
    {
        return $this->hasMany(TreatmentPlanAppointment::class)->orderBy('session_number');
    }

    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class, 'treatment_plan_item_id');
    }

    // Session tracking
    public function getRemainingSessionsAttribute(): int
    {
        return max(0, $this->recommended_sessions - $this->completed_sessions);
    }

    public function getScheduledSessionsCountAttribute(): int
    {
        return $this->planAppointments()
            ->whereHas('appointment', function ($query) {
                $query->whereIn('status', [
                    Appointment::STATUS_SCHEDULED,
                    Appointment::STATUS_CONFIRMED,
                    Appointment::STATUS_CHECKED_IN,
                    Appointment::STATUS_IN_PROGRESS,
                ]);
            })
            ->count();
    }

    public function getUnscheduledSessionsAttribute(): int
    {
        return max(0, $this->remaining_sessions - $this->scheduled_sessions_count);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->recommended_sessions <= 0) {
            return 0;
        }
        return round(($this->completed_sessions / $this->recommended_sessions) * 100, 1);
    }

    public function getNextSessionNumberAttribute(): int
    {
        $lastSession = $this->planAppointments()
            ->orderByDesc('session_number')
            ->value('session_number') ?? 0;
        return $lastSession + 1;
    }

    public function getLastCompletedAppointmentAttribute(): ?Appointment
    {
        return Appointment::whereHas('treatmentPlanAppointment', function ($query) {
            $query->where('treatment_plan_item_id', $this->id);
        })
        ->where('status', Appointment::STATUS_COMPLETED)
        ->orderByDesc('date')
        ->orderByDesc('start_time')
        ->first();
    }

    public function getNextSuggestedDateAttribute(): ?Carbon
    {
        $lastCompleted = $this->last_completed_appointment;

        if (!$lastCompleted || !$this->session_interval_days) {
            return today();
        }

        $suggestedDate = $lastCompleted->date->addDays($this->session_interval_days);

        // If suggested date is in the past, return today
        return $suggestedDate->isFuture() ? $suggestedDate : today();
    }

    // State checks
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
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

    public function canBook(): bool
    {
        return !$this->isCompleted() && !$this->isCancelled() && $this->unscheduled_sessions > 0;
    }

    // State transitions
    public function updateStatusFromProgress(): bool
    {
        $oldStatus = $this->status;

        if ($this->completed_sessions >= $this->recommended_sessions) {
            $this->status = self::STATUS_COMPLETED;
        } elseif ($this->completed_sessions > 0) {
            $this->status = self::STATUS_IN_PROGRESS;
        } else {
            $this->status = self::STATUS_PENDING;
        }

        if ($this->status !== $oldStatus) {
            $saved = $this->save();

            // Check if parent plan should be completed
            if ($this->status === self::STATUS_COMPLETED) {
                $this->treatmentPlan->checkAndMarkComplete();
            }

            return $saved;
        }

        return true;
    }

    public function incrementCompletedSessions(): bool
    {
        $this->completed_sessions = min(
            $this->completed_sessions + 1,
            $this->recommended_sessions
        );
        $this->save();

        return $this->updateStatusFromProgress();
    }

    public function cancel(): bool
    {
        $this->status = self::STATUS_CANCELLED;
        return $this->save();
    }

    // Accessors
    public function getServiceNameAttribute(): string
    {
        return $this->service?->translated_name ?? '';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    public function getTimeSlotLabelAttribute(): ?string
    {
        if (!$this->preferred_time_slot) {
            return null;
        }
        return self::TIME_SLOTS[$this->preferred_time_slot] ?? $this->preferred_time_slot;
    }

    public function getPreferredDaysLabelAttribute(): ?string
    {
        if (empty($this->preferred_day_of_week)) {
            return null;
        }

        $days = config('treatment_plans.days_of_week', [
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
        ]);

        $selectedDays = array_map(fn($day) => $days[$day] ?? $day, $this->preferred_day_of_week);
        return implode(', ', $selectedDays);
    }

    public function getSessionProgressDisplayAttribute(): string
    {
        return "{$this->completed_sessions}/{$this->recommended_sessions}";
    }

    // Item type accessors
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->item_type] ?? $this->item_type;
    }

    public function getTypeColorAttribute(): string
    {
        return self::TYPE_COLORS[$this->item_type] ?? 'gray';
    }

    public function isService(): bool
    {
        return $this->item_type === self::TYPE_SERVICE;
    }

    public function isPackage(): bool
    {
        return $this->item_type === self::TYPE_PACKAGE;
    }

    public function isProduct(): bool
    {
        return $this->item_type === self::TYPE_PRODUCT;
    }

    /**
     * Get item name from polymorphic relationship or service
     */
    public function getItemNameAttribute(): string
    {
        if ($this->itemable) {
            return $this->itemable->translated_name ?? $this->itemable->name ?? '';
        }
        return $this->service?->translated_name ?? '';
    }

    /**
     * Get remaining quantity to invoice
     */
    public function getRemainingToInvoiceAttribute(): int
    {
        if ($this->isService()) {
            return max(0, $this->completed_sessions - ($this->invoiced_quantity ?? 0));
        }

        if ($this->isProduct()) {
            return $this->is_delivered && $this->invoiced_quantity < $this->quantity
                ? $this->quantity - $this->invoiced_quantity
                : 0;
        }

        // Package: can be invoiced once fully
        return $this->invoiced_quantity > 0 ? 0 : 1;
    }

    /**
     * Check if item can be invoiced
     */
    public function getCanBeInvoicedAttribute(): bool
    {
        return $this->remaining_to_invoice > 0;
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        return number_format(($this->unit_price_minor ?? 0) / 100, 2);
    }

    public function getFormattedTotalAttribute(): string
    {
        return number_format(($this->total_minor ?? 0) / 100, 2);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }

    public function scopeNeedsScheduling($query)
    {
        return $query->active()
            ->whereColumn('completed_sessions', '<', 'recommended_sessions');
    }

    public function scopeForService($query, string $serviceId)
    {
        return $query->where('service_id', $serviceId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
