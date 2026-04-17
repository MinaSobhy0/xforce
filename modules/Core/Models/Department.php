<?php

namespace Modules\Core\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends BaseModel
{
    use HasTenancy;
    use HasActivity;
    use SoftDeletes;

    protected $table = 'departments';

    // Disable auto branch assignment
    protected bool $autoSetBranchId = false;

    protected $fillable = [
        'tenant_id',
        'odoo_id',
        'name',
        'code',
        'description',
        'parent_id',
        'manager_id',
        'branch_id',
        'is_active',
        'sort_order',
        'odoo_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'odoo_synced_at' => 'datetime',
    ];

    // ========================================
    // Relationships
    // ========================================

    /**
     * Get the parent department.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get the child departments.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Get all descendants (recursive).
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get the department manager.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(\Modules\Staff\Models\StaffProfile::class, 'manager_id');
    }

    /**
     * Get the branch this department belongs to.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get staff members in this department.
     */
    public function staff(): HasMany
    {
        return $this->hasMany(\Modules\Staff\Models\StaffProfile::class, 'department_id');
    }

    // ========================================
    // Accessors
    // ========================================

    /**
     * Get the full hierarchical path.
     */
    public function getFullPathAttribute(): string
    {
        $path = [$this->display_name];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($path, $parent->display_name);
            $parent = $parent->parent;
        }

        return implode(' / ', $path);
    }

    /**
     * Get the display name (translated or default).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name;
    }

    /**
     * Get the hierarchy level (0 = root).
     */
    public function getLevelAttribute(): int
    {
        $level = 0;
        $parent = $this->parent;

        while ($parent) {
            $level++;
            $parent = $parent->parent;
        }

        return $level;
    }

    // ========================================
    // Methods
    // ========================================

    /**
     * Check if this is a root department.
     */
    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Check if department has children.
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Check if department has staff.
     */
    public function hasStaff(): bool
    {
        return $this->staff()->exists();
    }

    /**
     * Get all ancestor IDs.
     */
    public function getAncestorIds(): array
    {
        $ids = [];
        $parent = $this->parent;

        while ($parent) {
            $ids[] = $parent->id;
            $parent = $parent->parent;
        }

        return $ids;
    }

    /**
     * Get all descendant IDs (flat).
     */
    public function getDescendantIds(): array
    {
        $ids = [];

        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getDescendantIds());
        }

        return $ids;
    }

    /**
     * Get a hierarchical list for select dropdowns.
     */
    public static function getHierarchicalList(?int $excludeId = null): array
    {
        $departments = self::query()
            ->active()
            ->root()
            ->ordered()
            ->with('descendants')
            ->get();

        $list = [];
        self::flattenHierarchy($departments, $list, 0, $excludeId);

        return $list;
    }

    /**
     * Flatten hierarchy for select list.
     */
    protected static function flattenHierarchy($departments, array &$list, int $level, ?int $excludeId): void
    {
        foreach ($departments as $department) {
            if ($excludeId && $department->id === $excludeId) {
                continue;
            }

            $prefix = str_repeat('— ', $level);
            $list[$department->id] = $prefix . $department->display_name;

            if ($department->children->isNotEmpty()) {
                self::flattenHierarchy($department->children, $list, $level + 1, $excludeId);
            }
        }
    }

    // ========================================
    // Scopes
    // ========================================

    /**
     * Scope: Active departments only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Root departments only (no parent).
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope: Filter by branch.
     */
    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope: Order by sort order and name.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
