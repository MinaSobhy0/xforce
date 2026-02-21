<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Traits\HasRecordNavigation;
use Filament\Resources\Pages\EditRecord;

class BaseEditRecord extends EditRecord
{
    use HasRecordNavigation;

    /**
     * Whether to show record navigation buttons.
     */
    protected bool $hasRecordNavigation = true;

    protected function getHeaderActions(): array
    {
        $actions = $this->getEditHeaderActions();

        if ($this->hasRecordNavigation) {
            $actions = array_merge($actions, $this->getRecordNavigationActions());
        }

        return $actions;
    }

    /**
     * Override this method in child classes to add custom header actions.
     * Navigation actions will be automatically appended.
     */
    protected function getEditHeaderActions(): array
    {
        return parent::getHeaderActions();
    }
}
