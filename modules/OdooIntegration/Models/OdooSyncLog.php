<?php

namespace Modules\OdooIntegration\Models;

use XLinic\Framework\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\User;
use Modules\OdooIntegration\Enums\SyncStatus;
use Modules\OdooIntegration\Enums\SyncDirection;

class OdooSyncLog extends BaseModel
{
    protected $table = 'odoo_sync_logs';

    protected $fillable = [
        'tenant_id',
        'connection_id',
        'entity_mapping_id',
        'sync_type',
        'direction',
        'status',
        'records_processed',
        'records_created',
        'records_updated',
        'records_failed',
        'conflicts_detected',
        'errors',
        'watermark',
        'started_at',
        'completed_at',
        'triggered_by',
    ];

    protected $casts = [
        'records_processed' => 'integer',
        'records_created' => 'integer',
        'records_updated' => 'integer',
        'records_failed' => 'integer',
        'conflicts_detected' => 'integer',
        'errors' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'status' => SyncStatus::class,
        'direction' => SyncDirection::class,
    ];

    // Sync types
    public const TYPE_FULL = 'full';
    public const TYPE_DELTA = 'delta';
    public const TYPE_SINGLE = 'single';

    public const SYNC_TYPES = [
        self::TYPE_FULL => 'Full Sync',
        self::TYPE_DELTA => 'Delta Sync',
        self::TYPE_SINGLE => 'Single Record',
    ];

    // Relationships
    public function connection(): BelongsTo
    {
        return $this->belongsTo(OdooConnection::class, 'connection_id');
    }

    public function entityMapping(): BelongsTo
    {
        return $this->belongsTo(OdooEntityMapping::class, 'entity_mapping_id');
    }

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', SyncStatus::PENDING);
    }

    public function scopeRunning($query)
    {
        return $query->where('status', SyncStatus::RUNNING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', SyncStatus::COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', SyncStatus::FAILED);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Helper methods
    public function start(): void
    {
        $this->update([
            'status' => SyncStatus::RUNNING,
            'started_at' => now(),
        ]);
    }

    public function complete(): void
    {
        $this->update([
            'status' => SyncStatus::COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function fail(array $errors = []): void
    {
        $this->update([
            'status' => SyncStatus::FAILED,
            'completed_at' => now(),
            'errors' => array_merge($this->errors ?? [], $errors),
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => SyncStatus::CANCELLED,
            'completed_at' => now(),
        ]);
    }

    public function incrementProcessed(int $count = 1): void
    {
        $this->increment('records_processed', $count);
    }

    public function incrementCreated(int $count = 1): void
    {
        $this->increment('records_created', $count);
    }

    public function incrementUpdated(int $count = 1): void
    {
        $this->increment('records_updated', $count);
    }

    public function incrementFailed(int $count = 1): void
    {
        $this->increment('records_failed', $count);
    }

    public function incrementConflicts(int $count = 1): void
    {
        $this->increment('conflicts_detected', $count);
    }

    public function addError(string $error): void
    {
        $errors = $this->errors ?? [];
        $errors[] = [
            'message' => $error,
            'timestamp' => now()->toIso8601String(),
        ];
        $this->update(['errors' => $errors]);
    }

    public function getDurationAttribute(): ?string
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }
        return $this->started_at->diffForHumans($this->completed_at, true);
    }

    public function getDurationSecondsAttribute(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }
        return $this->completed_at->diffInSeconds($this->started_at);
    }

    public function getSuccessRateAttribute(): float
    {
        if ($this->records_processed === 0) {
            return 0;
        }
        return round((($this->records_processed - $this->records_failed) / $this->records_processed) * 100, 2);
    }

    public function isRunning(): bool
    {
        return $this->status === SyncStatus::RUNNING;
    }

    public function isComplete(): bool
    {
        return $this->status === SyncStatus::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === SyncStatus::FAILED;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }
}
