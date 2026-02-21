<?php

namespace App\Services;

use Modules\Core\Models\Branch;
use Illuminate\Support\Collection;

class BranchContext
{
    /**
     * Get the current selected branches from session.
     */
    public static function current(): Collection
    {
        $branchIds = static::currentIds();

        if (empty($branchIds)) {
            return collect();
        }

        try {
            return Branch::whereIn('id', $branchIds)->get();
        } catch (\Exception $e) {
            return collect();
        }
    }

    /**
     * Get the first/primary selected branch.
     */
    public static function first(): ?Branch
    {
        return static::current()->first();
    }

    /**
     * Get the current branch IDs as array.
     */
    public static function currentIds(): array
    {
        $ids = session('current_branch_ids', []);
        return is_array($ids) ? $ids : [];
    }

    /**
     * Get the current branch ID (first one if multiple).
     * For backward compatibility.
     */
    public static function currentId(): ?string
    {
        $ids = static::currentIds();
        return $ids[0] ?? null;
    }

    /**
     * Set the current branch(es).
     */
    public static function set(array|string|Branch $branches): void
    {
        if ($branches instanceof Branch) {
            $branchIds = [$branches->id];
        } elseif (is_string($branches)) {
            $branchIds = [$branches];
        } else {
            $branchIds = array_map(
                fn($b) => $b instanceof Branch ? $b->id : $b,
                $branches
            );
        }

        session(['current_branch_ids' => array_values(array_filter($branchIds))]);
    }

    /**
     * Add a branch to the current selection.
     */
    public static function add(string|Branch $branch): void
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;
        $current = static::currentIds();

        if (!in_array($branchId, $current)) {
            $current[] = $branchId;
            session(['current_branch_ids' => $current]);
        }
    }

    /**
     * Remove a branch from the current selection.
     */
    public static function remove(string|Branch $branch): void
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;
        $current = static::currentIds();

        $current = array_filter($current, fn($id) => $id !== $branchId);
        session(['current_branch_ids' => array_values($current)]);
    }

    /**
     * Check if a specific branch is currently selected.
     */
    public static function isSelected(string|Branch $branch): bool
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;
        return in_array($branchId, static::currentIds());
    }

    /**
     * Check if all branches mode (no specific selection).
     */
    public static function isAllBranches(): bool
    {
        return empty(static::currentIds());
    }

    /**
     * Check if multiple branches are selected.
     */
    public static function isMultiple(): bool
    {
        return count(static::currentIds()) > 1;
    }

    /**
     * Clear the current branch selection (show all).
     */
    public static function clear(): void
    {
        session()->forget('current_branch_ids');
    }

    /**
     * Get all active branches.
     */
    public static function all(): Collection
    {
        try {
            return Branch::query()
                ->where('is_active', true)
                ->orderBy('is_main', 'desc')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        } catch (\Exception $e) {
            return collect();
        }
    }

    /**
     * Get the user's allowed branch IDs.
     * Returns all branches for super admins, otherwise returns assigned branches.
     */
    public static function userAllowedIds(): array
    {
        $user = auth()->user();

        if (!$user) {
            return [];
        }

        // Super admins can access all branches
        if (static::isSuperAdmin($user)) {
            return static::all()->pluck('id')->toArray();
        }

        // Get branches from user's branch role assignments
        return \Modules\Auth\Models\UserBranchRole::query()
            ->active()
            ->valid()
            ->forUser($user->id)
            ->pluck('branch_id')
            ->unique()
            ->toArray();
    }

    /**
     * Get the user's allowed branches as a collection.
     */
    public static function userAllowed(): Collection
    {
        $ids = static::userAllowedIds();

        if (empty($ids)) {
            return collect();
        }

        try {
            return Branch::whereIn('id', $ids)
                ->where('is_active', true)
                ->orderBy('is_main', 'desc')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        } catch (\Exception $e) {
            return collect();
        }
    }

    /**
     * Get the user's primary branch ID.
     */
    public static function userPrimaryId(): ?string
    {
        $user = auth()->user();

        if (!$user) {
            return null;
        }

        // For super admins, return the main branch
        if (static::isSuperAdmin($user)) {
            $mainBranch = Branch::where('is_main', true)->first();
            return $mainBranch?->id;
        }

        // Get primary branch from UserBranchRole
        $primaryAssignment = \Modules\Auth\Models\UserBranchRole::query()
            ->active()
            ->valid()
            ->primary()
            ->forUser($user->id)
            ->first();

        if ($primaryAssignment) {
            return $primaryAssignment->branch_id;
        }

        // Fall back to first allowed branch
        $allowedIds = static::userAllowedIds();
        return $allowedIds[0] ?? null;
    }

    /**
     * Check if user is a super admin with all-branch access.
     */
    protected static function isSuperAdmin($user): bool
    {
        // Only tenant owner and super admin have automatic all-branch access
        if ($user->hasRole(['super-admin', 'super_admin', 'tenant-owner', 'tenant_owner', 'owner'])) {
            return true;
        }

        // Explicit permission for all-branch access
        if ($user->can('access-all-branches')) {
            return true;
        }

        return false;
    }
}
