<?php

namespace Modules\Auth\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasPortalAccess;
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
        Notifiable,
        SoftDeletes,
        \App\Traits\TwoFactorAuthenticatable,
        \App\Traits\HasPostgresBoolean;

    /**
     * Boolean fields that need PostgreSQL-specific handling.
     */
    protected function getPostgresBooleanFields(): array
    {
        return ['must_change_password', 'two_factor_enabled'];
    }

    protected $fillable = [
        'tenant_id',
        'first_name',
        'last_name',
        'email',
        'username',
        'phone',
        'password',
        'avatar_url',
        'status',
        'language',
        'timezone',
        'email_verified_at',
        'phone_verified_at',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'last_login_at',
        'last_login_ip',
        'password_expires_at',
        'must_change_password',
        'failed_login_attempts',
        'locked_until',
        'employee_id',
        'department',
        'job_title',
        'hire_date',
        'birth_date',
        'gender',
        'address',
        'emergency_contact',
        'salary',
        'commission_rate',
        'work_schedule',
        'permissions_override',
        'settings',
        'preferences',
        'meta',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
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
        if (!$this->isActive()) {
            return false;
        }

        if ($this->isLocked()) {
            return false;
        }

        // Check panel-specific access
        return match ($panel->getId()) {
            // Admin panel (tenant portal) - only users WITH a tenant_id
            'admin' => $this->tenant_id !== null && $this->hasAnyRole(['admin', 'manager', 'staff', 'doctor', 'nurse', 'technician', 'receptionist', 'owner']),
            // Patient portal
            'portal' => $this->hasPortalAccess(),
            // Super admin panel - only users WITHOUT a tenant_id who are super_admin
            'super-admin' => $this->tenant_id === null && $this->hasRole('super_admin'),
            default => false,
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