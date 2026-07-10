<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Global do-not-email list. Checked at every send-job dispatch.
 */
class PlatformEmailSuppression extends Model
{
    use HasFactory;

    public const UPDATED_AT = null; // append-only

    public const REASON_UNSUBSCRIBED = 'unsubscribed';
    public const REASON_BOUNCED = 'bounced';
    public const REASON_COMPLAINED = 'complained';
    public const REASON_MANUAL = 'manual';

    public const REASONS = [
        self::REASON_UNSUBSCRIBED => 'Unsubscribed',
        self::REASON_BOUNCED => 'Bounced (hard)',
        self::REASON_COMPLAINED => 'Marked as spam',
        self::REASON_MANUAL => 'Manual (do-not-contact)',
    ];

    protected $fillable = [
        'email',
        'reason',
        'campaign_id',
        'note',
        'added_by_user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * @return bool true if this address is on the suppression list.
     */
    public static function suppresses(string $email): bool
    {
        return static::query()->where('email', mb_strtolower($email))->exists();
    }

    /**
     * Idempotent add — returns existing row if the address is already
     * suppressed, otherwise creates one.
     */
    public static function add(string $email, string $reason, array $extra = []): self
    {
        $email = mb_strtolower($email);

        return static::firstOrCreate(
            ['email' => $email],
            array_merge(['reason' => $reason], $extra),
        );
    }
}
