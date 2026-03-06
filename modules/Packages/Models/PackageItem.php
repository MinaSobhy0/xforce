<?php

namespace Modules\Packages\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Services\Models\Service;

class PackageItem extends BaseModel
{
    use HasTenancy;

    // Consumption types (per item)
    public const CONSUMPTION_SESSIONS = 'sessions';
    public const CONSUMPTION_PULSES = 'pulses';

    public const CONSUMPTION_TYPES = [
        self::CONSUMPTION_SESSIONS => 'Sessions',
        self::CONSUMPTION_PULSES => 'Pulses',
    ];

    protected $fillable = [
        'tenant_id',
        'package_id',
        'service_id',
        'quantity',
        'consumption_type',
        'unit_price_minor',
        'discount_percent',
        'pulses_per_session',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_minor' => 'integer',
        'discount_percent' => 'decimal:2',
        'pulses_per_session' => 'integer',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'consumption_type' => self::CONSUMPTION_SESSIONS,
        'unit_price_minor' => 0,
        'discount_percent' => 0,
    ];

    // Relationships
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    // Accessors
    public function getServiceNameAttribute(): string
    {
        return $this->service?->translated_name ?? '';
    }

    /**
     * Get the total price for this item (quantity × unit_price)
     */
    public function getTotalPriceMinorAttribute(): int
    {
        return $this->quantity * $this->unit_price_minor;
    }

    /**
     * Get formatted unit price
     */
    public function getFormattedUnitPriceAttribute(): string
    {
        return format_money($this->unit_price_minor);
    }

    /**
     * Get formatted total price
     */
    public function getFormattedTotalPriceAttribute(): string
    {
        return format_money($this->total_price_minor);
    }

    /**
     * Get the original service price (before any discount)
     */
    public function getOriginalPriceMinorAttribute(): int
    {
        return $this->service?->base_price_minor ?? 0;
    }

    /**
     * Get discount amount per unit
     */
    public function getDiscountAmountMinorAttribute(): int
    {
        return $this->original_price_minor - $this->unit_price_minor;
    }

    /**
     * Get consumption type label
     */
    public function getConsumptionTypeLabelAttribute(): string
    {
        return self::CONSUMPTION_TYPES[$this->consumption_type] ?? $this->consumption_type;
    }

    // Scopes
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopeSessionBased($query)
    {
        return $query->where('consumption_type', self::CONSUMPTION_SESSIONS);
    }

    public function scopePulseBased($query)
    {
        return $query->where('consumption_type', self::CONSUMPTION_PULSES);
    }

    // Consumption helpers
    public function isSessionBased(): bool
    {
        return $this->consumption_type === self::CONSUMPTION_SESSIONS;
    }

    public function isPulseBased(): bool
    {
        return $this->consumption_type === self::CONSUMPTION_PULSES;
    }

    /**
     * Get total units for this item (sessions or pulses)
     */
    public function getTotalUnitsAttribute(): int
    {
        if ($this->isPulseBased() && $this->pulses_per_session) {
            return $this->quantity * $this->pulses_per_session;
        }
        return $this->quantity;
    }

    /**
     * Get unit label based on consumption type
     */
    public function getUnitLabelAttribute(): string
    {
        return $this->isPulseBased() ? 'pulses' : 'sessions';
    }

    /**
     * Calculate price per unit (for revenue recognition)
     * For sessions: unit_price_minor
     * For pulses: unit_price_minor / pulses_per_session
     */
    public function getPricePerUnitMinorAttribute(): int
    {
        if ($this->isPulseBased() && $this->pulses_per_session > 0) {
            return (int) floor($this->unit_price_minor / $this->pulses_per_session);
        }
        return $this->unit_price_minor;
    }
}
