<?php

use App\Services\BranchContext;
use Illuminate\Support\Collection;
use Modules\Core\Models\Branch;

if (!function_exists('current_branches')) {
    /**
     * Get the current selected branches.
     */
    function current_branches(): Collection
    {
        return BranchContext::current();
    }
}

if (!function_exists('current_branch')) {
    /**
     * Get the first/primary current branch.
     */
    function current_branch(): ?Branch
    {
        return BranchContext::first();
    }
}

if (!function_exists('current_branch_ids')) {
    /**
     * Get the current branch IDs as array.
     */
    function current_branch_ids(): array
    {
        return BranchContext::currentIds();
    }
}

if (!function_exists('current_branch_id')) {
    /**
     * Get the first current branch ID.
     */
    function current_branch_id(): ?string
    {
        return BranchContext::currentId();
    }
}

if (!function_exists('is_all_branches')) {
    /**
     * Check if viewing all branches.
     */
    function is_all_branches(): bool
    {
        return BranchContext::isAllBranches();
    }
}
