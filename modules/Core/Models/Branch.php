<?php

namespace Modules\Core\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends BaseModel
{
    use HasTenancy;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'address',
        'city',
        'phone',
        'email',
        'google_maps_url',
        'working_hours',
        'timezone',
        'currency_code',
        'is_active',
        'is_main',
        'sort_order',
    ];

    protected $casts = [
        'working_hours' => 'array',
        'is_active' => 'boolean',
        'is_main' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the tenant this branch belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the rooms in this branch.
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Get the users assigned to this branch through UserBranchRole.
     */
    public function users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            \Modules\Auth\Models\User::class,
            'user_branch_roles',
            'branch_id',
            'user_id'
        )->withPivot(['role_id', 'is_primary', 'is_active'])
         ->wherePivot('is_active', true);
    }

    /**
     * Get all branch role assignments.
     */
    public function branchRoles(): HasMany
    {
        return $this->hasMany(\Modules\Auth\Models\UserBranchRole::class);
    }

    /**
     * Get active rooms count.
     */
    public function getActiveRoomsCountAttribute(): int
    {
        return $this->rooms()->where('is_active', true)->count();
    }

    /**
     * Get the formatted working hours for a specific day.
     */
    public function getWorkingHoursForDay(string $day): ?array
    {
        if (!$this->working_hours) {
            return null;
        }

        foreach ($this->working_hours as $hours) {
            if (($hours['day'] ?? '') === $day) {
                return $hours;
            }
        }

        return null;
    }

    /**
     * Check if the branch is open on a specific day.
     */
    public function isOpenOnDay(string $day): bool
    {
        $hours = $this->getWorkingHoursForDay($day);

        if (!$hours) {
            return true; // Default to open if no hours defined
        }

        return !($hours['is_closed'] ?? false);
    }

    /**
     * Scope: Active branches only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Main branch only.
     */
    public function scopeMain($query)
    {
        return $query->where('is_main', true);
    }

    /**
     * Scope: Order by sort order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
