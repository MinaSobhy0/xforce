<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

/**
 * OwnerUser model for the Owner Portal at sys.x-linic.com/admin
 * Uses the central database connection (public schema)
 */
class OwnerUser extends Model implements
    AuthenticatableContract,
    AuthorizableContract,
    CanResetPasswordContract,
    FilamentUser
{
    use Authenticatable,
        Authorizable,
        CanResetPassword,
        MustVerifyEmail,
        Notifiable,
        SoftDeletes;

    /**
     * Use central connection (public schema)
     */
    protected $connection = 'central';

    protected $table = 'users';

    /**
     * SECURITY: Only allow safe fields for mass assignment.
     * Tenant and status changes must use dedicated admin methods.
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
    ];

    /**
     * SECURITY: Fields that must never be mass-assigned.
     */
    protected $guarded = [
        'id',
        'tenant_id',  // CRITICAL: Would allow tenant switching
        'status',     // HIGH: Would allow reactivating suspended owners
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getNameAttribute(): string
    {
        return $this->full_name;
    }

    public function isActive(): bool
    {
        return ($this->status ?? 'active') === 'active';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Owner portal requires tenant_id (they own a clinic)
        if ($panel->getId() === 'admin') {
            return $this->tenant_id !== null && $this->isActive();
        }

        return false;
    }

    /**
     * Get the tenant this owner belongs to
     */
    public function tenant()
    {
        return $this->belongsTo(\Modules\Core\Models\Tenant::class);
    }
}
