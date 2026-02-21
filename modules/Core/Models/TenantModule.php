<?php

namespace Modules\Core\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantModule extends BaseModel
{
    use HasTenancy;

    protected $fillable = [
        'tenant_id',
        'module_code',
        'is_active',
        'activated_at',
        'deactivated_at',
        'activated_by',
        'settings',
        'license_key',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'expires_at' => 'datetime',
        'settings' => 'array',
    ];

    /**
     * Get the tenant this module belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user who activated this module.
     */
    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\Models\User::class, 'activated_by');
    }

    /**
     * Scope to active modules only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to inactive modules only.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope to a specific module code.
     */
    public function scopeForModule($query, string $moduleCode)
    {
        return $query->where('module_code', $moduleCode);
    }

    /**
     * Scope to non-expired modules.
     */
    public function scopeValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Check if the module license is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if the module is valid (active and not expired).
     */
    public function isValid(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    /**
     * Activate the module.
     */
    public function activate(?string $userId = null): self
    {
        $this->update([
            'is_active' => true,
            'activated_at' => now(),
            'activated_by' => $userId,
            'deactivated_at' => null,
        ]);

        return $this;
    }

    /**
     * Deactivate the module.
     */
    public function deactivate(): self
    {
        $this->update([
            'is_active' => false,
            'deactivated_at' => now(),
        ]);

        return $this;
    }

    /**
     * Get a setting value.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Set a setting value.
     */
    public function setSetting(string $key, mixed $value): self
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
        $this->save();

        return $this;
    }
}
