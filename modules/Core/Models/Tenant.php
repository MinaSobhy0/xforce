<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Tenant model - lives in public schema, not subject to tenant scoping.
 * Uses base Laravel Model instead of BaseModel to avoid HasTenancy trait.
 */
class Tenant extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'tenants';

    /**
     * The connection to use (always public schema).
     * Uses 'central' connection which is never modified by tenant middleware.
     */
    protected $connection = 'central';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     */
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'database_name',
        'database_host',
        'database_port',
        'database_username',
        'database_password',
        'status',
        'settings',
        'features',
        'subscription_plan',
        'subscription_plan_id',
        'subscription_status',
        'subscription_expires_at',
        'trial_ends_at',
        'owner_user_id',
        'max_users',
        'max_branches',
        'max_patients',
        'max_storage_mb',
        'timezone',
        'locale',
        'currency',
        'tax_rate',
        'contact_name',
        'contact_email',
        'contact_phone',
        'address',
        'city',
        'country',
        'postal_code',
        'logo_url',
        'logo_path',
        'favicon_url',
        'favicon_path',
        'primary_color',
        'secondary_color',
        'custom_css',
        'meta',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'features' => 'array',
        'subscription_expires_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'max_users' => 'integer',
        'max_branches' => 'integer',
        'max_patients' => 'integer',
        'max_storage_mb' => 'integer',
        'tax_rate' => 'decimal:4',
        'meta' => 'array',
        'status' => TenantStatus::class,
        'is_active' => 'boolean',
    ];

    protected $dates = [
        'subscription_expires_at',
        'trial_ends_at',
        'deleted_at',
    ];

    protected static function boot(): void
    {
        parent::boot();

        // Auto-generate UUID on creating (since we're not using BaseModel)
        static::creating(function (self $tenant) {
            if (empty($tenant->id)) {
                $tenant->id = Str::orderedUuid()->toString();
            }

            if (empty($tenant->slug)) {
                $tenant->slug = Str::slug($tenant->name);
            }

            if (empty($tenant->database_name)) {
                $tenant->database_name = 'tenant_' . $tenant->slug;
            }

            // Set default values
            $tenant->status = $tenant->status ?? TenantStatus::ACTIVE;
            $tenant->timezone = $tenant->timezone ?? config('app.timezone');
            $tenant->locale = $tenant->locale ?? config('app.locale');
            $tenant->currency = $tenant->currency ?? 'EGP';
            $tenant->tax_rate = $tenant->tax_rate ?? 14.00; // Egyptian VAT
            $tenant->max_users = $tenant->max_users ?? 10;
            $tenant->max_branches = $tenant->max_branches ?? 1;
            $tenant->max_patients = $tenant->max_patients ?? 1000;
            $tenant->max_storage_mb = $tenant->max_storage_mb ?? 1024; // 1GB

            // Default features
            $tenant->features = $tenant->features ?? [
                'users',
                'patients',
                'appointments',
                'treatments',
                'inventory',
                'reports',
            ];

            // Default settings
            $tenant->settings = array_merge([
                'appointment_duration_minutes' => 30,
                'booking_advance_days' => 30,
                'booking_cutoff_hours' => 2,
                'auto_confirm_appointments' => false,
                'send_sms_reminders' => true,
                'send_email_reminders' => true,
                'require_payment_confirmation' => false,
                'allow_online_booking' => true,
                'allow_patient_cancellation' => true,
                'patient_portal_enabled' => true,
            ], $tenant->settings ?? []);
        });

        // Auto-create subdomain record when tenant is created
        static::created(function (self $tenant) {
            if ($tenant->slug) {
                \DB::statement("
                    INSERT INTO tenant_domains (id, tenant_id, domain, type, is_primary, is_verified, ssl_status, dns_verified_at, created_at, updated_at)
                    VALUES (?, ?, ?, ?, true, true, ?, ?, ?, ?)
                ", [
                    (string) Str::uuid(),
                    $tenant->id,
                    $tenant->slug . '.xlinic.com',
                    'subdomain',
                    'valid',
                    now(),
                    now(),
                    now(),
                ]);
            }

            // Also create custom domain if provided
            if ($tenant->domain) {
                \DB::statement("
                    INSERT INTO tenant_domains (id, tenant_id, domain, type, is_primary, is_verified, ssl_status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, false, false, ?, ?, ?)
                ", [
                    (string) Str::uuid(),
                    $tenant->id,
                    $tenant->domain,
                    'custom',
                    'pending',
                    now(),
                    now(),
                ]);
            }
        });

        // Auto-update domain records when tenant is updated
        static::updated(function (self $tenant) {
            // Update subdomain if slug changed
            if ($tenant->isDirty('slug')) {
                $tenant->domains()
                    ->where('type', 'subdomain')
                    ->update(['domain' => $tenant->slug . '.xlinic.com']);
            }

            // Handle custom domain changes
            if ($tenant->isDirty('domain')) {
                $oldDomain = $tenant->getOriginal('domain');
                $newDomain = $tenant->domain;

                // Remove old custom domain if it existed
                if ($oldDomain) {
                    $tenant->domains()->where('domain', $oldDomain)->delete();
                }

                // Add new custom domain if provided
                if ($newDomain) {
                    \DB::statement("
                        INSERT INTO tenant_domains (id, tenant_id, domain, type, is_primary, is_verified, ssl_status, created_at, updated_at)
                        VALUES (?, ?, ?, ?, false, false, ?, ?, ?)
                    ", [
                        (string) Str::uuid(),
                        $tenant->id,
                        $newDomain,
                        'custom',
                        'pending',
                        now(),
                        now(),
                    ]);
                }
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('status', TenantStatus::ACTIVE);
    }

    public function scopeByDomain($query, string $domain)
    {
        return $query->where('domain', $domain);
    }

    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    public function users(): HasMany
    {
        return $this->hasMany(\Modules\Auth\Models\User::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(\Modules\Core\Models\TenantSubscription::class);
    }

    public function usage(): HasOne
    {
        return $this->hasOne(\Modules\Core\Models\TenantUsage::class);
    }

    public function plan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(\App\Models\PlatformInvoice::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(\App\Models\TenantActivityLog::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(\App\Models\SupportTicket::class);
    }

    public function addonSubscriptions(): HasMany
    {
        return $this->hasMany(\App\Models\TenantAddonSubscription::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(\App\Models\TenantDomain::class);
    }

    public function primaryDomain(): HasOne
    {
        return $this->hasOne(\App\Models\TenantDomain::class)->where('is_primary', true);
    }

    public function addOns(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Models\AddOn::class, 'tenant_add_ons')
            ->withPivot(['activated_at', 'expires_at', 'billing_interval', 'price', 'status'])
            ->withTimestamps();
    }

    public function activeAddOns(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->addOns()
            ->wherePivot('status', 'active')
            ->where(function ($query) {
                $query->whereNull('tenant_add_ons.expires_at')
                    ->orWhere('tenant_add_ons.expires_at', '>', now());
            });
    }

    public function getDatabaseConnectionName(): string
    {
        return "tenant_{$this->id}";
    }

    public function getDatabaseConfig(): array
    {
        return [
            'driver' => 'pgsql',
            'host' => $this->database_host ?? config('database.connections.pgsql.host'),
            'port' => $this->database_port ?? config('database.connections.pgsql.port'),
            'database' => $this->database_name,
            'username' => $this->database_username ?? config('database.connections.pgsql.username'),
            'password' => $this->database_password ?? config('database.connections.pgsql.password'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'schema' => $this->database_name,
            'sslmode' => 'prefer',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === TenantStatus::ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === TenantStatus::SUSPENDED;
    }

    public function isExpired(): bool
    {
        return $this->subscription_expires_at && $this->subscription_expires_at->isPast();
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
    }

    public function getUsagePercentage(string $metric): float
    {
        if (!$this->usage) {
            return 0.0;
        }

        $current = $this->usage->{$metric} ?? 0;
        $limit = $this->{"max_{$metric}"} ?? 1;

        return min(100, ($current / $limit) * 100);
    }

    public function canAddUser(): bool
    {
        if (!$this->usage) {
            return true;
        }

        return ($this->usage->users ?? 0) < ($this->max_users ?? 10);
    }

    public function canAddPatient(): bool
    {
        if (!$this->usage) {
            return true;
        }

        return ($this->usage->patients ?? 0) < ($this->max_patients ?? 1000);
    }

    public function canAddBranch(): bool
    {
        if (!$this->usage) {
            return true;
        }

        return ($this->usage->branches ?? 0) < ($this->max_branches ?? 1);
    }

    public function getRemainingStorage(): int
    {
        if (!$this->usage) {
            return $this->max_storage_mb ?? 1024;
        }

        return max(0, ($this->max_storage_mb ?? 1024) - ($this->usage->storage_mb ?? 0));
    }

    public function getDisplayName(): string
    {
        return $this->name;
    }

    public function getUrl(): string
    {
        if ($this->domain) {
            return "https://{$this->domain}";
        }

        return config('app.url') . "/tenant/{$this->slug}";
    }

    public function getLogoUrl(): ?string
    {
        if ($this->logo_url && filter_var($this->logo_url, FILTER_VALIDATE_URL)) {
            return $this->logo_url;
        }

        if ($this->logo_url) {
            return asset("storage/tenants/{$this->id}/logo/" . $this->logo_url);
        }

        return null;
    }

    public function toArray(): array
    {
        $array = parent::toArray();

        // Add computed attributes
        $array['is_active'] = $this->isActive();
        $array['is_suspended'] = $this->isSuspended();
        $array['is_expired'] = $this->isExpired();
        $array['display_name'] = $this->getDisplayName();
        $array['url'] = $this->getUrl();
        $array['logo_url'] = $this->getLogoUrl();

        return $array;
    }
}