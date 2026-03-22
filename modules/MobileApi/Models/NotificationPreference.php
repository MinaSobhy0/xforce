<?php

namespace Modules\MobileApi\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\User;
use XLinic\Framework\Core\Model\BaseModel;

class NotificationPreference extends BaseModel
{
    protected $table = 'notification_preferences';

    protected $fillable = [
        'user_id',
        'type',
        'push_enabled',
        'email_enabled',
        'sms_enabled',
    ];

    protected $casts = [
        'push_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the user that owns this preference.
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
     * Scope for specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for specific type.
     */
    public function scopeForType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for push enabled.
     */
    public function scopePushEnabled($query)
    {
        return $query->where('push_enabled', true);
    }

    /**
     * Scope for email enabled.
     */
    public function scopeEmailEnabled($query)
    {
        return $query->where('email_enabled', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if push notifications are enabled.
     */
    public function isPushEnabled(): bool
    {
        return $this->push_enabled;
    }

    /**
     * Check if email notifications are enabled.
     */
    public function isEmailEnabled(): bool
    {
        return $this->email_enabled;
    }

    /**
     * Check if SMS notifications are enabled.
     */
    public function isSmsEnabled(): bool
    {
        return $this->sms_enabled;
    }

    /**
     * Enable push notifications.
     */
    public function enablePush(): bool
    {
        return $this->update(['push_enabled' => true]);
    }

    /**
     * Disable push notifications.
     */
    public function disablePush(): bool
    {
        return $this->update(['push_enabled' => false]);
    }

    /**
     * Get or create preference for user and type.
     */
    public static function getOrCreate(int $userId, string $type): self
    {
        return self::firstOrCreate(
            [
                'user_id' => $userId,
                'type' => $type,
            ],
            [
                'push_enabled' => true,
                'email_enabled' => false,
                'sms_enabled' => false,
            ]
        );
    }

    /**
     * Check if user has push enabled for a type.
     * Returns true by default if no preference exists.
     */
    public static function isPushEnabledFor(int $userId, string $type): bool
    {
        $preference = self::where('user_id', $userId)
            ->where('type', $type)
            ->first();

        return $preference?->push_enabled ?? true;
    }

    /**
     * Bulk update preferences for a user.
     */
    public static function updateBulk(int $userId, array $preferences): void
    {
        foreach ($preferences as $preference) {
            self::updateOrCreate(
                [
                    'user_id' => $userId,
                    'type' => $preference['type'],
                ],
                [
                    'push_enabled' => $preference['push_enabled'] ?? true,
                    'email_enabled' => $preference['email_enabled'] ?? false,
                    'sms_enabled' => $preference['sms_enabled'] ?? false,
                ]
            );
        }
    }

    /**
     * Get all preferences for a user as a keyed array.
     */
    public static function getAllForUser(int $userId): array
    {
        $preferences = self::where('user_id', $userId)->get()->keyBy('type');

        $result = [];
        foreach (PushNotification::getAvailableTypes() as $type => $label) {
            $pref = $preferences->get($type);
            $result[$type] = [
                'type' => $type,
                'label' => $label,
                'push_enabled' => $pref?->push_enabled ?? true,
                'email_enabled' => $pref?->email_enabled ?? false,
                'sms_enabled' => $pref?->sms_enabled ?? false,
            ];
        }

        return $result;
    }
}
