<?php

namespace Modules\Core\Filament\Resources\BranchResource\Widgets;

use Modules\Core\Models\Branch;
use Filament\Widgets\Widget;

class BranchLimitWidget extends Widget
{
    protected static string $view = 'core::filament.widgets.branch-limit-widget';

    protected int | string | array $columnSpan = 'full';

    public int $currentCount = 0;
    public int $maxBranches = 0;
    public bool $isAtLimit = false;
    public bool $isNearLimit = false;

    public function mount(): void
    {
        $tenant = app('currentTenant');

        if ($tenant) {
            $this->currentCount = Branch::count();

            // Get effective limit from plan + extra purchased
            $planLimit = $tenant->plan?->max_branches ?? 0;
            $extraBranches = $tenant->extra_branches ?? 0;
            $this->maxBranches = $planLimit + $extraBranches;

            // If no limit set, treat as unlimited
            if ($this->maxBranches <= 0) {
                $this->maxBranches = PHP_INT_MAX;
            }

            $this->isAtLimit = $this->currentCount >= $this->maxBranches;
            $this->isNearLimit = !$this->isAtLimit && ($this->maxBranches - $this->currentCount) <= 1;
        }
    }

    /**
     * Only show widget if there's a limit set.
     */
    public static function canView(): bool
    {
        $tenant = app('currentTenant');

        if (!$tenant) {
            return false;
        }

        // Get effective limit from plan + extra purchased
        $planLimit = $tenant->plan?->max_branches ?? 0;
        $extraBranches = $tenant->extra_branches ?? 0;
        $total = $planLimit + $extraBranches;

        // Only show if there's a reasonable limit (not unlimited)
        return $total > 0 && $total < 999;
    }
}
