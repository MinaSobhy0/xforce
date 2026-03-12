<?php

namespace Modules\Booking\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Spatie\Translatable\HasTranslations;
use XLinic\Framework\Core\Model\BaseModel;

class TimeOffType extends BaseModel
{
    use HasTranslations;

    protected $table = 'time_off_types';

    public array $translatable = ['name', 'description'];

    // Approval type constants
    public const APPROVAL_TYPE_ANY = 'any';
    public const APPROVAL_TYPE_ROLES = 'roles';
    public const APPROVAL_TYPE_USERS = 'users';
    public const APPROVAL_TYPE_ROLES_OR_USERS = 'roles_or_users';

    public const APPROVAL_TYPES = [
        self::APPROVAL_TYPE_ANY => 'Any Manager',
        self::APPROVAL_TYPE_ROLES => 'Specific Roles',
        self::APPROVAL_TYPE_USERS => 'Specific Users',
        self::APPROVAL_TYPE_ROLES_OR_USERS => 'Specific Roles or Users',
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'color',
        'is_paid',
        'requires_approval',
        'approval_type',
        'approval_role_ids',
        'approval_user_ids',
        'default_days_per_year',
        'max_days_per_request',
        'min_days_notice',
        'allow_half_day',
        'allow_partial_day',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'requires_approval' => 'boolean',
        'approval_role_ids' => 'array',
        'approval_user_ids' => 'array',
        'default_days_per_year' => 'integer',
        'max_days_per_request' => 'integer',
        'min_days_notice' => 'integer',
        'allow_half_day' => 'boolean',
        'allow_partial_day' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'color' => 'gray',
        'is_paid' => true,
        'requires_approval' => true,
        'approval_type' => self::APPROVAL_TYPE_ANY,
        'default_days_per_year' => 0,
        'min_days_notice' => 0,
        'allow_half_day' => true,
        'allow_partial_day' => false,
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected $appends = ['translated_name'];

    /**
     * Get the translated name for the current locale.
     */
    public function getTranslatedNameAttribute(): string
    {
        return $this->getTranslation('name', app()->getLocale())
            ?? $this->getTranslation('name', 'en')
            ?? '';
    }

    public const COLORS = [
        'gray' => 'Gray',
        'primary' => 'Primary',
        'success' => 'Green',
        'info' => 'Blue',
        'warning' => 'Yellow',
        'danger' => 'Red',
    ];

    /**
     * Get allocations for this type.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(TimeOffAllocation::class);
    }

    /**
     * Get time off requests for this type.
     */
    public function timeOffRequests(): HasMany
    {
        return $this->hasMany(PractitionerTimeOff::class);
    }

    /**
     * Scope to active types only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to ordered types.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Check if a user can approve time off requests of this type.
     */
    public function canBeApprovedBy(User $user): bool
    {
        if (!$this->requires_approval) {
            return true;
        }

        switch ($this->approval_type) {
            case self::APPROVAL_TYPE_ANY:
                // Any user with approval permission can approve
                return $user->can('approve-time-off') || $user->hasRole(['super-admin', 'admin', 'manager']);

            case self::APPROVAL_TYPE_ROLES:
                // Only users with specific roles can approve
                $roleIds = $this->approval_role_ids ?? [];
                if (empty($roleIds)) {
                    return false;
                }
                return $user->roles()->whereIn('id', $roleIds)->exists();

            case self::APPROVAL_TYPE_USERS:
                // Only specific users can approve
                $userIds = $this->approval_user_ids ?? [];
                return in_array($user->id, $userIds);

            case self::APPROVAL_TYPE_ROLES_OR_USERS:
                // Either specific roles or specific users can approve
                $roleIds = $this->approval_role_ids ?? [];
                $userIds = $this->approval_user_ids ?? [];

                if (in_array($user->id, $userIds)) {
                    return true;
                }

                if (!empty($roleIds) && $user->roles()->whereIn('id', $roleIds)->exists()) {
                    return true;
                }

                return false;

            default:
                return false;
        }
    }

    /**
     * Get the approval roles.
     */
    public function getApprovalRoles()
    {
        $roleIds = $this->approval_role_ids ?? [];
        if (empty($roleIds)) {
            return collect();
        }

        return Role::whereIn('id', $roleIds)->get();
    }

    /**
     * Get the approval users.
     */
    public function getApprovalUsers()
    {
        $userIds = $this->approval_user_ids ?? [];
        if (empty($userIds)) {
            return collect();
        }

        return User::whereIn('id', $userIds)->get();
    }
}
