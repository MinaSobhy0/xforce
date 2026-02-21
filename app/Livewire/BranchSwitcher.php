<?php

namespace App\Livewire;

use App\Services\BranchContext;
use Livewire\Component;
use Modules\Auth\Models\UserBranchRole;
use Modules\Core\Models\Branch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class BranchSwitcher extends Component
{
    public array $selectedBranchIds = [];
    public array $allowedBranchIds = [];

    public function mount(): void
    {
        $this->allowedBranchIds = $this->getUserAllowedBranchIds();
        $this->selectedBranchIds = BranchContext::currentIds();

        // If no branch selected yet and user has allowed branches, select the primary/first one
        if (empty($this->selectedBranchIds) && !empty($this->allowedBranchIds)) {
            $primaryBranchId = $this->getUserPrimaryBranchId();
            $this->selectedBranchIds = [$primaryBranchId];
            BranchContext::set($this->selectedBranchIds);
        }
    }

    /**
     * Get branch IDs the current user is allowed to access.
     */
    protected function getUserAllowedBranchIds(): array
    {
        $user = Auth::user();

        if (!$user) {
            return [];
        }

        // Super admins can access all branches
        if ($this->isSuperAdmin($user)) {
            return BranchContext::all()->pluck('id')->toArray();
        }

        // Get branches from user's branch role assignments
        return UserBranchRole::query()
            ->active()
            ->valid()
            ->forUser($user->id)
            ->pluck('branch_id')
            ->unique()
            ->toArray();
    }

    /**
     * Get user's primary branch ID.
     */
    protected function getUserPrimaryBranchId(): ?string
    {
        $user = Auth::user();

        if (!$user) {
            return $this->allowedBranchIds[0] ?? null;
        }

        // For super admins, return the main branch
        if ($this->isSuperAdmin($user)) {
            $mainBranch = Branch::where('is_main', true)->first();
            return $mainBranch?->id ?? ($this->allowedBranchIds[0] ?? null);
        }

        // Get primary branch from UserBranchRole
        $primaryAssignment = UserBranchRole::query()
            ->active()
            ->valid()
            ->primary()
            ->forUser($user->id)
            ->first();

        if ($primaryAssignment) {
            return $primaryAssignment->branch_id;
        }

        // Fall back to first allowed branch
        return $this->allowedBranchIds[0] ?? null;
    }

    /**
     * Check if user is a super admin.
     */
    protected function isSuperAdmin($user): bool
    {
        if ($user->hasRole(['super-admin', 'super_admin', 'tenant-owner', 'tenant_owner', 'owner', 'admin'])) {
            return true;
        }

        if ($user->can('access-all-branches')) {
            return true;
        }

        return false;
    }

    /**
     * Toggle a branch selection (for multi-select mode).
     */
    public function toggleBranch(string $branchId): void
    {
        // Ensure branch is allowed
        if (!in_array($branchId, $this->allowedBranchIds)) {
            return;
        }

        if (in_array($branchId, $this->selectedBranchIds)) {
            // Remove from selection (but keep at least one selected)
            $newSelection = array_values(
                array_filter($this->selectedBranchIds, fn($id) => $id !== $branchId)
            );
            // Don't allow empty selection - keep at least one branch
            if (!empty($newSelection)) {
                $this->selectedBranchIds = $newSelection;
            }
        } else {
            // Add to selection
            $this->selectedBranchIds[] = $branchId;
        }

        BranchContext::set($this->selectedBranchIds);

        $this->dispatch('branch-switched', branchIds: $this->selectedBranchIds);
        $this->refreshPage();
    }

    /**
     * Select a single branch (exclusive).
     */
    public function selectBranch(string $branchId): void
    {
        // Ensure branch is allowed
        if (!in_array($branchId, $this->allowedBranchIds)) {
            return;
        }

        $this->selectedBranchIds = [$branchId];
        BranchContext::set($this->selectedBranchIds);

        $this->dispatch('branch-switched', branchIds: $this->selectedBranchIds);
        $this->refreshPage();
    }

    /**
     * Select all allowed branches.
     */
    public function selectAll(): void
    {
        // Only allow "All Branches" if user has more than one branch
        if (count($this->allowedBranchIds) <= 1) {
            return;
        }

        $this->selectedBranchIds = $this->allowedBranchIds;
        BranchContext::set($this->selectedBranchIds);

        $this->dispatch('branch-switched', branchIds: $this->selectedBranchIds);
        $this->refreshPage();
    }

    /**
     * Refresh the page using JavaScript to avoid SPA navigation loops.
     */
    protected function refreshPage(): void
    {
        $this->js('window.location.reload()');
    }

    /**
     * Get branches the user can access.
     */
    public function getBranches(): Collection
    {
        if (empty($this->allowedBranchIds)) {
            return collect();
        }

        try {
            return Branch::whereIn('id', $this->allowedBranchIds)
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
     * Check if "All Branches" option should be shown.
     */
    public function shouldShowAllBranches(): bool
    {
        return count($this->allowedBranchIds) > 1;
    }

    /**
     * Check if all allowed branches are currently selected.
     */
    public function isAllBranchesSelected(): bool
    {
        if (count($this->allowedBranchIds) <= 1) {
            return false;
        }

        return count(array_diff($this->allowedBranchIds, $this->selectedBranchIds)) === 0;
    }

    public function getSelectedBranches(): Collection
    {
        if (empty($this->selectedBranchIds)) {
            return collect();
        }

        try {
            return Branch::whereIn('id', $this->selectedBranchIds)->get();
        } catch (\Exception $e) {
            return collect();
        }
    }

    public function getDisplayLabel(): string
    {
        $allowedCount = count($this->allowedBranchIds);
        $selectedCount = count($this->selectedBranchIds);

        // If all branches are selected and there's more than one
        if ($allowedCount > 1 && $selectedCount === $allowedCount) {
            return __('All Branches');
        }

        if ($selectedCount === 1) {
            $branch = Branch::find($this->selectedBranchIds[0]);
            return $branch?->name ?? __('Select Branch');
        }

        if ($selectedCount > 1) {
            return __(':count Branches', ['count' => $selectedCount]);
        }

        // Fallback
        return __('Select Branch');
    }

    public function render()
    {
        return view('livewire.branch-switcher', [
            'branches' => $this->getBranches(),
            'displayLabel' => $this->getDisplayLabel(),
            'showAllBranches' => $this->shouldShowAllBranches(),
            'isAllSelected' => $this->isAllBranchesSelected(),
        ]);
    }
}
