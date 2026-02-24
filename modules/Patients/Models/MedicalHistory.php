<?php

namespace Modules\Patients\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicalHistory extends BaseModel
{
    use HasTenancy, SoftDeletes;

    protected $table = 'medical_histories';

    protected $fillable = [
        'tenant_id',
        'medical_profile_id',
        'history_type',
        'name',
        'description',
        'onset_date',
        'resolved_date',
        'is_ongoing',
        'severity',
        'family_relationship',
        'frequency',
        'quantity',
        'affects_treatment',
        'treatment_implications',
        'verified_by_doctor',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'onset_date' => 'date',
        'resolved_date' => 'date',
        'is_ongoing' => 'boolean',
        'affects_treatment' => 'boolean',
        'verified_by_doctor' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public const HISTORY_TYPES = [
        'medical_condition' => 'Medical Condition',
        'surgery' => 'Surgery',
        'hospitalization' => 'Hospitalization',
        'family_history' => 'Family History',
        'social_history' => 'Social History',
    ];

    public const SEVERITIES = [
        'mild' => 'Mild',
        'moderate' => 'Moderate',
        'severe' => 'Severe',
    ];

    public const FAMILY_RELATIONSHIPS = [
        'mother' => 'Mother',
        'father' => 'Father',
        'sibling' => 'Sibling',
        'grandparent' => 'Grandparent',
        'aunt_uncle' => 'Aunt/Uncle',
        'other' => 'Other',
    ];

    // Relationships
    public function medicalProfile(): BelongsTo
    {
        return $this->belongsTo(MedicalProfile::class);
    }

    public function verifiedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'verified_by');
    }

    // Accessors
    public function getHistoryTypeNameAttribute(): string
    {
        return self::HISTORY_TYPES[$this->history_type] ?? $this->history_type;
    }

    // Scopes
    public function scopeOfType($query, string $type)
    {
        return $query->where('history_type', $type);
    }

    public function scopeMedicalConditions($query)
    {
        return $query->where('history_type', 'medical_condition');
    }

    public function scopeSurgeries($query)
    {
        return $query->where('history_type', 'surgery');
    }

    public function scopeFamilyHistory($query)
    {
        return $query->where('history_type', 'family_history');
    }

    public function scopeOngoing($query)
    {
        return $query->where('is_ongoing', true);
    }

    public function scopeResolved($query)
    {
        return $query->where('is_ongoing', false);
    }

    public function scopeAffectsTreatment($query)
    {
        return $query->where('affects_treatment', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('verified_by_doctor', true);
    }

    // Helper Methods
    public function markAsVerified(?string $userId = null): void
    {
        $this->update([
            'verified_by_doctor' => true,
            'verified_by' => $userId ?? auth()->id(),
            'verified_at' => now(),
        ]);
    }
}
