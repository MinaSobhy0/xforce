<?php

namespace Modules\OdooIntegration\Models;

use XLinic\Framework\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\User;
use Modules\OdooIntegration\Enums\ConflictStatus;

class OdooSyncConflict extends BaseModel
{
    protected $table = 'odoo_sync_conflicts';

    protected $fillable = [
        'tenant_id',
        'sync_record_id',
        'entity_mapping_id',
        'status',
        'conflict_type',
        'local_data',
        'odoo_data',
        'diff',
        'resolution',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'local_data' => 'array',
        'odoo_data' => 'array',
        'diff' => 'array',
        'resolved_at' => 'datetime',
        'status' => ConflictStatus::class,
    ];

    // Conflict types
    public const TYPE_BOTH_MODIFIED = 'both_modified';
    public const TYPE_DELETED_LOCALLY = 'deleted_locally';
    public const TYPE_DELETED_REMOTELY = 'deleted_remotely';

    public const CONFLICT_TYPES = [
        self::TYPE_BOTH_MODIFIED => 'Both Modified',
        self::TYPE_DELETED_LOCALLY => 'Deleted Locally',
        self::TYPE_DELETED_REMOTELY => 'Deleted in Odoo',
    ];

    // Resolution options
    public const RESOLUTION_KEEP_LOCAL = 'keep_local';
    public const RESOLUTION_KEEP_ODOO = 'keep_odoo';
    public const RESOLUTION_MERGE = 'merge';
    public const RESOLUTION_SKIP = 'skip';

    public const RESOLUTIONS = [
        self::RESOLUTION_KEEP_LOCAL => 'Keep Local',
        self::RESOLUTION_KEEP_ODOO => 'Keep Odoo',
        self::RESOLUTION_MERGE => 'Merge',
        self::RESOLUTION_SKIP => 'Skip',
    ];

    // Relationships
    public function syncRecord(): BelongsTo
    {
        return $this->belongsTo(OdooSyncRecord::class, 'sync_record_id');
    }

    public function entityMapping(): BelongsTo
    {
        return $this->belongsTo(OdooEntityMapping::class, 'entity_mapping_id');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', ConflictStatus::PENDING);
    }

    public function scopeResolved($query)
    {
        return $query->where('status', ConflictStatus::RESOLVED);
    }

    public function scopeDismissed($query)
    {
        return $query->where('status', ConflictStatus::DISMISSED);
    }

    // Helper methods
    public function resolve(string $resolution, int $userId, ?string $notes = null): void
    {
        $this->update([
            'status' => ConflictStatus::RESOLVED,
            'resolution' => $resolution,
            'resolved_by' => $userId,
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);

        // Update sync record status
        $this->syncRecord->update(['sync_status' => OdooSyncRecord::STATUS_PENDING]);
    }

    public function dismiss(int $userId, ?string $notes = null): void
    {
        $this->update([
            'status' => ConflictStatus::DISMISSED,
            'resolved_by' => $userId,
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === ConflictStatus::PENDING;
    }

    public function isResolved(): bool
    {
        return $this->status === ConflictStatus::RESOLVED;
    }

    public function getConflictTypeLabelAttribute(): string
    {
        return self::CONFLICT_TYPES[$this->conflict_type] ?? $this->conflict_type;
    }

    public function getResolutionLabelAttribute(): ?string
    {
        return self::RESOLUTIONS[$this->resolution] ?? $this->resolution;
    }

    public function getChangedFields(): array
    {
        if (!$this->diff) {
            return [];
        }

        return array_keys($this->diff);
    }

    public function getFieldDiff(string $field): ?array
    {
        return $this->diff[$field] ?? null;
    }

    public static function createFromRecords(
        OdooSyncRecord $syncRecord,
        array $localData,
        array $odooData,
        string $conflictType = self::TYPE_BOTH_MODIFIED
    ): self {
        $diff = self::calculateDiff($localData, $odooData);

        return self::create([
            'tenant_id' => $syncRecord->tenant_id,
            'sync_record_id' => $syncRecord->id,
            'entity_mapping_id' => $syncRecord->entity_mapping_id,
            'status' => ConflictStatus::PENDING,
            'conflict_type' => $conflictType,
            'local_data' => $localData,
            'odoo_data' => $odooData,
            'diff' => $diff,
        ]);
    }

    protected static function calculateDiff(array $local, array $odoo): array
    {
        $diff = [];
        $allKeys = array_unique(array_merge(array_keys($local), array_keys($odoo)));

        foreach ($allKeys as $key) {
            $localValue = $local[$key] ?? null;
            $odooValue = $odoo[$key] ?? null;

            if ($localValue !== $odooValue) {
                $diff[$key] = [
                    'local' => $localValue,
                    'odoo' => $odooValue,
                ];
            }
        }

        return $diff;
    }
}
