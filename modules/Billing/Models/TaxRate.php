<?php

namespace Modules\Billing\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Spatie\Translatable\HasTranslations;

class TaxRate extends BaseModel
{
    use HasTenancy;
    use HasTranslations;

    protected $fillable = [
        'tenant_id',
        'name',
        'rate',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public array $translatable = ['name'];

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

    // Get the default tax rate
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->where('is_active', true)->first();
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
}
