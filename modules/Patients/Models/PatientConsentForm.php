<?php

namespace Modules\Patients\Models;

use XLinic\Framework\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientConsentForm extends BaseModel
{
    /**
     * The table associated with the model.
     */
    protected $table = 'patient_consent_forms';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'patient_id',
        'consent_template_id',
        'template_version',
        'signed_at',
        'signed_by_patient',
        'signature_data',
        'signature_type',
        'witness_name',
        'witness_signature_data',
        'ip_address',
        'user_agent',
        'valid_until',
        'revoked_at',
        'revoked_reason',
        'pdf_path',
        'notes',
        'staff_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'signed_at' => 'datetime',
        'signed_by_patient' => 'boolean',
        'valid_until' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * Signature types.
     */
    public const SIGNATURE_TYPES = [
        'drawn' => 'Drawn Signature',
        'typed' => 'Typed Name',
        'digital' => 'Digital Certificate',
    ];

    /**
     * Get the patient.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the consent template.
     */
    public function consentTemplate(): BelongsTo
    {
        return $this->belongsTo(\Modules\Treatments\Models\ConsentTemplate::class, 'consent_template_id');
    }

    /**
     * Get the staff member who witnessed/collected the consent.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\Models\User::class, 'staff_id');
    }

    /**
     * Check if consent is still valid.
     */
    public function isValid(): bool
    {
        // Check if revoked
        if ($this->revoked_at) {
            return false;
        }

        // Check expiration
        if ($this->valid_until && $this->valid_until->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if consent is expired.
     */
    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast();
    }

    /**
     * Check if consent is revoked.
     */
    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Revoke the consent.
     */
    public function revoke(string $reason = null): void
    {
        $this->update([
            'revoked_at' => now(),
            'revoked_reason' => $reason,
        ]);
    }

    /**
     * Get status.
     */
    public function getStatusAttribute(): string
    {
        if ($this->isRevoked()) {
            return 'revoked';
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        return 'valid';
    }

    /**
     * Get status color for UI.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'valid' => 'success',
            'expired' => 'warning',
            'revoked' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Scope: Valid consents only.
     */
    public function scopeValid($query)
    {
        return $query->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>', now());
            });
    }

    /**
     * Scope: By template.
     */
    public function scopeForTemplate($query, string $templateId)
    {
        return $query->where('consent_template_id', $templateId);
    }

    /**
     * Scope: Expiring soon (within X days).
     */
    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->whereNotNull('valid_until')
            ->whereNull('revoked_at')
            ->whereBetween('valid_until', [now(), now()->addDays($days)]);
    }
}
