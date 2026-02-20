<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Sequence extends Model
{
    protected $fillable = [
        'code',
        'tenant_id',
        'current_number',
    ];

    protected $casts = [
        'current_number' => 'integer',
    ];

    /**
     * Get the next sequence number with row-level locking.
     * Uses SELECT FOR UPDATE to prevent race conditions.
     */
    public static function next(string $code, ?string $tenantId = null, array $options = []): string
    {
        $prefix = $options['prefix'] ?? strtoupper($code);
        $format = $options['format'] ?? '{prefix}-{number:6}';
        $startAt = $options['start_at'] ?? 1;
        $increment = $options['increment'] ?? 1;

        return DB::transaction(function () use ($code, $tenantId, $prefix, $format, $startAt, $increment) {
            // Lock the row for update to prevent race conditions
            $sequence = DB::table('sequences')
                ->where('code', $code)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                // Create new sequence
                DB::table('sequences')->insert([
                    'code' => $code,
                    'tenant_id' => $tenantId,
                    'current_number' => $startAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $nextNumber = $startAt;
            } else {
                // Increment existing sequence
                $nextNumber = $sequence->current_number + $increment;
                DB::table('sequences')
                    ->where('id', $sequence->id)
                    ->update([
                        'current_number' => $nextNumber,
                        'updated_at' => now(),
                    ]);
            }

            return static::formatSequence($format, $prefix, $nextNumber);
        });
    }

    /**
     * Get current sequence value without incrementing.
     */
    public static function current(string $code, ?string $tenantId = null): ?int
    {
        return DB::table('sequences')
            ->where('code', $code)
            ->where('tenant_id', $tenantId)
            ->value('current_number');
    }

    /**
     * Reset sequence to a specific value.
     */
    public static function reset(string $code, ?string $tenantId = null, int $number = 0): bool
    {
        return DB::table('sequences')
            ->where('code', $code)
            ->where('tenant_id', $tenantId)
            ->update([
                'current_number' => $number,
                'updated_at' => now(),
            ]) > 0;
    }

    /**
     * Preview the next sequence value without incrementing.
     */
    public static function preview(string $code, ?string $tenantId = null, array $options = []): string
    {
        $prefix = $options['prefix'] ?? strtoupper($code);
        $format = $options['format'] ?? '{prefix}-{number:6}';
        $startAt = $options['start_at'] ?? 1;

        $current = static::current($code, $tenantId);
        $nextNumber = $current !== null ? $current + 1 : $startAt;

        return static::formatSequence($format, $prefix, $nextNumber);
    }

    /**
     * Create or update multiple sequences at once.
     */
    public static function seed(array $sequences, ?string $tenantId = null): void
    {
        foreach ($sequences as $code => $options) {
            $startAt = is_array($options) ? ($options['start_at'] ?? 0) : 0;

            DB::table('sequences')->updateOrInsert(
                ['code' => $code, 'tenant_id' => $tenantId],
                [
                    'current_number' => $startAt,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    /**
     * Format the sequence number according to the format string.
     */
    protected static function formatSequence(string $format, string $prefix, int $number): string
    {
        $replacements = [
            '{prefix}' => $prefix,
            '{number}' => $number,
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

        return str_replace(array_keys($replacements), array_values($replacements), $format);
    }

    /**
     * Scope by tenant.
     */
    public function scopeForTenant($query, ?string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
