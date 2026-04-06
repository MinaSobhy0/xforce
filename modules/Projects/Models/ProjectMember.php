<?php

namespace Modules\Projects\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\User;

class ProjectMember extends Model
{
    protected $table = 'project_members';

    protected $fillable = [
        'project_id',
        'user_id',
        'role',
        'hourly_rate_minor',
    ];

    protected $casts = [
        'hourly_rate_minor' => 'integer',
    ];

    // Role constants
    public const ROLE_MANAGER = 'manager';
    public const ROLE_MEMBER = 'member';
    public const ROLE_VIEWER = 'viewer';

    public const ROLES = [
        self::ROLE_MANAGER => 'Manager',
        self::ROLE_MEMBER => 'Member',
        self::ROLE_VIEWER => 'Viewer',
    ];

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Computed attributes
    public function getRoleLabelAttribute(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    /**
     * Get hourly rate in major currency units.
     */
    public function getHourlyRateAttribute(): float
    {
        return ($this->hourly_rate_minor ?? 0) / 100;
    }

    /**
     * Set hourly rate from major currency units.
     */
    public function setHourlyRateAttribute(float $value): void
    {
        $this->hourly_rate_minor = (int) round($value * 100);
    }

    // Permission checks
    public function canManage(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function canEdit(): bool
    {
        return in_array($this->role, [self::ROLE_MANAGER, self::ROLE_MEMBER]);
    }

    public function canView(): bool
    {
        return true;
    }

    // Scopes
    public function scopeManagers($query)
    {
        return $query->where('role', self::ROLE_MANAGER);
    }

    public function scopeMembers($query)
    {
        return $query->where('role', self::ROLE_MEMBER);
    }

    public function scopeViewers($query)
    {
        return $query->where('role', self::ROLE_VIEWER);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }
}
