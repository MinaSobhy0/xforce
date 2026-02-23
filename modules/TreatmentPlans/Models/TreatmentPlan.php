<?php

namespace Modules\TreatmentPlans\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasTranslation;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use XLinic\Framework\Core\Model\Traits\HasSequence;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\Models\User;
use Modules\Core\Models\Branch;
use Modules\Patients\Models\Patient;
use Modules\Packages\Models\Package;
use Modules\Packages\Models\PackageSubscription;
use Modules\Booking\Models\Appointment;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Carbon\Carbon;

class TreatmentPlan extends BaseModel
{
    use HasTenancy, HasTranslation, HasActivity, HasSequence, SoftDeletes;

    protected string $sequenceCode = 'treatment_plan';
    protected string $sequenceColumn = 'code';

    protected $fillable = [
        'tenant_id',
        'code',
        'patient_id',
        'branch_id',
        'created_by_user_id',
        'name',
        'description',
        'status',
        'start_date',
        'target_end_date',
        'actual_end_date',
        'source',
        'recommended_package_id',
        'package_subscription_id',
        'notes',
        'internal_notes',
        'activated_at',
        'paused_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'total_value_minor',
        'total_deposits_minor',
        'total_invoiced_minor',
        'total_paid_minor',
        'balance_minor',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'start_date' => 'date',
        'target_end_date' => 'date',
        'actual_end_date' => 'date',
        'activated_at' => 'datetime',
        'paused_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'total_value_minor' => 'integer',
        'total_deposits_minor' => 'integer',
        'total_invoiced_minor' => 'integer',
        'total_paid_minor' => 'integer',
        'balance_minor' => 'integer',
    ];

    public array $translatable = ['name', 'description'];

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_PAUSED => 'Paused',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const STATUS_COLORS = [
        self::STATUS_DRAFT => 'gray',
        self::STATUS_ACTIVE => 'success',
        self::STATUS_PAUSED => 'warning',
        self::STATUS_COMPLETED => 'info',
        self::STATUS_CANCELLED => 'danger',
    ];

    // Source constants
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_CONSULTATION = 'consultation';

    public const SOURCES = [
        self::SOURCE_MANUAL => 'Manual Entry',
        self::SOURCE_CONSULTATION => 'From Consultation',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (TreatmentPlan $plan) {
            if (empty($plan->status)) {
                $plan->status = self::STATUS_DRAFT;
            }
            if (empty($plan->source)) {
                $plan->source = self::SOURCE_MANUAL;
            }
            if (empty($plan->created_by_user_id)) {
                $plan->created_by_user_id = auth()->id();
            }
        });
    }

    // Relationships
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function recommendedPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'recommended_package_id');
    }

    public function packageSubscription(): BelongsTo
    {
        return $this->belongsTo(PackageSubscription::class, 'package_subscription_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TreatmentPlanItem::class)->orderBy('sort_order');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function unassignedPayments(): HasMany
    {
        return $this->hasMany(Payment::class)
            ->whereNull('invoice_id')
            ->where('status', Payment::STATUS_COMPLETED);
    }

    public function appointments(): HasManyThrough
    {
        return $this->hasManyThrough(
            Appointment::class,
            TreatmentPlanAppointment::class,
            'treatment_plan_item_id', // Foreign key on treatment_plan_appointments
            'id', // Foreign key on appointments
            'id', // Local key on treatment_plans (through items)
            'appointment_id' // Local key on treatment_plan_appointments
        );
    }

    // Progress tracking
    public function getTotalRecommendedSessionsAttribute(): int
    {
        return $this->items->sum('recommended_sessions');
    }

    public function getTotalCompletedSessionsAttribute(): int
    {
        return $this->items->sum('completed_sessions');
    }

    public function getProgressPercentageAttribute(): float
    {
        $total = $this->total_recommended_sessions;
        if ($total <= 0) {
            return 0;
        }
        return round(($this->total_completed_sessions / $total) * 100, 1);
    }

    public function getTotalScheduledSessionsAttribute(): int
    {
        return $this->items->sum('scheduled_sessions_count');
    }

    public function getItemsNeedingSchedulingAttribute()
    {
        return $this->items->filter(function ($item) {
            return $item->remaining_sessions > 0 && !$item->isCompleted() && !$item->isCancelled();
        });
    }

    public function getNextScheduledAppointmentAttribute(): ?Appointment
    {
        return Appointment::whereHas('treatmentPlanAppointment', function ($query) {
            $query->whereHas('item', function ($q) {
                $q->where('treatment_plan_id', $this->id);
            });
        })
        ->where('date', '>=', today())
        ->whereIn('status', [Appointment::STATUS_SCHEDULED, Appointment::STATUS_CONFIRMED])
        ->orderBy('date')
        ->orderBy('start_time')
        ->first();
    }

    public function hasPackageSubscription(): bool
    {
        return !is_null($this->package_subscription_id);
    }

    public function hasRecommendedPackage(): bool
    {
        return !is_null($this->recommended_package_id);
    }

    // State checks
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPaused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_PAUSED]);
    }

    // State transitions
    public function canTransitionTo(string $status): bool
    {
        $transitions = [
            self::STATUS_DRAFT => [self::STATUS_ACTIVE, self::STATUS_CANCELLED],
            self::STATUS_ACTIVE => [self::STATUS_PAUSED, self::STATUS_COMPLETED, self::STATUS_CANCELLED],
            self::STATUS_PAUSED => [self::STATUS_ACTIVE, self::STATUS_CANCELLED],
            self::STATUS_COMPLETED => [],
            self::STATUS_CANCELLED => [],
        ];

        return in_array($status, $transitions[$this->status] ?? []);
    }

    public function transitionTo(string $status): bool
    {
        if (!$this->canTransitionTo($status)) {
            return false;
        }

        $this->status = $status;

        switch ($status) {
            case self::STATUS_ACTIVE:
                $this->activated_at = now();
                if (empty($this->start_date)) {
                    $this->start_date = today();
                }
                break;
            case self::STATUS_PAUSED:
                $this->paused_at = now();
                break;
            case self::STATUS_COMPLETED:
                $this->completed_at = now();
                $this->actual_end_date = today();
                break;
            case self::STATUS_CANCELLED:
                $this->cancelled_at = now();
                break;
        }

        return $this->save();
    }

    public function activate(): bool
    {
        return $this->transitionTo(self::STATUS_ACTIVE);
    }

    public function pause(): bool
    {
        return $this->transitionTo(self::STATUS_PAUSED);
    }

    public function resume(): bool
    {
        return $this->transitionTo(self::STATUS_ACTIVE);
    }

    public function complete(): bool
    {
        return $this->transitionTo(self::STATUS_COMPLETED);
    }

    public function cancel(?string $reason = null): bool
    {
        if (!$this->canTransitionTo(self::STATUS_CANCELLED)) {
            return false;
        }

        $this->cancellation_reason = $reason;
        return $this->transitionTo(self::STATUS_CANCELLED);
    }

    /**
     * Check if all sessions are completed and auto-complete the plan
     */
    public function checkAndMarkComplete(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        $allItemsComplete = $this->items->every(function ($item) {
            return $item->isCompleted() || $item->isCancelled();
        });

        if ($allItemsComplete && config('treatment_plans.auto_complete_on_all_sessions', true)) {
            return $this->complete();
        }

        return false;
    }

    /**
     * Link a package subscription to this treatment plan
     */
    public function linkPackageSubscription(PackageSubscription $subscription): bool
    {
        $this->package_subscription_id = $subscription->id;
        return $this->save();
    }

    // Accessors
    public function getTranslatedNameAttribute(): string
    {
        return $this->getTranslation('name', app()->getLocale())
            ?? $this->getTranslation('name', 'en')
            ?? '';
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

    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->target_end_date) {
            return null;
        }
        return max(0, now()->diffInDays($this->target_end_date, false));
    }

    public function getDurationDaysAttribute(): ?int
    {
        if (!$this->start_date) {
            return null;
        }
        $endDate = $this->actual_end_date ?? $this->target_end_date ?? now();
        return $this->start_date->diffInDays($endDate);
    }

    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePaused($query)
    {
        return $query->where('status', self::STATUS_PAUSED);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeInProgress($query)
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_PAUSED]);
    }

    public function scopeForPatient($query, string $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeForBranch($query, string $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeCreatedBy($query, string $userId)
    {
        return $query->where('created_by_user_id', $userId);
    }

    public function scopeWithProgress($query)
    {
        return $query->withSum('items', 'recommended_sessions')
            ->withSum('items', 'completed_sessions');
    }

    public function scopeOrdered($query)
    {
        return $query->orderByDesc('created_at');
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('code', 'ilike', "%{$term}%")
                ->orWhereRaw("name->>'en' ILIKE ?", ["%{$term}%"])
                ->orWhereRaw("name->>'ar' ILIKE ?", ["%{$term}%"])
                ->orWhereHas('patient', function ($pq) use ($term) {
                    $pq->where('first_name', 'ilike', "%{$term}%")
                        ->orWhere('last_name', 'ilike', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
        });
    }

    // Financial methods
    /**
     * Recalculate all financial totals for this treatment plan
     */
    public function recalculateFinancials(): void
    {
        // Total value from items
        $this->total_value_minor = $this->items()->sum('total_minor');

        // Total deposits (unassigned payments linked to this plan)
        $this->total_deposits_minor = $this->payments()
            ->whereNull('invoice_id')
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount_minor');

        // Total invoiced (non-cancelled/draft invoices)
        $this->total_invoiced_minor = $this->invoices()
            ->whereNotIn('status', [Invoice::STATUS_CANCELLED, Invoice::STATUS_DRAFT])
            ->sum('total_minor');

        // Total paid (payments on invoices + deposits applied)
        $this->total_paid_minor = $this->invoices()
            ->whereNotIn('status', [Invoice::STATUS_CANCELLED, Invoice::STATUS_DRAFT])
            ->sum(\DB::raw('paid_minor + deposits_applied_minor'));

        // Balance
        $this->balance_minor = $this->total_value_minor - $this->total_paid_minor;

        $this->save();
    }

    /**
     * Get available deposits (unassigned payments) that can be applied to invoices
     */
    public function getAvailableDepositsAttribute()
    {
        return $this->unassignedPayments()->get();
    }

    /**
     * Get total available deposits amount
     */
    public function getAvailableDepositsAmountAttribute(): int
    {
        return $this->unassignedPayments()->sum('amount_minor');
    }

    /**
     * Get financial summary
     */
    public function getFinancialSummaryAttribute(): array
    {
        return [
            'total_value' => $this->total_value_minor / 100,
            'total_deposits' => $this->total_deposits_minor / 100,
            'total_invoiced' => $this->total_invoiced_minor / 100,
            'total_paid' => $this->total_paid_minor / 100,
            'balance' => $this->balance_minor / 100,
            'available_deposits' => $this->available_deposits_amount / 100,
        ];
    }

    /**
     * Format minor amount for display
     */
    public function formatAmount(int $amountMinor): string
    {
        return number_format($amountMinor / 100, 2);
    }

    public function getFormattedTotalValueAttribute(): string
    {
        return $this->formatAmount($this->total_value_minor ?? 0);
    }

    public function getFormattedDepositsAttribute(): string
    {
        return $this->formatAmount($this->total_deposits_minor ?? 0);
    }

    public function getFormattedInvoicedAttribute(): string
    {
        return $this->formatAmount($this->total_invoiced_minor ?? 0);
    }

    public function getFormattedPaidAttribute(): string
    {
        return $this->formatAmount($this->total_paid_minor ?? 0);
    }

    public function getFormattedBalanceAttribute(): string
    {
        return $this->formatAmount($this->balance_minor ?? 0);
    }
}
