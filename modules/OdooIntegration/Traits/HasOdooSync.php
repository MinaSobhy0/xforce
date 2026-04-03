<?php

namespace Modules\OdooIntegration\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait for models that can be synced with Odoo.
 *
 * Add this trait to any model that has odoo_id and odoo_synced_at columns.
 */
trait HasOdooSync
{
    /**
     * Get the Odoo ID column name.
     */
    public function getOdooIdColumn(): string
    {
        return 'odoo_id';
    }

    /**
     * Get the Odoo synced at column name.
     */
    public function getOdooSyncedAtColumn(): string
    {
        return 'odoo_synced_at';
    }

    /**
     * Scope to records that have an Odoo ID.
     */
    public function scopeWithOdooId(Builder $query): Builder
    {
        return $query->whereNotNull($this->getOdooIdColumn());
    }

    /**
     * Scope to records that don't have an Odoo ID.
     */
    public function scopeWithoutOdooId(Builder $query): Builder
    {
        return $query->whereNull($this->getOdooIdColumn());
    }

    /**
     * Scope to records synced after a certain time.
     */
    public function scopeSyncedAfter(Builder $query, $datetime): Builder
    {
        return $query->where($this->getOdooSyncedAtColumn(), '>', $datetime);
    }

    /**
     * Scope to records that need syncing (modified after last sync).
     */
    public function scopeNeedsSyncing(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull($this->getOdooSyncedAtColumn())
                ->orWhereColumn('updated_at', '>', $this->getOdooSyncedAtColumn());
        });
    }

    /**
     * Mark the record as synced with Odoo.
     */
    public function markAsSynced(int $odooId): void
    {
        $this->update([
            $this->getOdooIdColumn() => $odooId,
            $this->getOdooSyncedAtColumn() => now(),
        ]);
    }

    /**
     * Clear Odoo sync data.
     */
    public function clearOdooSync(): void
    {
        $this->update([
            $this->getOdooIdColumn() => null,
            $this->getOdooSyncedAtColumn() => null,
        ]);
    }

    /**
     * Check if the record is synced with Odoo.
     */
    public function isSyncedWithOdoo(): bool
    {
        return !is_null($this->{$this->getOdooIdColumn()});
    }

    /**
     * Check if the record needs to be synced.
     */
    public function needsOdooSync(): bool
    {
        $syncedAt = $this->{$this->getOdooSyncedAtColumn()};

        if (!$syncedAt) {
            return true;
        }

        return $this->updated_at > $syncedAt;
    }

    /**
     * Get the Odoo ID.
     */
    public function getOdooId(): ?int
    {
        return $this->{$this->getOdooIdColumn()};
    }

    /**
     * Get the last sync timestamp.
     */
    public function getOdooSyncedAt(): ?\DateTimeInterface
    {
        return $this->{$this->getOdooSyncedAtColumn()};
    }
}
