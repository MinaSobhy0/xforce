<?php

namespace Modules\Prescriptions\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrescriptionItem extends BaseModel
{
    use HasTenancy, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'prescription_id',
        'medication_name',
        'generic_name',
        'dosage',
        'dosage_unit',
        'form',
        'frequency',
        'duration',
        'duration_unit',
        'quantity',
        'route',
        'instructions',
        'special_instructions',
        'refills_allowed',
        'sort_order',
    ];

    protected $casts = [
        'duration' => 'integer',
        'quantity' => 'integer',
        'refills_allowed' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Boot the model - convert empty strings to null/defaults for integer fields.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function ($model) {
            // Fields that can be null
            $nullableIntFields = ['duration', 'quantity'];
            // Fields with default values (not null)
            $defaultIntFields = ['refills_allowed' => 0, 'sort_order' => 0];

            foreach ($nullableIntFields as $field) {
                $value = $model->getAttributes()[$field] ?? null;
                if ($value === '' || $value === null || (is_string($value) && trim($value) === '')) {
                    $model->{$field} = null;
                } elseif (is_numeric($value)) {
                    $model->{$field} = (int) $value;
                }
            }

            foreach ($defaultIntFields as $field => $default) {
                $value = $model->getAttributes()[$field] ?? null;
                if ($value === '' || $value === null || (is_string($value) && trim($value) === '')) {
                    $model->{$field} = $default;
                } elseif (is_numeric($value)) {
                    $model->{$field} = (int) $value;
                }
            }
        });
    }

    // Frequency constants
    public const FREQUENCIES = [
        'once_daily' => 'Once daily',
        'twice_daily' => 'Twice daily',
        'three_times_daily' => '3 times daily',
        'four_times_daily' => '4 times daily',
        'every_4_hours' => 'Every 4 hours',
        'every_6_hours' => 'Every 6 hours',
        'every_8_hours' => 'Every 8 hours',
        'every_12_hours' => 'Every 12 hours',
        'as_needed' => 'As needed (PRN)',
        'weekly' => 'Weekly',
        'twice_weekly' => 'Twice weekly',
        'monthly' => 'Monthly',
        'other' => 'Other',
    ];

    // Route constants
    public const ROUTES = [
        'oral' => 'Oral',
        'topical' => 'Topical',
        'injection' => 'Injection',
        'intravenous' => 'Intravenous (IV)',
        'intramuscular' => 'Intramuscular (IM)',
        'subcutaneous' => 'Subcutaneous (SC)',
        'inhalation' => 'Inhalation',
        'sublingual' => 'Sublingual',
        'transdermal' => 'Transdermal',
        'rectal' => 'Rectal',
        'ophthalmic' => 'Ophthalmic (Eye)',
        'otic' => 'Otic (Ear)',
        'nasal' => 'Nasal',
        'vaginal' => 'Vaginal',
        'other' => 'Other',
    ];

    // Form constants
    public const FORMS = [
        'tablet' => 'Tablet',
        'capsule' => 'Capsule',
        'syrup' => 'Syrup',
        'suspension' => 'Suspension',
        'solution' => 'Solution',
        'cream' => 'Cream',
        'ointment' => 'Ointment',
        'gel' => 'Gel',
        'lotion' => 'Lotion',
        'drops' => 'Drops',
        'injection' => 'Injection',
        'inhaler' => 'Inhaler',
        'patch' => 'Patch',
        'suppository' => 'Suppository',
        'powder' => 'Powder',
        'spray' => 'Spray',
        'other' => 'Other',
    ];

    // Instruction constants
    public const INSTRUCTIONS = [
        'before_meal' => 'Before meal',
        'after_meal' => 'After meal',
        'with_food' => 'With food',
        'empty_stomach' => 'On empty stomach',
        'at_bedtime' => 'At bedtime',
        'in_morning' => 'In the morning',
        'with_water' => 'With plenty of water',
        'without_water' => 'Without water',
        'chew' => 'Chew before swallowing',
        'swallow_whole' => 'Swallow whole',
        'dissolve' => 'Dissolve in mouth',
        'apply_affected' => 'Apply to affected area',
        'as_directed' => 'As directed',
    ];

    // Duration unit constants
    public const DURATION_UNITS = [
        'days' => 'Days',
        'weeks' => 'Weeks',
        'months' => 'Months',
    ];

    // Dosage unit constants
    public const DOSAGE_UNITS = [
        'mg' => 'mg',
        'g' => 'g',
        'ml' => 'ml',
        'mcg' => 'mcg',
        'IU' => 'IU',
        'unit' => 'unit',
        'puff' => 'puff',
        'drop' => 'drop',
        'application' => 'application',
    ];

    // Relationships
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    // Accessors
    public function getFullDosageAttribute(): string
    {
        $parts = [];

        if ($this->dosage) {
            $parts[] = $this->dosage;
        }

        if ($this->dosage_unit) {
            $parts[] = $this->dosage_unit;
        }

        return implode(' ', $parts);
    }

    public function getFullDurationAttribute(): string
    {
        if (!$this->duration) {
            return '';
        }

        $unit = self::DURATION_UNITS[$this->duration_unit] ?? $this->duration_unit ?? '';

        return "{$this->duration} {$unit}";
    }

    public function getFrequencyLabelAttribute(): string
    {
        return self::FREQUENCIES[$this->frequency] ?? $this->frequency ?? '';
    }

    public function getRouteLabelAttribute(): string
    {
        return self::ROUTES[$this->route] ?? $this->route ?? '';
    }

    public function getFormLabelAttribute(): string
    {
        return self::FORMS[$this->form] ?? $this->form ?? '';
    }

    public function getInstructionsLabelAttribute(): string
    {
        return self::INSTRUCTIONS[$this->instructions] ?? $this->instructions ?? '';
    }

    public function getDisplayLineAttribute(): string
    {
        $parts = [$this->medication_name];

        if ($this->generic_name) {
            $parts[] = "({$this->generic_name})";
        }

        if ($this->full_dosage) {
            $parts[] = $this->full_dosage;
        }

        if ($this->form_label) {
            $parts[] = $this->form_label;
        }

        return implode(' ', $parts);
    }

    public function getDosageInstructionsAttribute(): string
    {
        $parts = [];

        if ($this->frequency_label) {
            $parts[] = $this->frequency_label;
        }

        if ($this->full_duration) {
            $parts[] = "for {$this->full_duration}";
        }

        if ($this->instructions_label) {
            $parts[] = "- {$this->instructions_label}";
        }

        return implode(' ', $parts);
    }
}
