<?php

namespace Modules\Patients\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicalContraindication extends BaseModel
{
    use HasTenancy, SoftDeletes;

    protected $table = 'medical_contraindications';

    protected $fillable = [
        'tenant_id',
        'medical_profile_id',
        'contraindication_type',
        'name',
        'description',
        'affected_services',
        'start_date',
        'end_date',
        'is_active',
        'source',
        'identified_by',
        'identified_at',
        'show_booking_alert',
        'block_booking',
    ];

    protected $casts = [
        'affected_services' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'identified_at' => 'datetime',
        'show_booking_alert' => 'boolean',
        'block_booking' => 'boolean',
    ];

    public const TYPES = [
        'absolute' => 'Absolute (Treatment prohibited)',
        'relative' => 'Relative (Treatment with caution)',
        'temporary' => 'Temporary (Time-limited)',
    ];

    public const TYPE_COLORS = [
        'absolute' => 'danger',
        'relative' => 'warning',
        'temporary' => 'info',
    ];

    public const SOURCES = [
        'patient_reported' => 'Patient Reported',
        'doctor_identified' => 'Doctor Identified',
        'system_derived' => 'System Derived',
    ];

    // Relationships
    public function medicalProfile(): BelongsTo
    {
        return $this->belongsTo(MedicalProfile::class);
    }

    public function identifiedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'identified_by');
    }

    // Accessors
    public function getTypeColorAttribute(): string
    {
        return self::TYPE_COLORS[$this->contraindication_type] ?? 'gray';
    }

    public function getTypeNameAttribute(): string
    {
        return self::TYPES[$this->contraindication_type] ?? $this->contraindication_type;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            });
    }

    public function scopeAbsolute($query)
    {
        return $query->where('contraindication_type', 'absolute');
    }

    public function scopeRelative($query)
    {
        return $query->where('contraindication_type', 'relative');
    }

    public function scopeTemporary($query)
    {
        return $query->where('contraindication_type', 'temporary');
    }

    public function scopeBlocking($query)
    {
        return $query->where('block_booking', true);
    }

    public function scopeWithAlerts($query)
    {
        return $query->where('show_booking_alert', true);
    }

    public function scopeForService($query, string $serviceId)
    {
        return $query->where(function ($q) use ($serviceId) {
            $q->whereNull('affected_services')
              ->orWhereJsonContains('affected_services', $serviceId);
        });
    }

    // Helper Methods
    public function isAbsolute(): bool
    {
        return $this->contraindication_type === 'absolute';
    }

    public function isTemporary(): bool
    {
        return $this->contraindication_type === 'temporary';
    }

    public function hasExpired(): bool
    {
        if (!$this->end_date) {
            return false;
        }
        return $this->end_date->lt(now());
    }

    public function affectsService(string $serviceId): bool
    {
        if (empty($this->affected_services)) {
            return true; // Affects all services
        }
        return in_array($serviceId, $this->affected_services);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function reactivate(): void
    {
        $this->update(['is_active' => true]);
    }
}
