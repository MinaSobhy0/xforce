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
     * Explicitly prefixed with public schema to ensure correct table is used.
     */
    protected $table = 'public.tenants';

    /**
     * The connection to use (always public schema).
     * Uses 'central' connection which is never modified by tenant middleware.
     */
    protected $connection = 'central';

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
        'extra_users',
        'extra_branches',
        'extra_patients',
        'extra_storage_mb',
        'users_overage_at',
        'users_overage_notified',
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
        'extra_users' => 'integer',
        'extra_branches' => 'integer',
        'extra_patients' => 'integer',
        'extra_storage_mb' => 'integer',
        'users_overage_at' => 'datetime',
        'users_overage_notified' => 'boolean',
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

        static::creating(function (self $tenant) {
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

            // Default features (all modules enabled by default)
            $tenant->features = $tenant->features ?? [
                'users',
                'patients',
                'appointments',
                'treatments',
                'services',
                'inventory',
                'reports',
                'billing',
                'staff',
                'payroll',
                'attendance',
                'marketing',
                'accounting',
                'loyalty',
                'memberships',
                'gift_cards',
                'packages',
                'equipment',
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
                    INSERT INTO tenant_domains (tenant_id, domain, type, is_primary, is_verified, ssl_status, dns_verified_at, created_at, updated_at)
                    VALUES (?, ?, ?, true, true, ?, ?, ?, ?)
                ", [
                    $tenant->id,
                    $tenant->slug . '.x-linic.com',
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
                    INSERT INTO tenant_domains (tenant_id, domain, type, is_primary, is_verified, ssl_status, created_at, updated_at)
                    VALUES (?, ?, ?, false, false, ?, ?, ?)
                ", [
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
                    ->update(['domain' => $tenant->slug . '.x-linic.com']);
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
                        INSERT INTO tenant_domains (tenant_id, domain, type, is_primary, is_verified, ssl_status, created_at, updated_at)
                        VALUES (?, ?, ?, false, false, ?, ?, ?)
                    ", [
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

    /**
     * Get or create the usage record for this tenant.
     * Also computes actual usage from the tenant's database.
     */
    public function getOrCreateUsage(): TenantUsage
    {
        $usage = $this->usage;

        if (!$usage) {
            $usage = $this->usage()->create([
                'tenant_id' => $this->id,
            ]);
            $this->setRelation('usage', $usage);
        }

        return $usage;
    }

    /**
     * Compute and update usage statistics from tenant's actual data.
     */
    public function computeUsage(): TenantUsage
    {
        $usage = $this->getOrCreateUsage();

        // Only compute if tenant has a provisioned database
        if (!$this->database_name) {
            return $usage;
        }

        try {
            // Check if schema exists
            $schemaExists = \DB::select(
                "SELECT schema_name FROM information_schema.schemata WHERE schema_name = ?",
                [$this->database_name]
            );

            if (empty($schemaExists)) {
                return $usage;
            }

            // Switch to tenant schema and count records
            \DB::statement("SET search_path TO \"{$this->database_name}\"");

            $counts = [
                'users' => $this->countTable('users'),
                'branches' => $this->countTable('branches'),
                'patients' => $this->countTable('patients'),
                'appointments' => $this->countTable('appointments'),
                'treatments' => $this->countTable('treatments'),
                'equipment' => $this->countTable('equipment'),
                'products' => $this->countTable('products'),
            ];

            // Reset search path
            \DB::statement("SET search_path TO public");

            // Calculate database size (in MB)
            $dbSizeMb = $this->calculateDatabaseSize();

            // Calculate file storage (in MB)
            $fileStorage = $this->calculateFileStorage();

            // Total storage = DB size + file storage
            $counts['storage_mb'] = $dbSizeMb + ($fileStorage['total'] ?? 0);
            $counts['storage_photos_mb'] = $fileStorage['photos'] ?? 0;
            $counts['storage_documents_mb'] = $fileStorage['documents'] ?? 0;
            $counts['storage_consent_mb'] = $fileStorage['consent'] ?? 0;

            // Update usage record
            $usage->update($counts);
            $usage->refresh();

        } catch (\Exception $e) {
            // Reset search path on error
            try {
                \DB::statement("SET search_path TO public");
            } catch (\Exception $ignored) {}

            \Log::warning('Failed to compute tenant usage', [
                'tenant_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $usage;
    }

    /**
     * Calculate PostgreSQL schema size in MB.
     */
    protected function calculateDatabaseSize(): int
    {
        try {
            $result = \DB::select("
                SELECT COALESCE(SUM(pg_total_relation_size(quote_ident(schemaname) || '.' || quote_ident(tablename))), 0) as size_bytes
                FROM pg_tables
                WHERE schemaname = ?
            ", [$this->database_name]);

            $sizeBytes = $result[0]->size_bytes ?? 0;
            return (int) ceil($sizeBytes / (1024 * 1024)); // Convert to MB
        } catch (\Exception $e) {
            \Log::warning('Failed to calculate database size', [
                'tenant_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Calculate file storage usage in MB.
     */
    protected function calculateFileStorage(): array
    {
        $storage = [
            'total' => 0,
            'photos' => 0,
            'documents' => 0,
            'consent' => 0,
        ];

        // Tenant storage path
        $basePath = storage_path("app/tenants/{$this->id}");

        if (!is_dir($basePath)) {
            return $storage;
        }

        try {
            // Calculate photos storage
            $photosPath = $basePath . '/photos';
            if (is_dir($photosPath)) {
                $storage['photos'] = $this->getDirectorySizeMb($photosPath);
            }

            // Calculate documents storage
            $documentsPath = $basePath . '/documents';
            if (is_dir($documentsPath)) {
                $storage['documents'] = $this->getDirectorySizeMb($documentsPath);
            }

            // Calculate consent forms storage
            $consentPath = $basePath . '/consent';
            if (is_dir($consentPath)) {
                $storage['consent'] = $this->getDirectorySizeMb($consentPath);
            }

            // Calculate total (including any other subdirectories)
            $storage['total'] = $this->getDirectorySizeMb($basePath);

        } catch (\Exception $e) {
            \Log::warning('Failed to calculate file storage', [
                'tenant_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $storage;
    }

    /**
     * Get directory size in MB.
     */
    protected function getDirectorySizeMb(string $path): int
    {
        $sizeBytes = 0;

        if (!is_dir($path)) {
            return 0;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $sizeBytes += $file->getSize();
            }
        }

        return (int) ceil($sizeBytes / (1024 * 1024)); // Convert to MB
    }

    /**
     * Count records in a tenant table (helper).
     */
    protected function countTable(string $table): int
    {
        try {
            $result = \DB::select("SELECT COUNT(*) as count FROM \"{$table}\"");
            return $result[0]->count ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
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

    /**
     * Get effective limit for a resource (plan limit + additional purchased).
     * Returns null for unlimited resources.
     */
    public function getEffectiveLimit(string $resource): ?int
    {
        // These resources are unlimited
        $unlimitedResources = ['treatments', 'products', 'equipment', 'patients'];
        if (in_array($resource, $unlimitedResources)) {
            return null; // null = unlimited
        }

        // Get base limit from plan
        $planLimit = $this->plan?->{"max_{$resource}"} ?? 0;

        // Get additional purchased (stored in tenant's max_* fields as extra)
        // If tenant has a plan, treat tenant's max_* as "additional" on top of plan
        // If no plan, use tenant's max_* as the total limit
        if ($this->plan) {
            $additional = $this->{"extra_{$resource}"} ?? 0;
            return $planLimit + $additional;
        }

        // Fallback to tenant's own limits if no plan
        return $this->{"max_{$resource}"} ?? match ($resource) {
            'users' => 10,
            'branches' => 1,
            'patients' => 1000,
            'storage_mb' => 1024,
            default => 0,
        };
    }

    /**
     * Get plan limit for a resource (without additional).
     */
    public function getPlanLimit(string $resource): ?int
    {
        // Unlimited resources
        if (in_array($resource, ['treatments', 'products', 'equipment'])) {
            return null;
        }

        return $this->plan?->{"max_{$resource}"};
    }

    /**
     * Get additional purchased amount for a resource.
     */
    public function getAdditionalPurchased(string $resource): int
    {
        return $this->{"extra_{$resource}"} ?? 0;
    }

    public function canAddUser(): bool
    {
        $limit = $this->getEffectiveLimit('users');
        if ($limit === null) return true; // unlimited

        $current = $this->usage->users ?? 0;
        return $current < $limit;
    }

    public function canAddPatient(): bool
    {
        // Patients are unlimited
        return true;
    }

    public function canAddBranch(): bool
    {
        $limit = $this->getEffectiveLimit('branches');
        if ($limit === null) return true; // unlimited

        $current = $this->usage->branches ?? 0;
        return $current < $limit;
    }

    /**
     * Check if tenant is in user overage state.
     */
    public function isInUserOverage(): bool
    {
        return $this->users_overage_at !== null;
    }

    /**
     * Get remaining days in grace period for user overage.
     * Returns null if not in overage, 0 or negative if expired.
     */
    public function getUserOverageGraceDaysRemaining(): ?int
    {
        if (!$this->users_overage_at) {
            return null;
        }

        $gracePeriodEnds = $this->users_overage_at->addDays(14);
        return (int) now()->diffInDays($gracePeriodEnds, false);
    }

    /**
     * Check if user overage grace period has expired.
     */
    public function isUserOverageGraceExpired(): bool
    {
        if (!$this->users_overage_at) {
            return false;
        }

        return now()->greaterThan($this->users_overage_at->addDays(14));
    }

    /**
     * Get the user overage count (how many users over limit).
     */
    public function getUserOverageCount(): int
    {
        $limit = $this->getEffectiveLimit('users');
        if ($limit === null) {
            return 0;
        }

        $current = $this->usage->users ?? 0;
        return max(0, $current - $limit);
    }

    public function getRemainingStorage(): int
    {
        $limit = $this->getEffectiveLimit('storage_mb') ?? 1024;
        $used = $this->usage->storage_mb ?? 0;

        return max(0, $limit - $used);
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