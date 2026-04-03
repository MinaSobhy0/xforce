<?php

namespace Modules\OdooIntegration\Models;

use XLinic\Framework\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\OdooIntegration\Enums\SyncDirection;

class OdooSyncRecord extends BaseModel
{
    protected $table = 'odoo_sync_records';

    protected $fillable = [
        'tenant_id',
        'entity_mapping_id',
        'local_model',
        'local_id',
        'odoo_id',
        'sync_status',
        'last_sync_direction',
        'last_synced_at',
        'local_checksum',
        'odoo_checksum',
        'is_archived',
        'last_error',
    ];

    protected $casts = [
        'local_id' => 'integer',
        'odoo_id' => 'integer',
        'last_synced_at' => 'datetime',
        'is_archived' => 'boolean',
        'last_sync_direction' => SyncDirection::class,
    ];

    // Sync statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_SYNCED = 'synced';
    public const STATUS_ERROR = 'error';
    public const STATUS_CONFLICT = 'conflict';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_SYNCED => 'Synced',
        self::STATUS_ERROR => 'Error',
        self::STATUS_CONFLICT => 'Conflict',
    ];

    // Relationships
    public function entityMapping(): BelongsTo
    {
        return $this->belongsTo(OdooEntityMapping::class, 'entity_mapping_id');
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(OdooSyncConflict::class, 'sync_record_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('sync_status', self::STATUS_PENDING);
    }

    public function scopeSynced($query)
    {
        return $query->where('sync_status', self::STATUS_SYNCED);
    }

    public function scopeError($query)
    {
        return $query->where('sync_status', self::STATUS_ERROR);
    }

    public function scopeConflict($query)
    {
        return $query->where('sync_status', self::STATUS_CONFLICT);
    }

    public function scopeForLocalRecord($query, string $model, int $id)
    {
        return $query->where('local_model', $model)->where('local_id', $id);
    }

    public function scopeForOdooRecord($query, int $odooId)
    {
        return $query->where('odoo_id', $odooId);
    }

    public function scopeNotArchived($query)
    {
        return $query->where('is_archived', false);
    }

    // Helper methods
    public function markSynced(string $direction, ?string $localChecksum = null, ?string $odooChecksum = null): void
    {
        $this->update([
            'sync_status' => self::STATUS_SYNCED,
            'last_sync_direction' => $direction,
            'last_synced_at' => now(),
            'local_checksum' => $localChecksum ?? $this->local_checksum,
            'odoo_checksum' => $odooChecksum ?? $this->odoo_checksum,
            'last_error' => null,
        ]);
    }

    public function markError(string $error): void
    {
        $this->update([
            'sync_status' => self::STATUS_ERROR,
            'last_error' => $error,
        ]);
    }

    public function markConflict(): void
    {
        $this->update([
            'sync_status' => self::STATUS_CONFLICT,
        ]);
    }

    public function markArchived(bool $archived = true): void
    {
        $this->update(['is_archived' => $archived]);
    }

    public function hasConflict(): bool
    {
        return $this->sync_status === self::STATUS_CONFLICT;
    }

    public function hasPendingConflict(): bool
    {
        return $this->conflicts()->where('status', 'pending')->exists();
    }

    public function getLocalRecord()
    {
        if (!class_exists($this->local_model)) {
            return null;
        }
        return $this->local_model::find($this->local_id);
    }

    public function checksumsDiffer(): bool
    {
        return $this->local_checksum !== $this->odoo_checksum;
    }

    public static function findByLocalRecord(string $model, int $id): ?self
    {
        return static::forLocalRecord($model, $id)->first();
    }

    public static function findByOdooId(int $entityMappingId, int $odooId): ?self
    {
        return static::where('entity_mapping_id', $entityMappingId)
            ->where('odoo_id', $odooId)
            ->first();
    }
}
