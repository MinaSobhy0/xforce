<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDomain extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'domain',
        'type',
        'is_primary',
        'is_verified',
        'ssl_status',
        'ssl_expires_at',
        'dns_verified_at',
        'verification_token',
        'notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_verified' => 'boolean',
        'ssl_expires_at' => 'datetime',
        'dns_verified_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            foreach (['is_primary', 'is_verified'] as $field) {
                if (isset($model->$field)) {
                    $model->$field = filter_var($model->$field, FILTER_VALIDATE_BOOLEAN);
                }
            }
        });
    }

    public const TYPES = [
        'subdomain' => 'Subdomain',
        'custom' => 'Custom Domain',
    ];

    public const SSL_STATUSES = [
        'pending' => 'Pending',
        'valid' => 'Valid',
        'expiring' => 'Expiring Soon',
        'expired' => 'Expired',
        'failed' => 'Failed',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\Modules\Core\Models\Tenant::class);
    }

    public function scopeCustom($query)
    {
        return $query->where('type', 'custom');
    }

    public function scopeVerified($query)
    {
        return $query->whereRaw('is_verified = true');
    }

    public function scopeWithSslIssues($query)
    {
        return $query->whereIn('ssl_status', ['expiring', 'expired', 'failed']);
    }

    public function verifyDns(): bool
    {
        // In production, this would check DNS records
        // For now, we simulate verification
        $cname = dns_get_record($this->domain, DNS_CNAME);

        if ($cname && str_contains($cname[0]['target'] ?? '', 'xlinic.com')) {
            $this->update([
                'is_verified' => true,
                'dns_verified_at' => now(),
            ]);
            return true;
        }

        return false;
    }

    public function renewSsl(): void
    {
        // In production, this would trigger Let's Encrypt renewal
        $this->update([
            'ssl_status' => 'valid',
            'ssl_expires_at' => now()->addMonths(3),
        ]);
    }
}
