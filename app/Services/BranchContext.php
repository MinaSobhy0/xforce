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
}
