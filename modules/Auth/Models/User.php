<?php

namespace Modules\Auth\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasPortalAccess;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use XLinic\Framework\Core\Model\Traits\EnforcesTenantLimits;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends BaseModel implements
    AuthenticatableContract,
    AuthorizableContract,
    CanResetPasswordContract,
    MustVerifyEmailContract,
    FilamentUser
{
    use Authenticatable,
        Authorizable,
        CanResetPassword,
        MustVerifyEmail,
        HasApiTokens,
        HasRoles,
        HasTenancy,
        HasPortalAccess,
        HasActivity,
        Notifiable,
        SoftDeletes,
        EnforcesTenantLimits,
        \App\Traits\TwoFactorAuthenticatable;

    /**
     * SECURITY: Tenant limit enforcement configuration.
     */
    protected string $tenantLimitField = 'max_users';
    protected string $tenantLimitResourceName = 'users';

    /**
     * The database connection for the model.
     * Must match Role and Permission models for Spatie permissions to work correctly.
     */
    protected $connection = 'tenant';

    /**
     * SECURITY: Only allow safe fields for mass assignment.
     * Sensitive fields like permissions_override, impersonation_token,
     * salary, commission_rate must be set explicitly via dedicated methods.
     */
    protected $fillable = [
        'tenant_id',
        'first_name',
        'last_name',
        'email',
        'username',
        'phone',
        'password',
        'avatar_url',
        'language',
        'timezone',
        'email_verified_at',
        'phone_verified_at',
        'employee_id',
        'department',
        'job_title',
        'hire_date',
        'birth_date',
        'gender',
        'address',
        'emergency_contact',
        'work_schedule',
        'settings',
        'preferences',
        'meta',
    ];

    /**
     * SECURITY: Fields that must never be mass-assigned.
     * These require explicit setter methods with proper authorization.
     */
    protected $guarded = [
        'id',
        'permissions_override',      // CRITICAL: Allows arbitrary permission grants
        'impersonation_token',       // CRITICAL: Allows account takeover
        'impersonation_token_expires_at',
        'salary',                    // HIGH: Financial data
        'commission_rate',           // HIGH: Financial data
        'status',                    // HIGH: Can reactivate suspended accounts
        'failed_login_attempts',     // MEDIUM: Can reset brute-force protection
        'locked_until',              // MEDIUM: Can unlock locked accounts
        'two_factor_enabled',        // MEDIUM: Can disable 2FA
        'two_factor_secret',         // MEDIUM: Can manipulate 2FA
        'two_factor_recovery_codes', // MEDIUM: Can manipulate 2FA
        'two_factor_confirmed_at',   // MEDIUM: Can bypass 2FA setup
        'must_change_password',      // MEDIUM: Security flow bypass
        'password_expires_at',       // MEDIUM: Security flow bypass
        'last_login_at',             // LOW: Audit trail
        'last_login_ip',             // LOW: Audit trail
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'impersonation_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password_expires_at' => 'datetime',
        'hire_date' => 'date',
        'birth_date' => 'date',
        'locked_until' => 'datetime',
        'must_change_password' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'failed_login_attempts' => 'integer',
        'commission_rate' => 'decimal:4',
        'salary' => 'decimal:2',
        'permissions_override' => 'array',
        'settings' => 'array',
        'preferences' => 'array',
        'meta' => 'array',
        'work_schedule' => 'array',
        'emergency_contact' => 'array',
        'status' => UserStatus::class,
        'gender' => Gender::class,
        'impersonation_token_expires_at' => 'datetime',
    ];

    protected $appends = [
        'name',
        'full_name',
        'initials',
        'display_name',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $user) {
            // Generate username if not provided
            if (empty($user->username)) {
                $user->username = $user->generateUsername();
            }

            // Set default values
            $user->status = $user->status ?? UserStatus::ACTIVE;
            $user->language = $user->language ?? config('app.locale');
            $user->timezone = $user->timezone ?? config('app.timezone');
            $user->password_expires_at = $user->password_expires_at ?? now()->addDays(90);

            // Default settings
            $user->settings = array_merge([
                'notifications' => [
                    'email' => true,
                    'sms' => false,
                    'push' => true,
                ],
                'dashboard' => [
                    'theme' => 'light',
                    'widgets' => ['appointments', 'patients', 'revenue'],
                ],
                'privacy' => [
                    'show_phone' => false,
                    'show_email' => true,
                ],
            ], $user->settings ?? []);

            // Default preferences
            $user->preferences = array_merge([
                'date_format' => 'Y-m-d',
                'time_format' => 'H:i',
                'currency_display' => 'symbol',
                'items_per_page' => 25,
            ], $user->preferences ?? []);
        });

        // SECURITY: Invalidate password reset tokens when password is changed
        // This prevents tokens from being reused after password is changed via other flows
        static::updating(function (self $user) {
            if ($user->isDirty('password')) {
                $user->invalidatePasswordResetTokens();
            }
        });
    }

    /**
     * SECURITY: Invalidate all password reset tokens for this user.
     * Called automatically when password is changed via any flow.
     */
    public function invalidatePasswordResetTokens(): void
    {
        // Get the password reset table name from config
        $table = config('auth.passwords.users.table', 'password_reset_tokens');

        // Delete all password reset tokens for this user's email
        \Illuminate\Support\Facades\DB::table($table)
            ->where('email', $this->email)
            ->delete();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\Modules\Core\Models\Tenant::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    public function loginHistory(): HasMany
    {
        return $this->hasMany(LoginHistory::class);
    }

    public function passwordHistory(): HasMany
    {
        return $this->hasMany(PasswordHistory::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function practitionerAppointments(): HasMany
    {
        return $this->hasMany(\Modules\Booking\Models\Appointment::class, 'practitioner_id');
    }

    public function qualifiedServices(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            \Modules\Services\Models\Service::class,
            'service_qualified_staff',
            'user_id',
            'service_id'
        )->withTimestamps();
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(\Modules\Staff\Models\StaffProfile::class, 'user_id');
    }

    /**
     * Get the user's branch role assignments.
     */
    public function branchRoles(): HasMany
    {
        return $this->hasMany(UserBranchRole::class);
    }

    /**
     * Get the user's active branch role assignments.
     */
    public function activeBranchRoles(): HasMany
    {
        return $this->hasMany(UserBranchRole::class)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Get the branches the user has access to.
     */
    public function allowedBranches(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            \Modules\Core\Models\Branch::class,
            'user_branch_roles',
            'user_id',
            'branch_id'
        )
        ->wherePivot('is_active', true)
        ->where(function ($q) {
            $q->whereNull('user_branch_roles.expires_at')
              ->orWhere('user_branch_roles.expires_at', '>', now());
        })
        ->distinct();
    }

    /**
     * Get the user's primary branch.
     */
    public function getPrimaryBranchAttribute(): ?\Modules\Core\Models\Branch
    {
        $primaryRole = $this->branchRoles()
            ->where('is_active', true)
            ->where('is_primary', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->first();

        return $primaryRole?->branch;
    }

    /**
     * Check if user has access to a specific branch.
     */
    public function hasAccessToBranch(string $branchId): bool
    {
        // Only tenant owner and super admin have automatic all-branch access
        if ($this->hasRole(['super-admin', 'super_admin', 'tenant-owner', 'tenant_owner', 'owner'])) {
            return true;
        }

        // Explicit permission for all-branch access
        if ($this->can('access-all-branches')) {
            return true;
        }

        return $this->activeBranchRoles()
            ->where('branch_id', $branchId)
            ->exists();
    }

    public function commissionRecords(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            \Modules\Staff\Models\StaffCommissionRecord::class,
            \Modules\Staff\Models\StaffProfile::class,
            'user_id',           // Foreign key on staff_profiles table
            'staff_profile_id',  // Foreign key on staff_commission_records table
            'id',                // Local key on users table
            'id'                 // Local key on staff_profiles table
        );
    }

    /**
     * Get the user's device tokens for push notifications.
     */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(\Modules\MobileApi\Models\DeviceToken::class);
    }

    /**
     * Get the user's push notifications.
     */
    public function pushNotifications(): HasMany
    {
        return $this->hasMany(\Modules\MobileApi\Models\PushNotification::class);
    }

    /**
     * Get the user's notification preferences.
     */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(\Modules\MobileApi\Models\NotificationPreference::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', UserStatus::ACTIVE);
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    public function scopeWithRole($query, string $role)
    {
        return $query->whereHas('roles', function ($q) use ($role) {
            $q->where('name', $role);
        });
    }

    public function scopeTwoFactorEnabled($query)
    {
        return $query->where('two_factor_enabled', true);
    }

    public function scopePasswordExpired($query)
    {
        return $query->where('password_expires_at', '<', now());
    }

    public function scopeLocked($query)
    {
        return $query->where('locked_until', '>', now());
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getNameAttribute(): string
    {
        return $this->full_name ?: $this->email;
    }

    public function getInitialsAttribute(): string
    {
        $firstInitial = $this->first_name ? substr($this->first_name, 0, 1) : '';
        $lastInitial = $this->last_name ? substr($this->last_name, 0, 1) : '';
        return strtoupper($firstInitial . $lastInitial);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->full_name ?: $this->username ?: $this->email;
    }

    public function getAvatarUrlAttribute($value): ?string
    {
        if ($value && filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        if ($value) {
            return asset("storage/avatars/{$value}");
        }

        // Generate Gravatar URL
        $hash = md5(strtolower(trim($this->email)));
        return "https://www.gravatar.com/avatar/{$hash}?d=mp&s=200";
    }

    // Authentication methods
    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function isPasswordExpired(): bool
    {
        return $this->password_expires_at && $this->password_expires_at->isPast();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled && $this->two_factor_confirmed_at;
    }

    public function mustChangePassword(): bool
    {
        return $this->must_change_password || $this->isPasswordExpired();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Skip checks if user not fully loaded
        if (!$this->id) {
            return false;
        }

        if (!$this->isActive()) {
            return false;
        }

        if ($this->isLocked()) {
            return false;
        }

        $panelId = $panel->getId();

        // Check panel-specific access
        return match ($panelId) {
            // Tenant panel (clinic management) - only users WITH a tenant_id
            'tenant', 'admin' => $this->tenant_id !== null,
            // Patient portal
            'portal' => $this->hasPortalAccess(),
            // Super admin panel - only users WITHOUT a tenant_id who are super_admin
            'super-admin' => $this->tenant_id === null && $this->hasRole('super_admin'),
            default => true,
        };
    }

    // Account management methods
    public function lockAccount(int $minutes = null): void
    {
        $this->update([
            'locked_until' => now()->addMinutes($minutes ?? config('auth.lockout_minutes', 30)),
        ]);
    }

    public function unlockAccount(): void
    {
        $this->update([
            'locked_until' => null,
            'failed_login_attempts' => 0,
        ]);
    }

    public function incrementFailedLoginAttempts(): void
    {
        $attempts = $this->failed_login_attempts + 1;
        $maxAttempts = config('auth.max_login_attempts', 5);

        $this->update(['failed_login_attempts' => $attempts]);

        if ($attempts >= $maxAttempts) {
            $this->lockAccount();
        }
    }

    public function resetFailedLoginAttempts(): void
    {
        $this->update(['failed_login_attempts' => 0]);
    }

    public function recordLogin(?string $ip = null): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip ?? request()->ip(),
        ]);

        $this->resetFailedLoginAttempts();

        // Log login history
        $this->loginHistory()->create([
            'ip_address' => $ip ?? request()->ip(),
            'user_agent' => request()->userAgent(),
            'logged_in_at' => now(),
        ]);
    }

    // Settings and preferences
    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->update(['settings' => $settings]);
    }

    public function getPreference(string $key, $default = null)
    {
        return data_get($this->preferences, $key, $default);
    }

    public function setPreference(string $key, $value): void
    {
        $preferences = $this->preferences ?? [];
        data_set($preferences, $key, $value);
        $this->update(['preferences' => $preferences]);
    }

    // Username generation
    protected function generateUsername(): string
    {
        $base = strtolower($this->first_name . $this->last_name);
        $base = preg_replace('/[^a-z0-9]/', '', $base);

        $username = $base;
        $counter = 1;

        while (static::where('username', $username)->exists()) {
            $username = $base . $counter;
            $counter++;
        }

        return $username;
    }

    // Permission overrides
    public function hasPermissionOverride(string $permission): bool
    {
        return in_array($permission, $this->permissions_override ?? []);
    }

    public function addPermissionOverride(string $permission): void
    {
        $overrides = $this->permissions_override ?? [];
        if (!in_array($permission, $overrides)) {
            $overrides[] = $permission;
            $this->update(['permissions_override' => $overrides]);
        }
    }

    public function removePermissionOverride(string $permission): void
    {
        $overrides = array_filter(
            $this->permissions_override ?? [],
            fn($p) => $p !== $permission
        );
        $this->update(['permissions_override' => array_values($overrides)]);
    }
}

enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';
    case PENDING = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => __('Active'),
            self::INACTIVE => __('Inactive'),
            self::SUSPENDED => __('Suspended'),
            self::PENDING => __('Pending'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'gray',
            self::SUSPENDED => 'warning',
            self::PENDING => 'info',
        };
    }
}

enum Gender: string
{
    case MALE = 'male';
    case FEMALE = 'female';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MALE => __('Male'),
            self::FEMALE => __('Female'),
            self::OTHER => __('Other'),
        };
    }
}