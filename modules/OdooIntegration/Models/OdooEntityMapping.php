<?php

namespace Modules\OdooIntegration\Models;

use XLinic\Framework\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\OdooIntegration\Enums\SyncDirection;
use Modules\OdooIntegration\Enums\SyncFrequency;
use Modules\OdooIntegration\Enums\ConflictResolution;

class OdooEntityMapping extends BaseModel
{
    protected $table = 'odoo_entity_mappings';

    protected $fillable = [
        'tenant_id',
        'odoo_connection_id',
        'name',
        'local_model',
        'local_table',
        'odoo_model',
        'sync_direction',
        'sync_frequency',
        'conflict_resolution',
        'batch_size',
        'priority',
        'is_active',
        'filter_conditions',
        'settings',
        'sync_date_field',
        'sync_from_date',
        'sync_to_date',
    ];

    protected $casts = [
        'batch_size' => 'integer',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'filter_conditions' => 'array',
        'settings' => 'array',
        'sync_direction' => SyncDirection::class,
        'sync_frequency' => SyncFrequency::class,
        'conflict_resolution' => ConflictResolution::class,
        'sync_from_date' => 'date',
        'sync_to_date' => 'date',
    ];

    // Relationships
    public function connection(): BelongsTo
    {
        return $this->belongsTo(OdooConnection::class, 'odoo_connection_id');
    }

    public function fieldMappings(): HasMany
    {
        return $this->hasMany(OdooFieldMapping::class, 'entity_mapping_id')->orderBy('sort_order');
    }

    public function syncRecords(): HasMany
    {
        return $this->hasMany(OdooSyncRecord::class, 'entity_mapping_id');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(OdooSyncLog::class, 'entity_mapping_id');
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(OdooSyncConflict::class, 'entity_mapping_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForModel($query, string $localModel)
    {
        return $query->where('local_model', $localModel);
    }

    public function scopeForOdooModel($query, string $odooModel)
    {
        return $query->where('odoo_model', $odooModel);
    }

    public function scopeImportable($query)
    {
        return $query->whereIn('sync_direction', [
            SyncDirection::IMPORT->value,
            SyncDirection::BIDIRECTIONAL->value,
        ]);
    }

    public function scopeExportable($query)
    {
        return $query->whereIn('sync_direction', [
            SyncDirection::EXPORT->value,
            SyncDirection::BIDIRECTIONAL->value,
        ]);
    }

    public function scopeScheduled($query)
    {
        return $query->whereIn('sync_frequency', [
            SyncFrequency::HOURLY->value,
            SyncFrequency::DAILY->value,
        ]);
    }

    // Helper methods
    public function allowsImport(): bool
    {
        return $this->sync_direction->allowsImport();
    }

    public function allowsExport(): bool
    {
        return $this->sync_direction->allowsExport();
    }

    public function requiresManualConflictResolution(): bool
    {
        return $this->conflict_resolution === ConflictResolution::MANUAL;
    }

    public function getActiveFieldMappings()
    {
        return $this->fieldMappings()->where('is_active', true)->get();
    }

    public function getKeyFields()
    {
        return $this->fieldMappings()->where('is_key_field', true)->where('is_active', true)->get();
    }

    public function getLocalModelInstance()
    {
        if (!class_exists($this->local_model)) {
            return null;
        }
        return new $this->local_model;
    }

    public function getOdooDomain(): array
    {
        $raw = $this->filter_conditions ?? [];

        return array_values(array_filter(array_map(
            fn ($leaf) => $this->normalizeDomainLeaf($leaf),
            $raw
        )));
    }

    /**
     * Odoo's XML-RPC search_read expects each domain leaf as a 3-element
     * positional array [field, operator, value]. The Filament repeater
     * submits named keys {field, operator, value}, and legacy rows had
     * both merged into one array — which XML-RPC then encodes as a
     * struct, causing Odoo to reject the domain and misread it as
     * "Invalid field '0'". Emit clean positional tuples regardless.
     */
    protected function normalizeDomainLeaf(mixed $leaf): ?array
    {
        if (!is_array($leaf)) {
            return null;
        }

        if (isset($leaf['field']) && isset($leaf['operator'])) {
            $field = $leaf['field'];
            $operator = $leaf['operator'];
            $value = $leaf['value'] ?? null;
        } else {
            $field = $leaf[0] ?? null;
            $operator = $leaf[1] ?? null;
            $value = $leaf[2] ?? null;
        }

        if (!is_string($field) || $field === '' || !is_string($operator) || $operator === '') {
            return null;
        }

        return [$field, $operator, $this->coerceDomainValue($value, $operator)];
    }

    protected function coerceDomainValue(mixed $value, string $operator): mixed
    {
        if (in_array($operator, ['in', 'not in'], true) && is_string($value)) {
            $parts = array_map('trim', explode(',', $value));
            return array_values(array_filter(array_map(
                fn ($v) => $this->coerceScalar($v),
                $parts
            ), fn ($v) => $v !== null && $v !== ''));
        }

        return $this->coerceScalar($value);
    }

    protected function coerceScalar(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $lower = strtolower(trim($value));
        return match (true) {
            $lower === 'true' => true,
            $lower === 'false' => false,
            $lower === 'null' => null,
            is_numeric($value) && !str_contains($value, '.') => (int) $value,
            is_numeric($value) => (float) $value,
            default => $value,
        };
    }

    /**
     * Get the date filter domain for Odoo queries.
     * Returns an array of domain conditions based on sync_date_field, sync_from_date, and sync_to_date.
     */
    public function getDateFilterDomain(): array
    {
        if (empty($this->sync_date_field)) {
            return [];
        }

        $domain = [];

        if ($this->sync_from_date) {
            $domain[] = [$this->sync_date_field, '>=', $this->sync_from_date->format('Y-m-d')];
        }

        if ($this->sync_to_date) {
            $domain[] = [$this->sync_date_field, '<=', $this->sync_to_date->format('Y-m-d')];
        }

        return $domain;
    }

    /**
     * Check if this mapping has a date filter configured.
     */
    public function hasDateFilter(): bool
    {
        return !empty($this->sync_date_field) && ($this->sync_from_date || $this->sync_to_date);
    }

    /**
     * Get available date fields from the field mappings for this entity.
     * These are fields with 'date' or 'datetime' transform types.
     */
    public function getAvailableDateFields(): array
    {
        $dateFields = ['write_date', 'create_date']; // Always available in Odoo

        foreach ($this->getActiveFieldMappings() as $fieldMapping) {
            if (in_array($fieldMapping->transform_type, ['date', 'datetime']) && !empty($fieldMapping->odoo_field)) {
                $dateFields[] = $fieldMapping->odoo_field;
            }
        }

        return array_unique($dateFields);
    }

    public function getPendingConflictsCount(): int
    {
        return $this->conflicts()->where('status', 'pending')->count();
    }

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (OdooEntityMapping $mapping) {
            if (empty($mapping->name)) {
                $mapping->name = class_basename($mapping->local_model) . ' ↔ ' . $mapping->odoo_model;
            }
            if (empty($mapping->local_table)) {
                $model = $mapping->getLocalModelInstance();
                $mapping->local_table = $model?->getTable() ?? str()->snake(str()->plural(class_basename($mapping->local_model)));
            }
        });
    }
}
