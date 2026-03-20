<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Core\Models\Tenant;

/**
 * TenantAppCode model - lives in public schema (central).
 * Used for mobile app QR codes, short codes, and deep links.
 */
class TenantAppCode extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'tenant_app_codes';

    /**
     * The connection to use (always central/public schema).
     */
    protected $connection = 'central';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'code',
        'type',
        'is_active',
        'expires_at',
        'usage_count',
        'max_uses',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'usage_count' => 'integer',
        'max_uses' => 'integer',
        'created_by' => 'integer',
    ];

    /**
     * Available code types.
     */
    public const TYPE_DEFAULT = 'default';
    public const TYPE_QR = 'qr';
    public const TYPE_INVITATION = 'invitation';
    public const TYPE_STAFF = 'staff';

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $appCode) {
            if (empty($appCode->code)) {
                $appCode->code = self::generateUniqueCode();
            }
        });
    }

    /**
     * Generate a unique app code.
     */
    public static function generateUniqueCode(int $length = 8): string
    {
        do {
            $code = strtoupper(Str::random($length));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * Get the tenant that owns this app code.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope a query to only include active codes.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) {
                $q->whereNull('max_uses')
                    ->orWhereRaw('usage_count < max_uses');
            });
    }

    /**
     * Scope a query to only include codes of a specific type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to find by code.
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', strtoupper($code));
    }

    /**
     * Check if the code is valid (active, not expired, not maxed out).
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->usage_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    /**
     * Check if the code has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if the code has reached its max uses.
     */
    public function isMaxedOut(): bool
    {
        return $this->max_uses !== null && $this->usage_count >= $this->max_uses;
    }

    /**
     * Increment the usage count.
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Get the deep link URL for this code.
     */
    public function getDeepLinkAttribute(): string
    {
        $scheme = config('mobile_api.deep_links.scheme', 'xlinic');
        return "{$scheme}://tenant/{$this->code}";
    }

    /**
     * Get the web link URL for this code.
     */
    public function getWebLinkAttribute(): string
    {
        // Use the app's URL for the join route
        return url("/app/join/{$this->code}");
    }

    /**
     * Get the QR code data (what should be encoded in the QR).
     */
    public function getQrDataAttribute(): string
    {
        return $this->deep_link;
    }

    /**
     * Get type label for display.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_DEFAULT => 'Default',
            self::TYPE_QR => 'QR Code',
            self::TYPE_INVITATION => 'Invitation',
            self::TYPE_STAFF => 'Staff',
            default => ucfirst($this->type),
        };
    }

    /**
     * Get available types for select options.
     */
    public static function getTypeOptions(): array
    {
        return [
            self::TYPE_DEFAULT => 'Default',
            self::TYPE_QR => 'QR Code',
            self::TYPE_INVITATION => 'Invitation',
            self::TYPE_STAFF => 'Staff',
        ];
    }
}
