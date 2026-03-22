<?php

namespace Modules\MobileApi\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\User;
use XLinic\Framework\Core\Model\BaseModel;

class DeviceToken extends BaseModel
{
    protected $table = 'device_tokens';

    protected $fillable = [
        'user_id',
        'device_id',
        'fcm_token',
        'platform',
        'app_version',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    // Platforms
    public const PLATFORM_IOS = 'ios';

    public const PLATFORM_ANDROID = 'android';

    public const PLATFORMS = [
        self::PLATFORM_IOS => 'iOS',
        self::PLATFORM_ANDROID => 'Android',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the user that owns this device token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope for active devices.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for iOS devices.
     */
    public function scopeIos($query)
    {
        return $query->where('platform', self::PLATFORM_IOS);
    }

    /**
     * Scope for Android devices.
     */
    public function scopeAndroid($query)
    {
        return $query->where('platform', self::PLATFORM_ANDROID);
    }

    /**
     * Scope for specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Mark device as inactive.
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * Mark device as active.
     */
    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    /**
     * Update last used timestamp.
     */
    public function touchLastUsed(): bool
    {
        return $this->update(['last_used_at' => now()]);
    }

    /**
     * Update FCM token.
     */
    public function updateToken(string $fcmToken): bool
    {
        return $this->update([
            'fcm_token' => $fcmToken,
            'is_active' => true,
            'last_used_at' => now(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Get the platform label.
     */
    public function getPlatformLabelAttribute(): string
    {
        return self::PLATFORMS[$this->platform] ?? $this->platform;
    }
}
