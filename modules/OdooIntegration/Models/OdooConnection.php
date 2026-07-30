<?php

namespace Modules\OdooIntegration\Models;

use XLinic\Framework\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Modules\OdooIntegration\Enums\ApiProtocol;

class OdooConnection extends BaseModel
{
    use SoftDeletes;

    protected $table = 'odoo_connections';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'host',
        'port',
        'database_name',
        'username',
        'password',
        'api_key',
        'protocol',
        'use_ssl',
        'timeout',
        'rate_limit_per_minute',
        'timezone',
        'is_active',
        'is_default',
        'last_connected_at',
        'last_sync_at',
        'settings',
    ];

    protected $casts = [
        'port' => 'integer',
        'use_ssl' => 'boolean',
        'timeout' => 'integer',
        'rate_limit_per_minute' => 'integer',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'last_connected_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'settings' => 'array',
        'protocol' => ApiProtocol::class,
    ];

    protected $hidden = [
        'password',
        'api_key',
    ];

    // Encrypted attributes
    protected function username(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? decrypt($value) : null,
            set: fn ($value) => $value ? encrypt($value) : null,
        );
    }

    protected function password(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? decrypt($value) : null,
            set: fn ($value) => $value ? encrypt($value) : null,
        );
    }

    protected function apiKey(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? decrypt($value) : null,
            set: fn ($value) => $value ? encrypt($value) : null,
        );
    }

    /**
     * Clean the host attribute - strip scheme and trailing slashes.
     */
    protected function host(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value,
            set: function ($value) {
                if (!$value) {
                    return null;
                }
                // Remove scheme if present
                $value = preg_replace('#^https?://#i', '', $value);
                // Remove trailing slashes
                $value = rtrim($value, '/');
                return $value;
            },
        );
    }

    // Relationships
    public function entityMappings(): HasMany
    {
        return $this->hasMany(OdooEntityMapping::class, 'odoo_connection_id');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(OdooSyncLog::class, 'connection_id');
    }

    /**
     * Secret to pass to Odoo's authenticate() call. API keys and passwords
     * are interchangeable at the protocol layer — Odoo checks both — but
     * an Odoo user with 2FA enabled has password auth disabled and *must*
     * authenticate with an API key. Prefer the api_key when present so
     * 2FA users don't hit "Invalid credentials".
     */
    public function getAuthSecret(): ?string
    {
        return filled($this->api_key) ? $this->api_key : $this->password;
    }

    // Computed attributes
    public function getBaseUrlAttribute(): string
    {
        $scheme = $this->use_ssl ? 'https' : 'http';

        // Strip any existing scheme from host
        $host = $this->host;
        $host = preg_replace('#^https?://#i', '', $host);
        $host = rtrim($host, '/');

        $port = $this->port != 443 && $this->port != 80 ? ":{$this->port}" : '';
        return "{$scheme}://{$host}{$port}";
    }

    public function getXmlRpcUrlAttribute(): string
    {
        return "{$this->base_url}/xmlrpc/2";
    }

    public function getRestApiUrlAttribute(): string
    {
        return "{$this->base_url}/api/v1";
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // Helper methods
    public function markConnected(): void
    {
        $this->update(['last_connected_at' => now()]);
    }

    public function markSynced(): void
    {
        $this->update(['last_sync_at' => now()]);
    }

    public function isConnectedRecently(): bool
    {
        return $this->last_connected_at && $this->last_connected_at->gt(now()->subMinutes(5));
    }

    public function getActiveMappings()
    {
        return $this->entityMappings()->where('is_active', true)->orderBy('priority')->get();
    }

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (OdooConnection $connection) {
            if (empty($connection->code)) {
                $connection->code = strtoupper(str_replace([' ', '-'], '_', $connection->name));
            }
        });

        static::saving(function (OdooConnection $connection) {
            // Ensure only one default connection per tenant
            if ($connection->is_default) {
                static::where('tenant_id', $connection->tenant_id)
                    ->where('id', '!=', $connection->id ?? 0)
                    ->update(['is_default' => false]);
            }
        });
    }
}
