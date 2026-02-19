<?php

namespace XLinic\Framework\Core\Model\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasPortalAccess
{
    /**
     * Boot the HasPortalAccess trait.
     */
    protected static function bootHasPortalAccess(): void
    {
        // Generate portal credentials on creating
        static::creating(function ($model) {
            if ($model->shouldGeneratePortalCredentials()) {
                $model->generatePortalCredentials();
            }
        });
    }

    /**
     * Get portal access tokens for this model.
     */
    public function portalTokens(): HasMany
    {
        return $this->hasMany(\Laravel\Sanctum\PersonalAccessToken::class, 'tokenable_id')
            ->where('tokenable_type', static::class)
            ->where('name', 'like', 'portal-%');
    }

    /**
     * Generate portal access credentials.
     */
    public function generatePortalCredentials(): void
    {
        if (!$this->hasPortalAccess()) {
            return;
        }

        // Generate a secure portal password if not set
        if (!$this->portal_password) {
            $this->portal_password = bcrypt($this->generateSecurePassword());
        }

        // Set portal access as enabled
        if (!isset($this->portal_enabled)) {
            $this->portal_enabled = true;
        }

        // Set last portal access
        $this->portal_last_access = null;
        $this->portal_created_at = now();
    }

    /**
     * Generate a secure password for portal access.
     */
    protected function generateSecurePassword(): string
    {
        return str_random(12);
    }

    /**
     * Create a portal access token.
     */
    public function createPortalToken(string $name = 'portal-access', array $abilities = ['*']): \Laravel\Sanctum\NewAccessToken
    {
        return $this->createToken($name, $abilities);
    }

    /**
     * Revoke all portal tokens.
     */
    public function revokePortalTokens(): void
    {
        $this->portalTokens()->delete();
    }

    /**
     * Check if this model can access the portal.
     */
    public function canAccessPortal(): bool
    {
        return $this->hasPortalAccess() &&
               $this->portal_enabled &&
               $this->isActive();
    }

    /**
     * Check if this model should have portal access.
     */
    protected function shouldGeneratePortalCredentials(): bool
    {
        return $this->hasPortalAccess() && !$this->portal_password;
    }

    /**
     * Check if this model supports portal access (override in model).
     */
    public function hasPortalAccess(): bool
    {
        return property_exists($this, 'hasPortalAccess') ? $this->hasPortalAccess : false;
    }

    /**
     * Check if the model is active (override in model if needed).
     */
    public function isActive(): bool
    {
        if (isset($this->attributes['is_active'])) {
            return $this->attributes['is_active'];
        }

        if (isset($this->attributes['active'])) {
            return $this->attributes['active'];
        }

        if (isset($this->attributes['status'])) {
            return $this->attributes['status'] === 'active';
        }

        return true; // Default to active
    }

    /**
     * Update last portal access timestamp.
     */
    public function updatePortalAccess(): void
    {
        $this->portal_last_access = now();
        $this->save();
    }

    /**
     * Get portal login URL.
     */
    public function getPortalLoginUrl(): string
    {
        $baseUrl = config('app.url');
        $portalPath = config('xlinic.portal.path', '/portal');

        return $baseUrl . $portalPath . '/login';
    }

    /**
     * Get portal dashboard URL.
     */
    public function getPortalDashboardUrl(): string
    {
        $baseUrl = config('app.url');
        $portalPath = config('xlinic.portal.path', '/portal');

        return $baseUrl . $portalPath . '/dashboard';
    }

    /**
     * Send portal welcome notification.
     */
    public function sendPortalWelcomeNotification(): void
    {
        // This would trigger a notification
        // Implementation depends on notification system
        activity()
            ->performedOn($this)
            ->log('Portal access granted');
    }

    /**
     * Disable portal access.
     */
    public function disablePortalAccess(): void
    {
        $this->portal_enabled = false;
        $this->revokePortalTokens();
        $this->save();

        activity()
            ->performedOn($this)
            ->log('Portal access disabled');
    }

    /**
     * Enable portal access.
     */
    public function enablePortalAccess(): void
    {
        if (!$this->hasPortalAccess()) {
            throw new \Exception('This model does not support portal access');
        }

        $this->portal_enabled = true;

        if (!$this->portal_password) {
            $this->generatePortalCredentials();
        }

        $this->save();

        activity()
            ->performedOn($this)
            ->log('Portal access enabled');
    }

    /**
     * Reset portal password.
     */
    public function resetPortalPassword(): string
    {
        $newPassword = $this->generateSecurePassword();
        $this->portal_password = bcrypt($newPassword);
        $this->revokePortalTokens(); // Force re-login
        $this->save();

        activity()
            ->performedOn($this)
            ->log('Portal password reset');

        return $newPassword;
    }

    /**
     * Get portal access statistics.
     */
    public function getPortalStats(): array
    {
        return [
            'has_access' => $this->hasPortalAccess(),
            'is_enabled' => $this->portal_enabled ?? false,
            'can_access' => $this->canAccessPortal(),
            'created_at' => $this->portal_created_at,
            'last_access' => $this->portal_last_access,
            'active_tokens' => $this->portalTokens()->count(),
        ];
    }

    /**
     * Scope to models with portal access.
     */
    public function scopeWithPortalAccess($query)
    {
        return $query->where('portal_enabled', true);
    }

    /**
     * Scope to models without portal access.
     */
    public function scopeWithoutPortalAccess($query)
    {
        return $query->where('portal_enabled', false)->orWhereNull('portal_enabled');
    }

    /**
     * Scope to models that accessed portal recently.
     */
    public function scopeRecentPortalActivity($query, int $days = 30)
    {
        return $query->where('portal_last_access', '>=', now()->subDays($days));
    }
}