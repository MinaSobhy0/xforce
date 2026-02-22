<?php

namespace Modules\TreatmentPlans\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Booking\Models\Appointment;

class TreatmentPlanAppointment extends BaseModel
{
    use HasTenancy;

    protected $fillable = [
        'tenant_id',
        'treatment_plan_item_id',
        'appointment_id',
        'session_number',
        'status',
        'notes',
    ];

    protected $casts = [
        'session_number' => 'integer',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (TreatmentPlanAppointment $planAppointment) {
            // Auto-assign session number if not provided
            if (empty($planAppointment->session_number)) {
                $planAppointment->session_number = $planAppointment->item?->next_session_number ?? 1;
            }

            // Copy status from appointment
            if (empty($planAppointment->status)) {
                $planAppointment->status = $planAppointment->appointment?->status ?? 'scheduled';
            }
        });
    }

    // Relationships
    public function item(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlanItem::class, 'treatment_plan_item_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    // Accessors
    public function getTreatmentPlanAttribute(): ?TreatmentPlan
    {
        return $this->item?->treatmentPlan;
    }

    public function getSessionDisplayAttribute(): string
    {
        $total = $this->item?->recommended_sessions ?? 0;
        return "Session {$this->session_number} of {$total}";
    }

    public function getStatusLabelAttribute(): string
    {
        return Appointment::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return Appointment::STATUS_COLORS[$this->status] ?? 'gray';
    }

    // State checks
    public function isScheduled(): bool
    {
        return in_array($this->status, [
            Appointment::STATUS_SCHEDULED,
            Appointment::STATUS_CONFIRMED,
        ]);
    }

    public function isCompleted(): bool
    {
        return $this->status === Appointment::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === Appointment::STATUS_CANCELLED;
    }

    public function isNoShow(): bool
    {
        return $this->status === Appointment::STATUS_NO_SHOW;
    }

    /**
     * Sync status from the linked appointment
     */
    public function syncStatusFromAppointment(): bool
    {
        if (!$this->appointment) {
            return false;
        }

        $oldStatus = $this->status;
        $this->status = $this->appointment->status;

        if ($this->status !== $oldStatus) {
            return $this->save();
        }

        return true;
    }

    // Scopes
    public function scopeForItem($query, string $itemId)
    {
        return $query->where('treatment_plan_item_id', $itemId);
    }

    public function scopeScheduled($query)
    {
        return $query->whereIn('status', [
            Appointment::STATUS_SCHEDULED,
            Appointment::STATUS_CONFIRMED,
        ]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', Appointment::STATUS_COMPLETED);
    }

    public function scopeUpcoming($query)
    {
        return $query->whereHas('appointment', function ($q) {
            $q->where('date', '>=', today())
                ->whereIn('status', [
                    Appointment::STATUS_SCHEDULED,
                    Appointment::STATUS_CONFIRMED,
                ]);
        });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('session_number');
    }
}
