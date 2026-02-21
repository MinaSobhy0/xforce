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
            $this->maxBranches = $tenant->max_branches ?? PHP_INT_MAX;
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

        // Only show if max_branches is a reasonable limit (not unlimited)
        return ($tenant->max_branches ?? PHP_INT_MAX) < 999;
    }
}
