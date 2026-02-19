<?php

namespace Modules\Patients\Models;

use XLinic\Framework\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientMedicalHistory extends BaseModel
{
    /**
     * The table associated with the model.
     */
    protected $table = 'patient_medical_histories';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'patient_id',
        'fitzpatrick_type',
        'blood_type',
        'height_cm',
        'weight_kg',
        'allergies',
        'current_medications',
        'medical_conditions',
        'previous_cosmetic_treatments',
        'previous_surgeries',
        'contraindications',
        'skin_concerns',
        'is_pregnant',
        'is_breastfeeding',
        'is_smoker',
        'last_menstrual_date',
        'hormone_therapy',
        'sun_exposure_level',
        'sunscreen_usage',
        'skincare_routine',
        'physician_name',
        'physician_phone',
        'notes',
        'last_updated_by',
        'last_updated_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'allergies' => 'array',
        'current_medications' => 'array',
        'medical_conditions' => 'array',
        'previous_cosmetic_treatments' => 'array',
        'previous_surgeries' => 'array',
        'contraindications' => 'array',
        'skin_concerns' => 'array',
        'is_pregnant' => 'boolean',
        'is_breastfeeding' => 'boolean',
        'is_smoker' => 'boolean',
        'hormone_therapy' => 'boolean',
        'height_cm' => 'integer',
        'weight_kg' => 'decimal:1',
        'last_menstrual_date' => 'date',
        'last_updated_at' => 'datetime',
    ];

    /**
     * Fitzpatrick skin type options.
     */
    public const FITZPATRICK_TYPES = [
        'I' => 'Type I - Very fair, always burns',
        'II' => 'Type II - Fair, burns easily',
        'III' => 'Type III - Medium, sometimes burns',
        'IV' => 'Type IV - Olive, rarely burns',
        'V' => 'Type V - Brown, very rarely burns',
        'VI' => 'Type VI - Dark brown/black, never burns',
    ];

    /**
     * Blood type options.
     */
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

    /**
     * Common medical conditions.
     */
    public const COMMON_CONDITIONS = [
        'diabetes' => 'Diabetes',
        'hypertension' => 'Hypertension',
        'heart_disease' => 'Heart Disease',
        'thyroid' => 'Thyroid Disorder',
        'autoimmune' => 'Autoimmune Disorder',
        'keloid' => 'Keloid Tendency',
        'herpes' => 'Herpes/Cold Sores',
        'epilepsy' => 'Epilepsy',
        'cancer' => 'Cancer History',
        'bleeding_disorder' => 'Bleeding Disorder',
        'immunocompromised' => 'Immunocompromised',
        'pacemaker' => 'Pacemaker/Metal Implants',
    ];

    /**
     * Common contraindications for laser/aesthetic treatments.
     */
    public const CONTRAINDICATIONS = [
        'active_infection' => 'Active Skin Infection',
        'open_wounds' => 'Open Wounds',
        'recent_sun_exposure' => 'Recent Sun Exposure',
        'recent_tanning' => 'Recent Tanning',
        'isotretinoin' => 'Isotretinoin Use (within 6 months)',
        'photosensitizing_meds' => 'Photosensitizing Medications',
        'active_acne' => 'Active Acne',
        'cold_sores' => 'Active Cold Sores',
        'eczema_psoriasis' => 'Active Eczema/Psoriasis',
        'vitiligo' => 'Vitiligo',
    ];

    /**
     * Get the patient.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the user who last updated.
     */
    public function lastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\Models\User::class, 'last_updated_by');
    }

    /**
     * Check if patient has a specific contraindication.
     */
    public function hasContraindication(string $key): bool
    {
        return in_array($key, $this->contraindications ?? []);
    }

    /**
     * Check if patient has any active contraindications.
     */
    public function hasAnyContraindication(): bool
    {
        return !empty($this->contraindications);
    }

    /**
     * Check if patient has a specific allergy.
     */
    public function hasAllergy(string $allergy): bool
    {
        $allergies = array_map('strtolower', $this->allergies ?? []);
        return in_array(strtolower($allergy), $allergies);
    }

    /**
     * Check if patient is on a specific medication.
     */
    public function isOnMedication(string $medication): bool
    {
        $medications = array_map('strtolower', $this->current_medications ?? []);
        return in_array(strtolower($medication), $medications);
    }

    /**
     * Check if treatment is safe based on Fitzpatrick type.
     */
    public function isSafeForFitzpatrick(int $minType, int $maxType): bool
    {
        $typeMap = ['I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4, 'V' => 5, 'VI' => 6];
        $patientType = $typeMap[$this->fitzpatrick_type] ?? 3;

        return $patientType >= $minType && $patientType <= $maxType;
    }

    /**
     * Calculate BMI.
     */
    public function getBmiAttribute(): ?float
    {
        if (!$this->height_cm || !$this->weight_kg) {
            return null;
        }

        $heightM = $this->height_cm / 100;
        return round($this->weight_kg / ($heightM * $heightM), 1);
    }

    /**
     * Get BMI category.
     */
    public function getBmiCategoryAttribute(): ?string
    {
        $bmi = $this->bmi;
        if ($bmi === null) {
            return null;
        }

        return match (true) {
            $bmi < 18.5 => 'underweight',
            $bmi < 25 => 'normal',
            $bmi < 30 => 'overweight',
            default => 'obese',
        };
    }

    /**
     * Record an update to medical history.
     */
    public function recordUpdate($userId): void
    {
        $this->update([
            'last_updated_by' => $userId,
            'last_updated_at' => now(),
        ]);
    }
}
