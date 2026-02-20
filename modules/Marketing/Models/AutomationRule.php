<?php

namespace Modules\Marketing\Models;

use XLinic\Framework\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class AutomationRule extends BaseModel
{
    use HasTranslations;

    protected $table = 'automation_rules';

    // Trigger types
    public const TRIGGER_APPOINTMENT_CONFIRMED = 'appointment_confirmed';
    public const TRIGGER_APPOINTMENT_REMINDER = 'appointment_reminder';
    public const TRIGGER_APPOINTMENT_COMPLETED = 'appointment_completed';
    public const TRIGGER_INVOICE_ISSUED = 'invoice_issued';
    public const TRIGGER_INVOICE_PAID = 'invoice_paid';
    public const TRIGGER_INVOICE_OVERDUE = 'invoice_overdue';
    public const TRIGGER_PATIENT_BIRTHDAY = 'patient_birthday';
    public const TRIGGER_PATIENT_INACTIVE = 'patient_inactive';
    public const TRIGGER_PACKAGE_EXPIRING = 'package_expiring';
    public const TRIGGER_MEMBERSHIP_EXPIRING = 'membership_expiring';

    // Timing types
    public const TIMING_IMMEDIATE = 'immediate';
    public const TIMING_BEFORE = 'before';
    public const TIMING_AFTER = 'after';

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'trigger_type',
        'template_id',
        'channel',
        'timing_type',
        'timing_value',
        'timing_unit',
        'conditions_json',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'conditions_json' => 'array',
        'timing_value' => 'integer',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    protected $attributes = [
        'timing_type' => self::TIMING_IMMEDIATE,
        'timing_value' => 0,
        'timing_unit' => 'hours',
        'is_active' => true,
        'priority' => 0,
    ];

    /**
     * Get all trigger types.
     */
    public static function triggerTypes(): array
    {
        return [
            self::TRIGGER_APPOINTMENT_CONFIRMED => __('marketing::marketing.triggers.appointment_confirmed'),
            self::TRIGGER_APPOINTMENT_REMINDER => __('marketing::marketing.triggers.appointment_reminder'),
            self::TRIGGER_APPOINTMENT_COMPLETED => __('marketing::marketing.triggers.appointment_completed'),
            self::TRIGGER_INVOICE_ISSUED => __('marketing::marketing.triggers.invoice_issued'),
            self::TRIGGER_INVOICE_PAID => __('marketing::marketing.triggers.invoice_paid'),
            self::TRIGGER_INVOICE_OVERDUE => __('marketing::marketing.triggers.invoice_overdue'),
            self::TRIGGER_PATIENT_BIRTHDAY => __('marketing::marketing.triggers.patient_birthday'),
            self::TRIGGER_PATIENT_INACTIVE => __('marketing::marketing.triggers.patient_inactive'),
            self::TRIGGER_PACKAGE_EXPIRING => __('marketing::marketing.triggers.package_expiring'),
            self::TRIGGER_MEMBERSHIP_EXPIRING => __('marketing::marketing.triggers.membership_expiring'),
        ];
    }

    /**
     * Get timing types.
     */
    public static function timingTypes(): array
    {
        return [
            self::TIMING_IMMEDIATE => __('marketing::marketing.timing.immediate'),
            self::TIMING_BEFORE => __('marketing::marketing.timing.before'),
            self::TIMING_AFTER => __('marketing::marketing.timing.after'),
        ];
    }

    /**
     * Get timing units.
     */
    public static function timingUnits(): array
    {
        return [
            'minutes' => __('marketing::marketing.timing_units.minutes'),
            'hours' => __('marketing::marketing.timing_units.hours'),
            'days' => __('marketing::marketing.timing_units.days'),
        ];
    }

    /**
     * Get the template for this rule.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }

    /**
     * Calculate the send time based on reference time.
     */
    public function calculateSendTime(\DateTimeInterface $referenceTime): \DateTimeInterface
    {
        $time = \Carbon\Carbon::instance($referenceTime);

        if ($this->timing_type === self::TIMING_IMMEDIATE) {
            return $time;
        }

        $modifier = match ($this->timing_unit) {
            'minutes' => 'minutes',
            'hours' => 'hours',
            'days' => 'days',
            default => 'hours',
        };

        if ($this->timing_type === self::TIMING_BEFORE) {
            return $time->sub($this->timing_value, $modifier);
        }

        return $time->add($this->timing_value, $modifier);
    }

    /**
     * Check if conditions match the given context.
     */
    public function matchesConditions(array $context): bool
    {
        if (empty($this->conditions_json)) {
            return true;
        }

        foreach ($this->conditions_json as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? 'equals';
            $value = $condition['value'] ?? null;

            if (!$field || !isset($context[$field])) {
                continue;
            }

            $contextValue = $context[$field];

            $matches = match ($operator) {
                'equals' => $contextValue == $value,
                'not_equals' => $contextValue != $value,
                'contains' => str_contains($contextValue, $value),
                'in' => in_array($contextValue, (array) $value),
                'not_in' => !in_array($contextValue, (array) $value),
                'greater_than' => $contextValue > $value,
                'less_than' => $contextValue < $value,
                default => true,
            };

            if (!$matches) {
                return false;
            }
        }

        return true;
    }

    /**
     * Scope to active rules.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to specific trigger type.
     */
    public function scopeForTrigger($query, string $triggerType)
    {
        return $query->where('trigger_type', $triggerType);
    }

    /**
     * Scope ordered by priority.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('priority', 'desc');
    }
}
