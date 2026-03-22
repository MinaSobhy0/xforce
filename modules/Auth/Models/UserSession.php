<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'ip_address',
        'user_agent',
        'payload',
        'last_activity',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'last_activity' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * SECURITY: Override resolveRouteBinding to scope sessions to authenticated user.
     * This prevents cross-user session deletion via API routes.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $user = auth()->user();

        // If no authenticated user, return null (will trigger 404)
        if (!$user) {
            return null;
        }

        // Admins can access any session (for user management)
        if (method_exists($user, 'hasRole') && $user->hasRole(['super_admin', 'admin'])) {
            return $this->where($field ?? $this->getRouteKeyName(), $value)->first();
        }

        // Regular users can only access their own sessions
        return $this->where($field ?? $this->getRouteKeyName(), $value)
            ->where('user_id', $user->id)
            ->first();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                     ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function terminate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function refresh(): void
    {
        $this->update([
            'last_activity' => now(),
            'expires_at' => now()->addMinutes(config('session.lifetime', 120)),
        ]);
    }
}
