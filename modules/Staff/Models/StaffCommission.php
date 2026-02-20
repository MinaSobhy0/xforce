<?php

namespace Modules\Staff\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Treatments\Models\Treatment;
use Modules\Treatments\Models\TreatmentCategory;
use XLinic\Framework\Core\Model\BaseModel;

class StaffCommission extends BaseModel
{
    protected $table = 'staff_commissions';

    protected $fillable = [
        'tenant_id',
        'staff_profile_id',
        'treatment_id',
        'treatment_category_id',
        'commission_type',
        'flat_amount_minor',
        'percentage',
        'tier_from_minor',
        'tier_to_minor',
        'is_active',
    ];

    protected $casts = [
        'id' => 'string',
        'flat_amount_minor' => 'integer',
        'percentage' => 'decimal:2',
        'tier_from_minor' => 'integer',
        'tier_to_minor' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'commission_type' => 'percentage',
        'percentage' => 10.00,
        'flat_amount_minor' => 0,
        'is_active' => true,
    ];

    // Commission types
    public const TYPE_FLAT = 'flat';
    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_TIERED = 'tiered';

    public const TYPES = [
        self::TYPE_FLAT => 'Flat Amount',
        self::TYPE_PERCENTAGE => 'Percentage',
        self::TYPE_TIERED => 'Tiered',
    ];

    /**
     * Get the staff profile.
     */
    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    /**
     * Get the treatment.
     */
    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    /**
     * Get the treatment category.
     */
    public function treatmentCategory(): BelongsTo
    {
        return $this->belongsTo(TreatmentCategory::class);
    }

    /**
     * Scope to active rules only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Calculate commission amount.
     */
    public function calculateAmount(int $revenueMinor): int
    {
        switch ($this->commission_type) {
            case self::TYPE_FLAT:
                return $this->flat_amount_minor;

            case self::TYPE_PERCENTAGE:
                return (int) ($revenueMinor * $this->percentage / 100);

            case self::TYPE_TIERED:
                // Check if amount falls within tier
                if ($this->tier_from_minor !== null && $revenueMinor < $this->tier_from_minor) {
                    return 0;
                }
                if ($this->tier_to_minor !== null && $revenueMinor > $this->tier_to_minor) {
                    return 0;
                }
                return (int) ($revenueMinor * $this->percentage / 100);

            default:
                return 0;
        }
    }

    /**
     * Get flat amount in major units.
     */
    public function getFlatAmountAttribute(): float
    {
        return $this->flat_amount_minor / 100;
    }

    /**
     * Get tier from in major units.
     */
    public function getTierFromAttribute(): ?float
    {
        return $this->tier_from_minor ? $this->tier_from_minor / 100 : null;
    }

    /**
     * Get tier to in major units.
     */
    public function getTierToAttribute(): ?float
    {
        return $this->tier_to_minor ? $this->tier_to_minor / 100 : null;
    }

    /**
     * Get rule description.
     */
    public function getDescriptionAttribute(): string
    {
        switch ($this->commission_type) {
            case self::TYPE_FLAT:
                return number_format($this->flat_amount, 2) . ' EGP per appointment';

            case self::TYPE_PERCENTAGE:
                return $this->percentage . '% of revenue';

            case self::TYPE_TIERED:
                $range = $this->tier_from !== null && $this->tier_to !== null
                    ? number_format($this->tier_from, 0) . '-' . number_format($this->tier_to, 0) . ' EGP'
                    : ($this->tier_from !== null ? '>' . number_format($this->tier_from, 0) . ' EGP' : '');
                return $this->percentage . '% when ' . $range;

            default:
                return '';
        }
    }
}
