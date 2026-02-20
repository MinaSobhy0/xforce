<?php

namespace Modules\Booking\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\User;

class AppointmentTreatmentNote extends BaseModel
{
    use HasTenancy;

    protected $fillable = [
        'tenant_id',
        'appointment_id',
        'areas_treated',
        'machine_settings',
        'skin_reaction',
        'patient_comfort',
        'shots_fired',
        'notes',
        'post_care_given',
        'follow_up_recommended',
        'follow_up_days',
        'created_by_user_id',
    ];

    protected $casts = [
        'areas_treated' => 'array',
        'machine_settings' => 'array',
        'post_care_given' => 'array',
        'shots_fired' => 'integer',
        'follow_up_recommended' => 'boolean',
        'follow_up_days' => 'integer',
    ];

    // Skin reaction options
    public const SKIN_REACTIONS = [
        'none' => 'None',
        'mild_redness' => 'Mild Redness',
        'moderate' => 'Moderate',
        'severe' => 'Severe',
    ];

    // Patient comfort options
    public const PATIENT_COMFORT = [
        'comfortable' => 'Comfortable',
        'mild_discomfort' => 'Mild Discomfort',
        'moderate' => 'Moderate Discomfort',
        'painful' => 'Painful',
    ];

    // Common treatment areas
    public const COMMON_AREAS = [
        'face' => 'Face',
        'neck' => 'Neck',
        'upper_lip' => 'Upper Lip',
        'chin' => 'Chin',
        'cheeks' => 'Cheeks',
        'underarms' => 'Underarms',
        'arms' => 'Arms',
        'hands' => 'Hands',
        'legs' => 'Legs',
        'bikini' => 'Bikini',
        'brazilian' => 'Brazilian',
        'back' => 'Back',
        'chest' => 'Chest',
        'abdomen' => 'Abdomen',
        'full_body' => 'Full Body',
    ];

    // Common post-care instructions
    public const POST_CARE_OPTIONS = [
        'avoid_sun' => 'Avoid sun exposure',
        'use_sunscreen' => 'Apply SPF 30+ sunscreen',
        'no_hot_water' => 'Avoid hot water for 24 hours',
        'no_exercise' => 'Avoid exercise for 24 hours',
        'moisturize' => 'Keep area moisturized',
        'ice_pack' => 'Apply ice pack if needed',
        'aloe_vera' => 'Apply aloe vera gel',
        'no_makeup' => 'Avoid makeup for 24 hours',
        'no_perfume' => 'Avoid perfumed products',
        'loose_clothing' => 'Wear loose clothing',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function getSkinReactionLabelAttribute(): string
    {
        return self::SKIN_REACTIONS[$this->skin_reaction] ?? $this->skin_reaction ?? '';
    }

    public function getPatientComfortLabelAttribute(): string
    {
        return self::PATIENT_COMFORT[$this->patient_comfort] ?? $this->patient_comfort ?? '';
    }

    public function getFormattedAreasAttribute(): string
    {
        if (empty($this->areas_treated)) {
            return '';
        }

        return collect($this->areas_treated)
            ->map(fn ($area) => self::COMMON_AREAS[$area] ?? $area)
            ->implode(', ');
    }

    public function getFormattedPostCareAttribute(): string
    {
        if (empty($this->post_care_given)) {
            return '';
        }

        return collect($this->post_care_given)
            ->map(fn ($care) => self::POST_CARE_OPTIONS[$care] ?? $care)
            ->implode(', ');
    }

    public function getMachineSettingAttribute(string $key): ?string
    {
        return $this->machine_settings[$key] ?? null;
    }
}
