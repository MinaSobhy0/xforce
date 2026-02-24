<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Models\ChartOfAccount;
use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Spatie\Translatable\HasTranslations;

class TaxRate extends BaseModel
{
    use HasTenancy;
    use HasTranslations;

    // Type constants
    public const TYPE_SALES = 'sales';
    public const TYPE_PURCHASE = 'purchase';

    public const TYPES = [
        self::TYPE_SALES => 'Sales Tax',
        self::TYPE_PURCHASE => 'Purchase Tax',
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'rate',
        'type',
        'account_id',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public array $translatable = ['name'];

    /**
     * Get the translated name attribute.
     */
    public function getTranslatedNameAttribute(): string
    {
        return $this->getTranslation('name', app()->getLocale())
            ?: $this->getTranslation('name', 'en')
            ?: '';
    }

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (TaxRate $taxRate) {
            if ($taxRate->is_default) {
                // Unset other defaults
                static::where('tenant_id', $taxRate->tenant_id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });

        static::updating(function (TaxRate $taxRate) {
            if ($taxRate->isDirty('is_default') && $taxRate->is_default) {
                static::where('tenant_id', $taxRate->tenant_id)
                    ->where('id', '!=', $taxRate->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    // Relationships
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    // Get the default tax rate for a specific type
    public static function getDefault(?string $type = null): ?self
    {
        $query = static::where('is_default', true)->where('is_active', true);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->first();
    }

    // Get the type label
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeSales($query)
    {
        return $query->where('type', self::TYPE_SALES);
    }

    public function scopePurchase($query)
    {
        return $query->where('type', self::TYPE_PURCHASE);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
