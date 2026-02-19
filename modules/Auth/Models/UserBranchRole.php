<?php

namespace Modules\Auth\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UserBranchRole - Branch-scoped role assignments
 *
 * Allows users to have different roles in different branches.
 */
class UserBranchRole extends BaseModel
{
    use HasTenancy;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'branch_id',
        'role_id',
        'is_primary',
        'is_active',
        'assigned_at',
        'assigned_by',
        'expires_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'assigned_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user this assignment belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the branch this assignment is for.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(\Modules\Core\Models\Branch::class);
    }

    /**
     * Get the role assigned.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the user who made this assignment.
     */
    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Get the tenant this assignment belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\Modules\Core\Models\Tenant::class);
    }

    /**
     * Scope to active assignments.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to primary assignments.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope to valid (not expired) assignments.
     */
    public function scopeValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope to assignments for a specific user.
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to assignments for a specific branch.
     */
    public function scopeForBranch($query, string $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Check if this assignment is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if this assignment is valid (active and not expired).
     */
    public function isValid(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    /**
     * Get the role name with branch context.
     */
    public function getDisplayName(): string
    {
        $roleName = $this->role?->name ?? 'Unknown Role';
        $branchName = $this->branch?->name ?? 'Unknown Branch';
        return "{$roleName} @ {$branchName}";
    }

    /**
     * Make this the primary assignment for the user.
     */
    public function makePrimary(): self
    {
        // Remove primary flag from other assignments
        static::query()
            ->where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        $this->update(['is_primary' => true]);

        return $this;
    }
}
