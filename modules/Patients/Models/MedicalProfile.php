<?php

namespace Modules\Patients\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicalProfile extends BaseModel
{
    use HasTenancy, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'blood_type',
        'is_pregnant',
        'is_breastfeeding',
        'fitzpatrick_type',
        'insurance_info',
        'status',
        'profile_created_at',
        'last_reviewed_at',
        'reviewed_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'insurance_info' => 'array',
        'is_pregnant' => 'boolean',
        'is_breastfeeding' => 'boolean',
        'fitzpatrick_type' => 'integer',
        'profile_created_at' => 'datetime',
        'last_reviewed_at' => 'datetime',
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_TRANSFERRED = 'transferred';

    public const BLOOD_TYPES = [
        'A+' => 'A+',
        'A-' => 'A-',
        'B+' => 'B+',
        'B-' => 'B-',
        'AB+' => 'AB+',
        'AB-' => 'AB-',
        'O+' => 'O+',
        'O-' => 'O-',
    ];

    public const FITZPATRICK_TYPES = [
        1 => 'Type I - Very fair skin, always burns, never tans',
        2 => 'Type II - Fair skin, burns easily, tans minimally',
        3 => 'Type III - Medium skin, sometimes burns, tans gradually',
        4 => 'Type IV - Olive skin, rarely burns, tans easily',
        5 => 'Type V - Brown skin, very rarely burns, tans very easily',
        6 => 'Type VI - Dark brown/black skin, never burns, tans very easily',
    ];

    public const FITZPATRICK_SHORT = [
        1 => 'Type I',
        2 => 'Type II',
        3 => 'Type III',
        4 => 'Type IV',
        5 => 'Type V',
        6 => 'Type VI',
    ];

    // Relationships
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewed_by');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }

    public function allergies(): HasMany
    {
        return $this->hasMany(MedicalAllergy::class);
    }

    public function medications(): HasMany
    {
        return $this->hasMany(MedicalMedication::class);
    }

    public function contraindications(): HasMany
    {
        return $this->hasMany(MedicalContraindication::class);
    }

    public function medicalHistories(): HasMany
    {
        return $this->hasMany(MedicalHistory::class);
    }

    public function skinAssessments(): HasMany
    {
        return $this->hasMany(SkinAssessment::class);
    }

    public function lifestyleInfo(): HasOne
    {
        return $this->hasOne(LifestyleInfo::class);
    }

    // Accessors
    public function getFitzpatrickDescriptionAttribute(): ?string
    {
        return self::FITZPATRICK_TYPES[$this->fitzpatrick_type] ?? null;
    }

    public function getFitzpatrickShortAttribute(): ?string
    {
        return self::FITZPATRICK_SHORT[$this->fitzpatrick_type] ?? null;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeNeedsReview($query, int $months = 6)
    {
        return $query->where(function ($q) use ($months) {
            $q->whereNull('last_reviewed_at')
              ->orWhere('last_reviewed_at', '<', now()->subMonths($months));
        });
    }

    // Helper Methods
    public function hasCriticalAllergies(): bool
    {
        return $this->allergies()
            ->whereIn('severity', ['severe', 'life_threatening'])
            ->exists();
    }

    public function getCriticalAllergies()
    {
        return $this->allergies()
            ->whereIn('severity', ['severe', 'life_threatening'])
            ->get();
    }

    public function getActiveAllergies()
    {
        return $this->allergies()
            ->where('show_alert', true)
            ->get();
    }

    public function getActiveContraindications()
    {
        return $this->contraindications()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            })
            ->get();
    }

    public function getBlockingContraindications()
    {
        return $this->contraindications()
            ->where('is_active', true)
            ->where('block_booking', true)
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            })
            ->get();
    }

    public function hasActiveContraindicationsForService(string $serviceId): bool
    {
        return $this->contraindications()
            ->where('is_active', true)
            ->where(function ($q) use ($serviceId) {
                $q->whereNull('affected_services')
                  ->orWhereJsonContains('affected_services', $serviceId);
            })
            ->exists();
    }

    public function getOngoingMedications()
    {
        return $this->medications()
            ->where('is_ongoing', true)
            ->get();
    }

    public function getOngoingConditions()
    {
        return $this->medicalHistories()
            ->where('history_type', 'medical_condition')
            ->where('is_ongoing', true)
            ->get();
    }

    public function getLatestSkinAssessment(): ?SkinAssessment
    {
        return $this->skinAssessments()
            ->orderByDesc('created_at')
            ->first();
    }

    public function needsReview(int $months = 6): bool
    {
        if (!$this->last_reviewed_at) {
            return true;
        }
        return $this->last_reviewed_at->lt(now()->subMonths($months));
    }

    public function markAsReviewed(?string $userId = null): void
    {
        $this->update([
            'last_reviewed_at' => now(),
            'reviewed_by' => $userId ?? auth()->id(),
        ]);
    }

    public function hasAlerts(): bool
    {
        return $this->hasCriticalAllergies() ||
               $this->getActiveContraindications()->isNotEmpty() ||
               $this->is_pregnant ||
               $this->is_breastfeeding;
    }

    public static function getOrCreateForPatient(string $patientId): self
    {
        $patient = Patient::findOrFail($patientId);

        return self::firstOrCreate(
            ['patient_id' => $patientId],
            [
                'tenant_id' => $patient->tenant_id,
                'status' => self::STATUS_ACTIVE,
                'profile_created_at' => now(),
                'created_by' => auth()->id(),
            ]
        );
    }
}
