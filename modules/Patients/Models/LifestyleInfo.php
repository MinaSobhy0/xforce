<?php

namespace Modules\Patients\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LifestyleInfo extends BaseModel
{
    use HasTenancy, SoftDeletes;

    protected $table = 'lifestyle_info';

    protected $fillable = [
        'tenant_id',
        'medical_profile_id',
        'smoking_status',
        'smoking_frequency',
        'smoking_years',
        'smoking_quit_date',
        'alcohol_status',
        'alcohol_frequency',
        'exercise_level',
        'exercise_details',
        'sun_exposure_level',
        'uses_sunscreen',
        'uses_tanning_beds',
        'sleep_hours',
        'sleep_issues',
        'diet_type',
        'dietary_restrictions',
        'occupational_exposures',
    ];

    protected $casts = [
        'smoking_years' => 'integer',
        'smoking_quit_date' => 'date',
        'uses_sunscreen' => 'boolean',
        'uses_tanning_beds' => 'boolean',
        'sleep_hours' => 'integer',
        'sleep_issues' => 'boolean',
    ];

    public const SMOKING_STATUS = [
        'never' => 'Never Smoked',
        'former' => 'Former Smoker',
        'current' => 'Current Smoker',
    ];

    public const ALCOHOL_STATUS = [
        'never' => 'Never',
        'occasional' => 'Occasional',
        'regular' => 'Regular',
        'heavy' => 'Heavy',
    ];

    public const EXERCISE_LEVELS = [
        'sedentary' => 'Sedentary',
        'light' => 'Light (1-2 days/week)',
        'moderate' => 'Moderate (3-4 days/week)',
        'active' => 'Active (5+ days/week)',
        'very_active' => 'Very Active (Daily)',
    ];

    public const SUN_EXPOSURE_LEVELS = [
        'minimal' => 'Minimal',
        'moderate' => 'Moderate',
        'frequent' => 'Frequent',
        'excessive' => 'Excessive',
    ];

    // Relationships
    public function medicalProfile(): BelongsTo
    {
        return $this->belongsTo(MedicalProfile::class);
    }

    // Helper Methods
    public function isSmoker(): bool
    {
        return $this->smoking_status === 'current';
    }

    public function isFormerSmoker(): bool
    {
        return $this->smoking_status === 'former';
    }

    public function hasHighSunExposure(): bool
    {
        return in_array($this->sun_exposure_level, ['frequent', 'excessive']);
    }

    public function usesTanningBeds(): bool
    {
        return $this->uses_tanning_beds ?? false;
    }

    public function getTreatmentRiskFactors(): array
    {
        $risks = [];

        if ($this->isSmoker()) {
            $risks[] = [
                'type' => 'warning',
                'message' => 'Current smoker - may affect healing and treatment outcomes',
            ];
        }

        if ($this->hasHighSunExposure()) {
            $risks[] = [
                'type' => 'warning',
                'message' => 'High sun exposure - increased sensitivity risk',
            ];
        }

        if ($this->usesTanningBeds()) {
            $risks[] = [
                'type' => 'danger',
                'message' => 'Uses tanning beds - evaluate sun damage before treatment',
            ];
        }

        if ($this->alcohol_status === 'heavy') {
            $risks[] = [
                'type' => 'warning',
                'message' => 'Heavy alcohol use - may affect healing',
            ];
        }

        return $risks;
    }
}
