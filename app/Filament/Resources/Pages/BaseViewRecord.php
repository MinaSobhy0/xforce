<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Resources\Pages\Concerns\HasRefreshAction;
use App\Filament\Traits\HasRecordNavigation;
use Filament\Resources\Pages\ViewRecord;

class BaseViewRecord extends ViewRecord
{
    use HasRecordNavigation;
    use HasRefreshAction;

    /**
     * Whether to show record navigation buttons.
     */
    protected bool $hasRecordNavigation = true;

    /**
     * Disable URL persistence for relation manager tabs to allow smooth switching.
     * When false, relation managers switch without changing the URL (no page reload).
     */
    public bool $persistActiveRelationManagerTabInQueryString = false;

    /**
     * Preload all relation managers instead of lazy-loading.
     * This ensures instant tab switching without loading delays.
     */
    public function getRelationManagersContentIsLazy(): bool
    {
        return false;
    }

    protected function getHeaderActions(): array
    {
        $actions = [$this->getRefreshHeaderAction()];

        $actions = array_merge($actions, $this->getViewHeaderActions());

        if ($this->hasRecordNavigation) {
            $actions = array_merge($actions, $this->getRecordNavigationActions());
        }

        return $actions;
    }

    /**
     * Override this method in child classes to add custom header actions.
     * Navigation actions will be automatically appended.
     */
    protected function getViewHeaderActions(): array
    {
        return parent::getHeaderActions();
    }
}
