<?php

namespace Modules\Patients\Models;

use XLinic\Framework\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientNote extends BaseModel
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'patient_notes';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'patient_id',
        'appointment_id',
        'type',
        'subject',
        'content',
        'is_pinned',
        'is_private',
        'is_alert',
        'alert_until',
        'created_by',
        'attachments',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_pinned' => 'boolean',
        'is_private' => 'boolean',
        'is_alert' => 'boolean',
        'alert_until' => 'datetime',
        'attachments' => 'array',
    ];

    /**
     * Note types.
     */
    public const TYPES = [
        'clinical' => 'Clinical Note',
        'administrative' => 'Administrative Note',
        'follow_up' => 'Follow-up Note',
        'complaint' => 'Complaint',
        'consultation' => 'Consultation Note',
        'treatment' => 'Treatment Note',
        'prescription' => 'Prescription',
        'referral' => 'Referral Note',
    ];

    /**
     * Get the patient.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the appointment (if linked).
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(\Modules\Booking\Models\Appointment::class);
    }

    /**
     * Get the user who created the note.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\Models\User::class, 'created_by');
    }

    /**
     * Get type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    /**
     * Get type color for UI.
     */
    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            'clinical' => 'info',
            'administrative' => 'gray',
            'follow_up' => 'warning',
            'complaint' => 'danger',
            'consultation' => 'primary',
            'treatment' => 'success',
            'prescription' => 'purple',
            'referral' => 'pink',
            default => 'gray',
        };
    }

    /**
     * Check if alert is active.
     */
    public function isAlertActive(): bool
    {
        if (!$this->is_alert) {
            return false;
        }

        if ($this->alert_until && $this->alert_until->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Scope: Pinned notes.
     */
    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    /**
     * Scope: Active alerts.
     */
    public function scopeActiveAlerts($query)
    {
        return $query->where('is_alert', true)
            ->where(function ($q) {
                $q->whereNull('alert_until')
                    ->orWhere('alert_until', '>', now());
            });
    }

    /**
     * Scope: By type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Clinical notes only.
     */
    public function scopeClinical($query)
    {
        return $query->where('type', 'clinical');
    }

    /**
     * Scope: Public notes (visible to all staff).
     */
    public function scopePublic($query)
    {
        return $query->where('is_private', false);
    }

    /**
     * Scope: Recent notes.
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get short preview of content.
     */
    public function getPreviewAttribute(): string
    {
        return \Illuminate\Support\Str::limit(strip_tags($this->content), 150);
    }

    /**
     * Pin the note.
     */
    public function pin(): void
    {
        $this->update(['is_pinned' => true]);
    }

    /**
     * Unpin the note.
     */
    public function unpin(): void
    {
        $this->update(['is_pinned' => false]);
    }

    /**
     * Set as alert.
     */
    public function setAlert(\Carbon\Carbon $until = null): void
    {
        $this->update([
            'is_alert' => true,
            'alert_until' => $until,
        ]);
    }

    /**
     * Clear alert.
     */
    public function clearAlert(): void
    {
        $this->update([
            'is_alert' => false,
            'alert_until' => null,
        ]);
    }
}
