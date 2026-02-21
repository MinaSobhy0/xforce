<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LoginHistory extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'login_history';

    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'location',
        'status',
        'failure_reason',
        'logged_in_at',
        'logged_out_at',
        'session_duration',
        'meta',
    ];

    protected $casts = [
        'logged_in_at' => 'datetime',
        'logged_out_at' => 'datetime',
        'session_duration' => 'integer',
        'meta' => 'array',
    ];

    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_BLOCKED = 'blocked';

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::orderedUuid()->toString();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeBlocked($query)
    {
        return $query->where('status', self::STATUS_BLOCKED);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('logged_in_at', '>=', now()->subDays($days));
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function markLoggedOut(): void
    {
        $loggedOutAt = now();
        $duration = $this->logged_in_at->diffInSeconds($loggedOutAt);

        $this->update([
            'logged_out_at' => $loggedOutAt,
            'session_duration' => $duration,
        ]);
    }

    public function getBrowserAttribute(): ?string
    {
        if (!$this->user_agent) {
            return null;
        }

        // Simple browser detection
        if (str_contains($this->user_agent, 'Chrome')) {
            return 'Chrome';
        } elseif (str_contains($this->user_agent, 'Firefox')) {
            return 'Firefox';
        } elseif (str_contains($this->user_agent, 'Safari')) {
            return 'Safari';
        } elseif (str_contains($this->user_agent, 'Edge')) {
            return 'Edge';
        }

        return 'Unknown';
    }

    public function getPlatformAttribute(): ?string
    {
        if (!$this->user_agent) {
            return null;
        }

        // Simple platform detection
        if (str_contains($this->user_agent, 'Windows')) {
            return 'Windows';
        } elseif (str_contains($this->user_agent, 'Mac')) {
            return 'macOS';
        } elseif (str_contains($this->user_agent, 'Linux')) {
            return 'Linux';
        } elseif (str_contains($this->user_agent, 'Android')) {
            return 'Android';
        } elseif (str_contains($this->user_agent, 'iPhone') || str_contains($this->user_agent, 'iPad')) {
            return 'iOS';
        }

        return 'Unknown';
    }
}
