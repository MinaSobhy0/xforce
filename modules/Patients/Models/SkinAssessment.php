<?php

namespace Modules\Patients\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SkinAssessment extends BaseModel
{
    use HasTenancy, SoftDeletes;

    protected $table = 'skin_assessments';

    protected $fillable = [
        'tenant_id',
        'medical_profile_id',
        'appointment_id',
        'assessed_by',
        'fitzpatrick_type',
        'skin_type_oily',
        'skin_sensitivity',
        'skin_texture',
        'pore_size',
        'skin_tone',
        'hydration_level',
        'elasticity_score',
        'pigmentation_level',
        'acne_severity',
        'current_conditions',
        'previous_conditions',
        'aging_signs',
        'aging_level',
        'sun_damage_level',
        'sun_damage_signs',
        'areas_of_concern',
        'patient_goals',
        'clinical_observations',
        'recommendations',
        'treatment_recommendations',
        'notes',
        'assessment_photos',
    ];

    protected $casts = [
        'fitzpatrick_type' => 'integer',
        'hydration_level' => 'integer',
        'elasticity_score' => 'integer',
        'pigmentation_level' => 'integer',
        'acne_severity' => 'integer',
        'current_conditions' => 'array',
        'previous_conditions' => 'array',
        'aging_signs' => 'array',
        'sun_damage_signs' => 'array',
        'areas_of_concern' => 'array',
        'assessment_photos' => 'array',
    ];

    public const FITZPATRICK_TYPES = MedicalProfile::FITZPATRICK_TYPES;

    public const SKIN_OILY_TYPES = [
        'dry' => 'Dry',
        'normal' => 'Normal',
        'oily' => 'Oily',
        'combination' => 'Combination',
    ];

    public const SENSITIVITY_LEVELS = [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'very_high' => 'Very High',
    ];

    public const TEXTURE_LEVELS = [
        'smooth' => 'Smooth',
        'slightly_rough' => 'Slightly Rough',
        'rough' => 'Rough',
        'very_rough' => 'Very Rough',
    ];

    public const PORE_SIZES = [
        'small' => 'Small',
        'medium' => 'Medium',
        'large' => 'Large',
        'very_large' => 'Very Large',
    ];

    public const SKIN_TONE_LEVELS = [
        'even' => 'Even',
        'slightly_uneven' => 'Slightly Uneven',
        'uneven' => 'Uneven',
        'very_uneven' => 'Very Uneven',
    ];

    public const AGING_LEVELS = [
        'none' => 'None',
        'early' => 'Early Signs',
        'moderate' => 'Moderate',
        'advanced' => 'Advanced',
    ];

    public const SUN_DAMAGE_LEVELS = [
        'none' => 'None',
        'mild' => 'Mild',
        'moderate' => 'Moderate',
        'severe' => 'Severe',
    ];

    public const COMMON_CONDITIONS = [
        'acne' => 'Acne',
        'rosacea' => 'Rosacea',
        'eczema' => 'Eczema',
        'psoriasis' => 'Psoriasis',
        'melasma' => 'Melasma',
        'hyperpigmentation' => 'Hyperpigmentation',
        'vitiligo' => 'Vitiligo',
        'scarring' => 'Scarring',
        'seborrheic_dermatitis' => 'Seborrheic Dermatitis',
        'keratosis_pilaris' => 'Keratosis Pilaris',
    ];

    public const AGING_SIGNS = [
        'fine_lines' => 'Fine Lines',
        'wrinkles' => 'Wrinkles',
        'sagging' => 'Sagging',
        'volume_loss' => 'Volume Loss',
        'age_spots' => 'Age Spots',
        'dull_skin' => 'Dull Skin',
        'neck_lines' => 'Neck Lines',
        'crow_feet' => 'Crow\'s Feet',
    ];

    public const AREAS_OF_CONCERN = [
        'face' => 'Face',
        'forehead' => 'Forehead',
        'cheeks' => 'Cheeks',
        'nose' => 'Nose',
        'chin' => 'Chin',
        'neck' => 'Neck',
        'chest' => 'Chest',
        'hands' => 'Hands',
        'back' => 'Back',
    ];

    // Relationships
    public function medicalProfile(): BelongsTo
    {
        return $this->belongsTo(MedicalProfile::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(\Modules\Booking\Models\Appointment::class);
    }

    public function assessedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assessed_by');
    }

    // Accessors
    public function getFitzpatrickDescriptionAttribute(): ?string
    {
        return self::FITZPATRICK_TYPES[$this->fitzpatrick_type] ?? null;
    }

    // Helper Methods
    public function isHighSensitivity(): bool
    {
        return in_array($this->skin_sensitivity, ['high', 'very_high']);
    }

    public function hasAgingSigns(): bool
    {
        return !empty($this->aging_signs) || ($this->aging_level && $this->aging_level !== 'none');
    }

    public function hasSunDamage(): bool
    {
        return $this->sun_damage_level && $this->sun_damage_level !== 'none';
    }

    public function getSummary(): array
    {
        return [
            'fitzpatrick' => $this->fitzpatrick_type,
            'skin_type' => $this->skin_type_oily,
            'sensitivity' => $this->skin_sensitivity,
            'aging_level' => $this->aging_level,
            'sun_damage' => $this->sun_damage_level,
            'conditions' => $this->current_conditions ?? [],
        ];
    }

    public function getTreatmentConsiderations(): array
    {
        $considerations = [];

        if ($this->fitzpatrick_type >= 4) {
            $considerations[] = 'Higher Fitzpatrick type - use lower energy settings';
        }

        if ($this->isHighSensitivity()) {
            $considerations[] = 'High skin sensitivity - perform test patch first';
        }

        if ($this->hasSunDamage() && $this->sun_damage_level === 'severe') {
            $considerations[] = 'Severe sun damage - evaluate skin integrity before treatment';
        }

        if (!empty($this->current_conditions)) {
            $considerations[] = 'Active skin conditions present - review contraindications';
        }

        return $considerations;
    }
}
