<?php

namespace Modules\Patients\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicalAllergy extends BaseModel
{
    use HasTenancy, SoftDeletes;

    protected $table = 'medical_allergies';

    protected $fillable = [
        'tenant_id',
        'medical_profile_id',
        'allergy_type',
        'allergen',
        'severity',
        'reaction',
        'discovered_date',
        'is_confirmed',
        'notes',
        'show_alert',
    ];

    protected $casts = [
        'discovered_date' => 'date',
        'is_confirmed' => 'boolean',
        'show_alert' => 'boolean',
    ];

    public const ALLERGY_TYPES = [
        'drug' => 'Drug/Medication',
        'food' => 'Food',
        'environmental' => 'Environmental',
        'topical' => 'Topical/Skincare',
        'metal' => 'Metal',
        'latex' => 'Latex',
        'other' => 'Other',
    ];

    public const SEVERITIES = [
        'mild' => 'Mild',
        'moderate' => 'Moderate',
        'severe' => 'Severe',
        'life_threatening' => 'Life Threatening',
    ];

    public const SEVERITY_COLORS = [
        'mild' => 'info',
        'moderate' => 'warning',
        'severe' => 'danger',
        'life_threatening' => 'danger',
    ];

    // Relationships
    public function medicalProfile(): BelongsTo
    {
        return $this->belongsTo(MedicalProfile::class);
    }

    // Accessors
    public function getSeverityColorAttribute(): string
    {
        return self::SEVERITY_COLORS[$this->severity] ?? 'gray';
    }

    public function getAllergyTypeNameAttribute(): string
    {
        return self::ALLERGY_TYPES[$this->allergy_type] ?? $this->allergy_type;
    }

    // Scopes
    public function scopeOfType($query, string $type)
    {
        return $query->where('allergy_type', $type);
    }

    public function scopeCritical($query)
    {
        return $query->whereIn('severity', ['severe', 'life_threatening']);
    }

    public function scopeLifeThreatening($query)
    {
        return $query->where('severity', 'life_threatening');
    }

    public function scopeWithAlerts($query)
    {
        return $query->where('show_alert', true);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('is_confirmed', true);
    }

    // Helper Methods
    public function isCritical(): bool
    {
        return in_array($this->severity, ['severe', 'life_threatening']);
    }

    public function isLifeThreatening(): bool
    {
        return $this->severity === 'life_threatening';
    }
}
