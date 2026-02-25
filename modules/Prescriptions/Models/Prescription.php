<?php

namespace Modules\Prescriptions\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use XLinic\Framework\Core\Model\Traits\HasSequence;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\Models\User;
use Modules\Core\Models\Branch;
use Modules\Patients\Models\Patient;
use Modules\Booking\Models\Appointment;

class Prescription extends BaseModel
{
    use HasTenancy, HasActivity, HasSequence, SoftDeletes;

    protected string $sequenceCode = 'prescription';
    protected string $sequenceColumn = 'prescription_number';

    protected $fillable = [
        'tenant_id',
        'prescription_number',
        'patient_id',
        'prescriber_id',
        'appointment_id',
        'branch_id',
        'diagnosis',
        'notes',
        'status',
        'issued_at',
        'valid_until',
        'is_printed',
        'print_count',
        'last_printed_at',
        'finalized_by',
        'finalized_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'valid_until' => 'date',
        'is_printed' => 'boolean',
        'print_count' => 'integer',
        'last_printed_at' => 'datetime',
        'finalized_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_FINALIZED = 'finalized';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_FINALIZED => 'Finalized',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const STATUS_COLORS = [
        self::STATUS_DRAFT => 'gray',
        self::STATUS_FINALIZED => 'success',
        self::STATUS_CANCELLED => 'danger',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Prescription $prescription) {
            if (empty($prescription->status)) {
                $prescription->status = self::STATUS_DRAFT;
            }
            if (empty($prescription->valid_until)) {
                $validityDays = config('prescriptions.default_validity_days', 30);
                $prescription->valid_until = now()->addDays($validityDays);
            }
        });
    }

    // Relationships
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function prescriber(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prescriber_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class)->orderBy('sort_order');
    }

    // Computed attributes
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    public function getIsExpiredAttribute(): bool
    {
        if (!$this->valid_until) {
            return false;
        }
        return $this->valid_until->isPast();
    }

    public function getIsValidAttribute(): bool
    {
        return $this->isFinalized() && !$this->is_expired;
    }

    public function getMedicationCountAttribute(): int
    {
        return $this->items()->count();
    }

    // State machine
    public function canTransitionTo(string $status): bool
    {
        $transitions = [
            self::STATUS_DRAFT => [self::STATUS_FINALIZED, self::STATUS_CANCELLED],
            self::STATUS_FINALIZED => [self::STATUS_CANCELLED],
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

        return $this->save();
    }

    // State actions
    public function finalize(): bool
    {
        if (!$this->canTransitionTo(self::STATUS_FINALIZED)) {
            return false;
        }

        $this->status = self::STATUS_FINALIZED;
        $this->finalized_by = auth()->id();
        $this->finalized_at = now();
        $this->issued_at = now();

        return $this->save();
    }

    public function cancel(?string $reason = null): bool
    {
        if (!$this->canTransitionTo(self::STATUS_CANCELLED)) {
            return false;
        }

        $this->status = self::STATUS_CANCELLED;
        $this->cancelled_by = auth()->id();
        $this->cancelled_at = now();

        if ($reason) {
            $this->cancellation_reason = $reason;
        }

        return $this->save();
    }

    public function markPrinted(): void
    {
        $this->increment('print_count');
        $this->update([
            'is_printed' => true,
            'last_printed_at' => now(),
        ]);
    }

    // State checks
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_FINALIZED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    public function canPrint(): bool
    {
        return $this->isFinalized();
    }

    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeFinalized($query)
    {
        return $query->where('status', self::STATUS_FINALIZED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_FINALIZED]);
    }

    public function scopeValid($query)
    {
        return $query->where('status', self::STATUS_FINALIZED)
            ->where(function ($q) {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', today());
            });
    }

    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_FINALIZED)
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', today());
    }

    public function scopeForPatient($query, string $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeForPrescriber($query, string $prescriberId)
    {
        return $query->where('prescriber_id', $prescriberId);
    }

    public function scopeForBranch($query, string $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeForAppointment($query, string $appointmentId)
    {
        return $query->where('appointment_id', $appointmentId);
    }

    public function scopeForDateRange($query, $start, $end)
    {
        return $query->whereBetween('issued_at', [$start, $end]);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('prescription_number', 'ilike', "%{$term}%")
                ->orWhere('diagnosis', 'ilike', "%{$term}%")
                ->orWhereHas('patient', function ($pq) use ($term) {
                    $pq->where('first_name', 'ilike', "%{$term}%")
                        ->orWhere('last_name', 'ilike', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                })
                ->orWhereHas('items', function ($iq) use ($term) {
                    $iq->where('medication_name', 'ilike', "%{$term}%")
                        ->orWhere('generic_name', 'ilike', "%{$term}%");
                });
        });
    }
}
