<?php

namespace XLinic\Framework\Core\Model\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAudit
{
    /**
     * Boot the HasAudit trait.
     */
    protected static function bootHasAudit(): void
    {
        // Create audit record on create
        static::created(function ($model) {
            $model->createAuditRecord('created', [], $model->getAuditableAttributes());
        });

        // Create audit record on update
        static::updated(function ($model) {
            $changes = $model->getChanges();
            $original = [];

            foreach (array_keys($changes) as $key) {
                $original[$key] = $model->getOriginal($key);
            }

            $model->createAuditRecord('updated', $original, $changes);
        });

        // Create audit record on delete
        static::deleted(function ($model) {
            $model->createAuditRecord('deleted', $model->getAuditableAttributes(), []);
        });
    }

    /**
     * Get audit records for this model.
     */
    public function audits(): MorphMany
    {
        return $this->morphMany(\XLinic\Framework\Core\Model\Audit::class, 'auditable');
    }

    /**
     * Create an audit record.
     */
    protected function createAuditRecord(string $event, array $oldValues = [], array $newValues = []): void
    {
        $tenantManager = app(\XLinic\Framework\Core\Tenancy\TenantManager::class);

        $this->audits()->create([
            'event' => $event,
            'old_values' => $this->filterAuditableAttributes($oldValues),
            'new_values' => $this->filterAuditableAttributes($newValues),
            'user_id' => auth()->id(),
            'tenant_id' => $tenantManager->getCurrentTenantId(),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'url' => request()?->fullUrl(),
            'created_at' => now(),
        ]);
    }

    /**
     * Get auditable attributes.
     */
    public function getAuditableAttributes(): array
    {
        $attributes = $this->getAttributes();

        // Remove non-auditable attributes
        $excluded = $this->getAuditExclude();

        return array_diff_key($attributes, array_flip($excluded));
    }

    /**
     * Get attributes to exclude from audit.
     */
    protected function getAuditExclude(): array
    {
        return array_merge(
            ['password', 'remember_token', 'email_verified_at'],
            $this->auditExclude ?? []
        );
    }

    /**
     * Filter auditable attributes.
     */
    protected function filterAuditableAttributes(array $attributes): array
    {
        $excluded = $this->getAuditExclude();

        return array_diff_key($attributes, array_flip($excluded));
    }

    /**
     * Get audit by event type.
     */
    public function auditsByEvent(string $event)
    {
        return $this->audits()->where('event', $event);
    }

    /**
     * Get latest audit record.
     */
    public function latestAudit()
    {
        return $this->audits()->latest()->first();
    }

    /**
     * Get audit history with user information.
     */
    public function getAuditHistory()
    {
        return $this->audits()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get changes between two audit records.
     */
    public function getChangesBetweenAudits($fromAudit, $toAudit): array
    {
        $changes = [];
        $fromValues = $fromAudit->new_values ?? [];
        $toValues = $toAudit->new_values ?? [];

        $allKeys = array_unique(array_merge(array_keys($fromValues), array_keys($toValues)));

        foreach ($allKeys as $key) {
            $from = $fromValues[$key] ?? null;
            $to = $toValues[$key] ?? null;

            if ($from !== $to) {
                $changes[$key] = [
                    'from' => $from,
                    'to' => $to,
                ];
            }
        }

        return $changes;
    }

    /**
     * Check if model has been audited.
     */
    public function hasAudits(): bool
    {
        return $this->audits()->exists();
    }

    /**
     * Get audit statistics.
     */
    public function getAuditStats(): array
    {
        $audits = $this->audits()->get();

        return [
            'total' => $audits->count(),
            'created' => $audits->where('event', 'created')->count(),
            'updated' => $audits->where('event', 'updated')->count(),
            'deleted' => $audits->where('event', 'deleted')->count(),
            'first_audit' => $audits->sortBy('created_at')->first()?->created_at,
            'last_audit' => $audits->sortByDesc('created_at')->first()?->created_at,
        ];
    }
}