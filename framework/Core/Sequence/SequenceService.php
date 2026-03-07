<?php

namespace XLinic\Framework\Core\Sequence;

use Illuminate\Support\Facades\DB;
use XLinic\Framework\Core\Tenancy\TenantManager;

class SequenceService
{
    public function __construct(
        protected TenantManager $tenantManager
    ) {
    }

    /**
     * Get the next sequence number for a code.
     */
    public function next(string $code, array $options = []): string
    {
        // Use tenant_id from options if provided, otherwise from TenantManager
        $tenantId = $options['tenant_id'] ?? $this->tenantManager->getCurrentTenantId();
        $prefix = $options['prefix'] ?? strtoupper($code);
        $format = $options['format'] ?? '{prefix}-{number:6}';
        $startAt = $options['start_at'] ?? 1;
        $increment = $options['increment'] ?? 1;

        // Get or create sequence record
        $sequence = $this->getOrCreateSequence($code, $tenantId, $startAt);

        // Increment the sequence
        DB::table('sequences')
            ->where('id', $sequence->id)
            ->increment('current_number', $increment);

        $nextNumber = $sequence->current_number + $increment;

        // Format the sequence number
        return $this->formatSequence($format, $prefix, $nextNumber, $options);
    }

    /**
     * Get the current sequence number without incrementing.
     */
    public function current(string $code): ?int
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();

        $sequence = DB::table('sequences')
            ->where('code', $code)
            ->where('tenant_id', $tenantId)
            ->first();

        return $sequence?->current_number;
    }

    /**
     * Reset a sequence to a specific number.
     */
    public function reset(string $code, int $number = 1): bool
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();

        return DB::table('sequences')
            ->where('code', $code)
            ->where('tenant_id', $tenantId)
            ->update(['current_number' => $number]) > 0;
    }

    /**
     * Set a sequence to a specific number.
     */
    public function set(string $code, int $number): bool
    {
        return $this->reset($code, $number);
    }

    /**
     * Delete a sequence.
     */
    public function delete(string $code): bool
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();

        return DB::table('sequences')
            ->where('code', $code)
            ->where('tenant_id', $tenantId)
            ->delete() > 0;
    }

    /**
     * Get all sequences for current tenant.
     */
    public function all(): array
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();

        return DB::table('sequences')
            ->where('tenant_id', $tenantId)
            ->orderBy('code')
            ->get()
            ->toArray();
    }

    /**
     * Check if a sequence exists.
     */
    public function exists(string $code): bool
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();

        return DB::table('sequences')
            ->where('code', $code)
            ->where('tenant_id', $tenantId)
            ->exists();
    }

    /**
     * Get or create a sequence record.
     */
    protected function getOrCreateSequence(string $code, ?string $tenantId, int $startAt): object
    {
        $sequence = DB::table('sequences')
            ->where('code', $code)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$sequence) {
            $id = DB::table('sequences')->insertGetId([
                'code' => $code,
                'tenant_id' => $tenantId,
                'current_number' => $startAt - 1, // Start at -1 so first increment gives startAt
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = (object) [
                'id' => $id,
                'code' => $code,
                'tenant_id' => $tenantId,
                'current_number' => $startAt - 1,
            ];
        }

        return $sequence;
    }

    /**
     * Format the sequence number according to the format string.
     */
    protected function formatSequence(string $format, string $prefix, int $number, array $options = []): string
    {
        // Support custom date formats
        $dateFormat = $options['date_format'] ?? 'Y';
        $currentDate = now()->format($dateFormat);

        // Replace placeholders
        $replacements = [
            '{prefix}' => $prefix,
            '{number}' => $number,
            '{date}' => $currentDate,
            '{year}' => now()->format('Y'),
            '{month}' => now()->format('m'),
            '{day}' => now()->format('d'),
        ];

        // Handle padded numbers like {number:6}
        $format = preg_replace_callback(
            '/\{number:(\d+)\}/',
            fn($matches) => str_pad($number, (int) $matches[1], '0', STR_PAD_LEFT),
            $format
        );

        // Handle padded dates like {date:Ym:8}
        $format = preg_replace_callback(
            '/\{date:([^:]+):(\d+)\}/',
            fn($matches) => str_pad(now()->format($matches[1]), (int) $matches[2], '0', STR_PAD_LEFT),
            $format
        );

        return str_replace(array_keys($replacements), array_values($replacements), $format);
    }

    /**
     * Preview what the next sequence would look like without incrementing.
     */
    public function preview(string $code, array $options = []): string
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();
        $prefix = $options['prefix'] ?? strtoupper($code);
        $format = $options['format'] ?? '{prefix}-{number:6}';
        $startAt = $options['start_at'] ?? 1;
        $increment = $options['increment'] ?? 1;

        $sequence = DB::table('sequences')
            ->where('code', $code)
            ->where('tenant_id', $tenantId)
            ->first();

        $nextNumber = $sequence ? $sequence->current_number + $increment : $startAt;

        return $this->formatSequence($format, $prefix, $nextNumber, $options);
    }

    /**
     * Bulk create sequences.
     */
    public function bulkCreate(array $sequences): void
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();
        $records = [];

        foreach ($sequences as $code => $options) {
            if ($this->exists($code)) {
                continue;
            }

            $records[] = [
                'code' => $code,
                'tenant_id' => $tenantId,
                'current_number' => ($options['start_at'] ?? 1) - 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($records)) {
            DB::table('sequences')->insert($records);
        }
    }

    /**
     * Get sequence statistics.
     */
    public function getStats(): array
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();

        $sequences = DB::table('sequences')
            ->where('tenant_id', $tenantId)
            ->get();

        return [
            'total_sequences' => $sequences->count(),
            'max_number' => $sequences->max('current_number') ?? 0,
            'min_number' => $sequences->min('current_number') ?? 0,
            'avg_number' => round($sequences->avg('current_number') ?? 0, 2),
            'total_generated' => $sequences->sum('current_number'),
        ];
    }

    /**
     * Cleanup old sequences (optional maintenance).
     */
    public function cleanup(int $daysOld = 90): int
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();

        return DB::table('sequences')
            ->where('tenant_id', $tenantId)
            ->where('updated_at', '<', now()->subDays($daysOld))
            ->where('current_number', 0) // Only cleanup unused sequences
            ->delete();
    }
}