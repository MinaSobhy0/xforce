<?php

namespace Modules\Booking\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\User;
use Modules\Equipment\Models\Equipment;
use Modules\Services\Models\Service;
use Modules\Services\Models\ParameterPreset;

class TreatmentSessionData extends BaseModel
{
    use HasTenancy;

    protected $table = 'treatment_session_data';

    protected $fillable = [
        'tenant_id',
        'appointment_id',
        'service_id',
        'equipment_id',
        'practitioner_id',
        'parameter_values',
        'equipment_metrics',
        'session_equipment',
        'equipment_parameter_values',
        'treatment_areas',
        'clinical_notes',
        'skin_reaction',
        'pain_level',
        'adverse_events',
        'pre_treatment_checklist',
        'post_treatment_instructions',
        'aftercare_provided',
        'session_started_at',
        'session_ended_at',
        'actual_duration_minutes',
        'preset_id',
        'is_complete',
        'is_validated',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'parameter_values' => 'array',
        'equipment_metrics' => 'array',
        'session_equipment' => 'array',
        'equipment_parameter_values' => 'array',
        'treatment_areas' => 'array',
        'adverse_events' => 'array',
        'pre_treatment_checklist' => 'array',
        'post_treatment_instructions' => 'array',
        'aftercare_provided' => 'boolean',
        'session_started_at' => 'datetime',
        'session_ended_at' => 'datetime',
        'actual_duration_minutes' => 'integer',
        'is_complete' => 'boolean',
        'is_validated' => 'boolean',
        'validated_at' => 'datetime',
    ];

    // Skin reaction levels
    public const SKIN_REACTION_NONE = 'none';
    public const SKIN_REACTION_MILD = 'mild';
    public const SKIN_REACTION_MODERATE = 'moderate';
    public const SKIN_REACTION_SEVERE = 'severe';

    public const SKIN_REACTIONS = [
        self::SKIN_REACTION_NONE => 'None',
        self::SKIN_REACTION_MILD => 'Mild',
        self::SKIN_REACTION_MODERATE => 'Moderate',
        self::SKIN_REACTION_SEVERE => 'Severe',
    ];

    /**
     * Get the appointment this session data belongs to.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Get the service for this session.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get the equipment used in this session.
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * Get the practitioner who performed this session.
     */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'practitioner_id');
    }

    /**
     * Get the preset used for this session.
     */
    public function preset(): BelongsTo
    {
        return $this->belongsTo(ParameterPreset::class, 'preset_id');
    }

    /**
     * Get the user who validated this session.
     */
    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * Get a specific parameter value.
     */
    public function getParameterValue(string $key)
    {
        $values = $this->parameter_values ?? [];
        return $values[$key] ?? null;
    }

    /**
     * Get a parameter value with its unit.
     */
    public function getParameterWithUnit(string $key): ?array
    {
        $value = $this->getParameterValue($key);
        if ($value === null) {
            return null;
        }

        // If value is already structured with value/unit
        if (is_array($value) && isset($value['value'])) {
            return $value;
        }

        return ['value' => $value, 'unit' => null];
    }

    /**
     * Set a parameter value.
     */
    public function setParameterValue(string $key, $value, ?string $unit = null): void
    {
        $values = $this->parameter_values ?? [];
        $values[$key] = $unit ? ['value' => $value, 'unit' => $unit] : $value;
        $this->parameter_values = $values;
    }

    /**
     * Get equipment metric value.
     */
    public function getEquipmentMetric(string $key)
    {
        $metrics = $this->equipment_metrics ?? [];
        return $metrics[$key] ?? null;
    }

    /**
     * Get shots used during this session.
     */
    public function getShotsUsed(): int
    {
        return $this->getEquipmentMetric('shots_used') ?? 0;
    }

    /**
     * Get total energy delivered.
     */
    public function getEnergyDelivered(): ?array
    {
        return $this->getEquipmentMetric('energy_delivered');
    }

    /**
     * Check if pre-treatment checklist item is complete.
     */
    public function isChecklistItemComplete(string $key): bool
    {
        $checklist = $this->pre_treatment_checklist ?? [];
        return ($checklist[$key] ?? false) === true;
    }

    /**
     * Get all incomplete checklist items.
     */
    public function getIncompleteChecklistItems(): array
    {
        $checklist = $this->pre_treatment_checklist ?? [];
        return array_keys(array_filter($checklist, fn($value) => $value !== true));
    }

    /**
     * Check if all required checklist items are complete.
     */
    public function isChecklistComplete(): bool
    {
        return empty($this->getIncompleteChecklistItems());
    }

    /**
     * Get treatment areas summary.
     */
    public function getTreatmentAreasSummary(): array
    {
        $areas = $this->treatment_areas ?? [];
        return array_map(function ($area) {
            return [
                'area' => $area['area'] ?? 'Unknown',
                'pulses' => $area['pulses'] ?? 0,
                'passes' => $area['passes'] ?? 1,
            ];
        }, $areas);
    }

    /**
     * Get total pulses across all treatment areas.
     */
    public function getTotalPulses(): int
    {
        $areas = $this->treatment_areas ?? [];
        return array_sum(array_column($areas, 'pulses'));
    }

    /**
     * Check if there were any adverse events.
     */
    public function hasAdverseEvents(): bool
    {
        return !empty($this->adverse_events);
    }

    /**
     * Calculate session duration from timestamps.
     */
    public function calculateDuration(): ?int
    {
        if (!$this->session_started_at || !$this->session_ended_at) {
            return null;
        }

        return $this->session_started_at->diffInMinutes($this->session_ended_at);
    }

    /**
     * Start the session.
     */
    public function startSession(): void
    {
        $this->session_started_at = now();
        $this->save();
    }

    /**
     * End the session.
     */
    public function endSession(): void
    {
        $this->session_ended_at = now();
        $this->actual_duration_minutes = $this->calculateDuration();
        $this->save();
    }

    /**
     * Mark session as complete.
     */
    public function markComplete(): void
    {
        $this->is_complete = true;
        if (!$this->session_ended_at) {
            $this->endSession();
        } else {
            $this->save();
        }
    }

    /**
     * Validate the session data.
     */
    public function validate(User $validator): void
    {
        $this->is_validated = true;
        $this->validated_by = $validator->id;
        $this->validated_at = now();
        $this->save();
    }

    /**
     * Apply a preset's values to this session.
     */
    public function applyPreset(ParameterPreset $preset): void
    {
        $this->preset_id = $preset->id;
        $this->parameter_values = array_merge(
            $this->parameter_values ?? [],
            $preset->getValues()
        );
        $this->save();
    }

    /**
     * Scope to complete sessions.
     */
    public function scopeComplete($query)
    {
        return $query->where('is_complete', true);
    }

    /**
     * Scope to validated sessions.
     */
    public function scopeValidated($query)
    {
        return $query->where('is_validated', true);
    }

    /**
     * Scope to sessions with adverse events.
     */
    public function scopeWithAdverseEvents($query)
    {
        return $query->whereNotNull('adverse_events')
            ->where('adverse_events', '!=', '[]');
    }

    /**
     * Scope to sessions for a specific equipment.
     */
    public function scopeForEquipment($query, string $equipmentId)
    {
        return $query->where('equipment_id', $equipmentId);
    }

    /**
     * Scope to sessions by a specific practitioner.
     */
    public function scopeByPractitioner($query, string $practitionerId)
    {
        return $query->where('practitioner_id', $practitionerId);
    }
}
