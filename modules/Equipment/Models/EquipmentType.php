<?php

namespace Modules\Equipment\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasTranslation;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentType extends BaseModel
{
    use HasTenancy, HasTranslation;

    protected $fillable = [
        'tenant_id',
        'name',
        'manufacturer',
        'model',
        'category',
        'specifications',
        'max_shots',
        'image_url',
        'is_active',
    ];

    protected $casts = [
        'name' => 'array',
        'specifications' => 'array',
        'max_shots' => 'integer',
        'is_active' => 'boolean',
    ];

    public array $translatable = ['name'];

    public const CATEGORIES = [
        'laser' => 'Laser',
        'ipl' => 'IPL',
        'rf' => 'Radio Frequency',
        'hifu' => 'HIFU',
        'cryolipolysis' => 'Cryolipolysis',
        'microneedling' => 'Microneedling',
        'hydrafacial' => 'Hydrafacial',
        'led' => 'LED Therapy',
        'other' => 'Other',
    ];

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    public function getTranslatedNameAttribute(): string
    {
        return $this->getTranslation('name', app()->getLocale())
            ?? $this->getTranslation('name', 'en')
            ?? '';
    }
}
