<?php

namespace XLinic\Framework\Core\Model\Traits;

trait HasSequence
{
    /**
     * Boot the HasSequence trait.
     */
    protected static function bootHasSequence(): void
    {
        static::creating(function ($model) {
            $sequenceColumn = $model->getSequenceColumn();
            $sequenceCode = $model->getSequenceCode();

            if ($sequenceColumn && $sequenceCode && !$model->getAttribute($sequenceColumn)) {
                $model->setAttribute($sequenceColumn, $model->generateSequenceNumber());
            }
        });
    }

    /**
     * Get the sequence column name.
     */
    protected function getSequenceColumn(): ?string
    {
        return $this->sequenceColumn ?? null;
    }

    /**
     * Get the sequence code.
     */
    protected function getSequenceCode(): ?string
    {
        return $this->sequenceCode ?? strtoupper(class_basename($this));
    }

    /**
     * Get the sequence prefix.
     */
    protected function getSequencePrefix(): string
    {
        return $this->sequencePrefix ?? $this->getSequenceCode();
    }

    /**
     * Get the sequence format.
     */
    protected function getSequenceFormat(): string
    {
        return $this->sequenceFormat ?? '{prefix}-{number:6}';
    }

    /**
     * Generate the next sequence number.
     */
    protected function generateSequenceNumber(): string
    {
        $sequenceService = app(\XLinic\Framework\Core\Sequence\SequenceService::class);

        return $sequenceService->next($this->getSequenceCode(), [
            'prefix' => $this->getSequencePrefix(),
            'format' => $this->getSequenceFormat(),
            'tenant_id' => $this->getTenantId(),
        ]);
    }

    /**
     * Regenerate sequence number.
     */
    public function regenerateSequence(): void
    {
        $sequenceColumn = $this->getSequenceColumn();

        if ($sequenceColumn) {
            $this->setAttribute($sequenceColumn, $this->generateSequenceNumber());
        }
    }

    /**
     * Check if sequence is auto-generated.
     */
    public function hasAutoSequence(): bool
    {
        return !empty($this->getSequenceColumn()) && !empty($this->getSequenceCode());
    }

    /**
     * Get the sequence number only (without prefix).
     */
    public function getSequenceNumber(): ?int
    {
        $sequenceColumn = $this->getSequenceColumn();

        if (!$sequenceColumn || !$this->getAttribute($sequenceColumn)) {
            return null;
        }

        $sequence = $this->getAttribute($sequenceColumn);

        // Extract number from format like "INV-000123"
        if (preg_match('/(\d+)$/', $sequence, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Search by sequence number.
     */
    public function scopeBySequence($query, string $sequence)
    {
        $sequenceColumn = $this->getSequenceColumn();

        if (!$sequenceColumn) {
            return $query;
        }

        return $query->where($sequenceColumn, $sequence);
    }

    /**
     * Search by sequence pattern.
     */
    public function scopeBySequencePattern($query, string $pattern)
    {
        $sequenceColumn = $this->getSequenceColumn();

        if (!$sequenceColumn) {
            return $query;
        }

        return $query->where($sequenceColumn, 'like', $pattern);
    }

    /**
     * Get the latest sequence for this model type.
     */
    public static function getLatestSequence(): ?string
    {
        $instance = new static();
        $sequenceColumn = $instance->getSequenceColumn();

        if (!$sequenceColumn) {
            return null;
        }

        return static::orderByDesc($sequenceColumn)->value($sequenceColumn);
    }

    /**
     * Get the next available sequence.
     */
    public static function getNextSequence(): string
    {
        $instance = new static();
        return $instance->generateSequenceNumber();
    }
}