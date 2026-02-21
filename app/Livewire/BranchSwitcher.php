<?php

namespace App\Livewire;

use App\Services\BranchContext;
use Livewire\Component;
use Modules\Core\Models\Branch;
use Illuminate\Support\Collection;

class BranchSwitcher extends Component
{
    public array $selectedBranchIds = [];

    public function mount(): void
    {
        $this->selectedBranchIds = BranchContext::currentIds();
    }

    /**
     * Toggle a branch selection (for multi-select mode).
     */
    public function toggleBranch(string $branchId): void
    {
        if (in_array($branchId, $this->selectedBranchIds)) {
            // Remove from selection
            $this->selectedBranchIds = array_values(
                array_filter($this->selectedBranchIds, fn($id) => $id !== $branchId)
            );
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
        $this->selectedBranchIds = [$branchId];
        BranchContext::set($this->selectedBranchIds);

        $this->dispatch('branch-switched', branchIds: $this->selectedBranchIds);
        $this->refreshPage();
    }

    /**
     * Select all branches (clear filter).
     */
    public function selectAll(): void
    {
        $this->selectedBranchIds = [];
        BranchContext::clear();

        $this->dispatch('branch-switched', branchIds: []);
        $this->refreshPage();
    }

    /**
     * Refresh the page using JavaScript to avoid SPA navigation loops.
     */
    protected function refreshPage(): void
    {
        $this->js('window.location.reload()');
    }

    public function getBranches(): Collection
    {
        return BranchContext::all();
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
        if (empty($this->selectedBranchIds)) {
            return __('All Branches');
        }

        $count = count($this->selectedBranchIds);

        if ($count === 1) {
            $branch = Branch::find($this->selectedBranchIds[0]);
            return $branch?->name ?? __('Select Branch');
        }

        return __(':count Branches', ['count' => $count]);
    }

    public function render()
    {
        return view('livewire.branch-switcher', [
            'branches' => $this->getBranches(),
            'displayLabel' => $this->getDisplayLabel(),
        ]);
    }
}
