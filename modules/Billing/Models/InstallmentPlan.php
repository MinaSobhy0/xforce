<?php

namespace Modules\Billing\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class InstallmentPlan extends BaseModel
{
    use HasTenancy;

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'total_installments',
        'installment_amount_minor',
        'frequency',
        'start_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'total_installments' => 'integer',
        'installment_amount_minor' => 'integer',
        'start_date' => 'date',
    ];

    // Frequency constants
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_BIWEEKLY = 'biweekly';
    public const FREQUENCY_MONTHLY = 'monthly';

    public const FREQUENCIES = [
        self::FREQUENCY_WEEKLY => 'Weekly',
        self::FREQUENCY_BIWEEKLY => 'Bi-weekly',
        self::FREQUENCY_MONTHLY => 'Monthly',
    ];

    // Status constants
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_DEFAULTED = 'defaulted';

    public const STATUSES = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_DEFAULTED => 'Defaulted',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (InstallmentPlan $plan) {
            if (empty($plan->status)) {
                $plan->status = self::STATUS_ACTIVE;
            }
        });

        static::created(function (InstallmentPlan $plan) {
            $plan->generateSchedule();
        });
    }

    // Relationships
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(InstallmentSchedule::class)->orderBy('installment_number');
    }

    // Generate installment schedule
    public function generateSchedule(): void
    {
        $startDate = $this->start_date ?? today();

        for ($i = 1; $i <= $this->total_installments; $i++) {
            $dueDate = $this->calculateDueDate($startDate, $i);

            $this->schedules()->create([
                'tenant_id' => $this->tenant_id,
                'installment_number' => $i,
                'amount_minor' => $this->installment_amount_minor,
                'due_date' => $dueDate,
                'status' => InstallmentSchedule::STATUS_PENDING,
            ]);
        }
    }

    // Calculate due date based on frequency
    protected function calculateDueDate(Carbon $startDate, int $installmentNumber): Carbon
    {
        $periodsToAdd = $installmentNumber - 1;

        return match ($this->frequency) {
            self::FREQUENCY_WEEKLY => $startDate->copy()->addWeeks($periodsToAdd),
            self::FREQUENCY_BIWEEKLY => $startDate->copy()->addWeeks($periodsToAdd * 2),
            self::FREQUENCY_MONTHLY => $startDate->copy()->addMonths($periodsToAdd),
            default => $startDate->copy()->addMonths($periodsToAdd),
        };
    }

    // Computed attributes
    public function getTotalAmountMinorAttribute(): int
    {
        return $this->total_installments * $this->installment_amount_minor;
    }

    public function getPaidAmountMinorAttribute(): int
    {
        return $this->schedules()->where('status', InstallmentSchedule::STATUS_PAID)->sum('amount_minor');
    }

    public function getRemainingAmountMinorAttribute(): int
    {
        return $this->total_amount_minor - $this->paid_amount_minor;
    }

    public function getPaidCountAttribute(): int
    {
        return $this->schedules()->where('status', InstallmentSchedule::STATUS_PAID)->count();
    }

    public function getRemainingCountAttribute(): int
    {
        return $this->total_installments - $this->paid_count;
    }

    public function getNextDueAttribute(): ?InstallmentSchedule
    {
        return $this->schedules()
            ->where('status', InstallmentSchedule::STATUS_PENDING)
            ->orderBy('due_date')
            ->first();
    }

    public function getHasOverdueAttribute(): bool
    {
        return $this->schedules()
            ->where('status', InstallmentSchedule::STATUS_PENDING)
            ->where('due_date', '<', today())
            ->exists();
    }

    // Check if plan is complete
    public function checkCompletion(): void
    {
        if ($this->paid_count >= $this->total_installments) {
            $this->update(['status' => self::STATUS_COMPLETED]);
        }
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeHasOverdue($query)
    {
        return $query->whereHas('schedules', function ($q) {
            $q->where('status', InstallmentSchedule::STATUS_PENDING)
                ->where('due_date', '<', today());
        });
    }
}
