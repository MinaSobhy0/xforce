<?php

namespace Modules\Marketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ShortLink extends Model
{
    protected $table = 'short_links';

    protected $fillable = [
        'tenant_id',
        'code',
        'target_url',
        'action',
        'appointment_id',
        'expires_at',
        'clicks',
        'clicked_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'clicked_at' => 'datetime',
        'clicks' => 'integer',
    ];

    /**
     * Generate a unique short code.
     */
    public static function generateCode(): string
    {
        do {
            $code = Str::random(6);
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /**
     * Create a short link for a URL.
     */
    public static function createFor(
        string $targetUrl,
        ?string $tenantId = null,
        ?string $action = null,
        ?int $appointmentId = null,
        ?int $expiresInDays = 7
    ): self {
        // Get tenant_id from various sources
        $tenantId = $tenantId
            ?? tenant()?->id
            ?? session('tenant_id')
            ?? \Modules\Core\Models\Tenant::current()?->id
            ?? 1; // Fallback for single-tenant context

        return static::create([
            'tenant_id' => $tenantId,
            'code' => static::generateCode(),
            'target_url' => $targetUrl,
            'action' => $action,
            'appointment_id' => $appointmentId,
            'expires_at' => $expiresInDays ? now()->addDays($expiresInDays) : null,
        ]);
    }

    /**
     * Get the short URL.
     */
    public function getShortUrlAttribute(): string
    {
        // Use the current request's host to generate URL on same domain
        $baseUrl = request()->getSchemeAndHttpHost();
        return "{$baseUrl}/l/{$this->code}";
    }

    /**
     * Check if the link is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Record a click.
     */
    public function recordClick(): void
    {
        $this->increment('clicks');
        $this->update(['clicked_at' => now()]);
    }
}
