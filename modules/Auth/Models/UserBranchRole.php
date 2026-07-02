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

    /**
     * SECURITY: Only allow safe fields for mass assignment.
     * Role assignments must be validated for authorization before creation.
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'branch_id',
        'expires_at',
    ];

    /**
     * SECURITY: Fields that must never be mass-assigned.
     * These require explicit admin authorization.
     */
    protected $guarded = [
        'id',
        'role_id',       // CRITICAL: Role assignment must be authorized
        'is_primary',    // HIGH: Primary role has elevated access
        'is_active',     // HIGH: Can bypass deactivation
        'assigned_at',   // MEDIUM: Audit trail
        'assigned_by',   // MEDIUM: Audit trail manipulation
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
    public function scopeForBranch($query, ?string $branchId)
    {
        if ($branchId === null) {
            return $query;
        }
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

        // is_primary is guarded against mass assignment — set it explicitly.
        $this->forceFill(['is_primary' => true])->save();

        return $this;
    }

    /**
     * Authorized assignment helper.
     *
     * role_id / is_primary / is_active / assigned_* are guarded against mass
     * assignment to prevent privilege escalation from untrusted input. Trusted,
     * permission-gated admin code creates assignments through this method, which
     * force-fills the attributes after the caller has authorized them.
     */
    public static function assign(array $attributes): self
    {
        $role = new static();
        $role->forceFill($attributes);
        $role->save();

        return $role;
    }
}
