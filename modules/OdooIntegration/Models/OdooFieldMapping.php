<?php

namespace Modules\OdooIntegration\Models;

use XLinic\Framework\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\OdooIntegration\Enums\SyncDirection;

class OdooFieldMapping extends BaseModel
{
    protected $table = 'odoo_field_mappings';

    protected $fillable = [
        'entity_mapping_id',
        'local_field',
        'odoo_field',
        'direction',
        'transform_type',
        'transform_config',
        'default_value',
        'is_required',
        'is_key_field',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'transform_config' => 'array',
        'is_required' => 'boolean',
        'is_key_field' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'direction' => SyncDirection::class,
    ];

    // Available transform types
    public const TRANSFORM_TYPES = [
        'direct' => 'Direct Copy',
        'date' => 'Date Format',
        'datetime' => 'DateTime with Timezone',
        'money' => 'Money (cents ↔ decimal)',
        'relation' => 'Foreign Key via odoo_id',
        'enum' => 'Value Mapping',
        'boolean' => 'Boolean Conversion',
        'json' => 'JSON Encode/Decode',
        'translatable' => 'Translatable JSONB',
        'split_name' => 'Split Name',
        'many2many' => 'Many2Many Pivot',
        'percentage' => 'Percentage',
    ];

    // Relationships
    public function entityMapping(): BelongsTo
    {
        return $this->belongsTo(OdooEntityMapping::class, 'entity_mapping_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeKeyFields($query)
    {
        return $query->where('is_key_field', true);
    }

    public function scopeImportable($query)
    {
        return $query->whereIn('direction', [
            SyncDirection::IMPORT->value,
            SyncDirection::BIDIRECTIONAL->value,
        ]);
    }

    public function scopeExportable($query)
    {
        return $query->whereIn('direction', [
            SyncDirection::EXPORT->value,
            SyncDirection::BIDIRECTIONAL->value,
        ]);
    }

    // Helper methods
    public function allowsImport(): bool
    {
        return $this->direction->allowsImport();
    }

    public function allowsExport(): bool
    {
        return $this->direction->allowsExport();
    }

    public function getTransformConfig(string $key, $default = null)
    {
        return $this->transform_config[$key] ?? $default;
    }

    public function getTransformTypeLabelAttribute(): string
    {
        return self::TRANSFORM_TYPES[$this->transform_type] ?? $this->transform_type;
    }

    public static function transformTypeOptions(): array
    {
        return self::TRANSFORM_TYPES;
    }
}
