<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportMapping extends Model
{

    protected $fillable = [
        'tenant_id',
        'user_id',
        'resource_class',
        'name',
        'column_map',
        'options',
        'is_default',
    ];

    protected $casts = [
        'column_map' => 'array',
        'options' => 'array',
        'is_default' => 'boolean',
    ];

    /**
     * Get the user who created this mapping.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\Models\User::class);
    }

    /**
     * Scope to filter by resource class.
     */
    public function scopeForResource($query, string $resourceClass)
    {
        return $query->where('resource_class', $resourceClass);
    }

    /**
     * Scope to filter by tenant.
     */
    public function scopeForTenant($query, ?string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Get the default mapping for a resource.
     */
    public static function getDefault(string $resourceClass, ?string $tenantId = null): ?self
    {
        return static::query()
            ->forResource($resourceClass)
            ->forTenant($tenantId)
            ->where('is_default', true)
            ->first();
    }

    /**
     * Set this mapping as the default for its resource.
     */
    public function setAsDefault(): void
    {
        // Remove default from other mappings
        static::query()
            ->forResource($this->resource_class)
            ->forTenant($this->tenant_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }
}
