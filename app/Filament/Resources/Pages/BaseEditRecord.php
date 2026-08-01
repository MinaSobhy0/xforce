<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Traits\HasRecordNavigation;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class BaseEditRecord extends EditRecord
{
    use HasRecordNavigation;

    /**
     * Whether to show record navigation buttons.
     */
    protected bool $hasRecordNavigation = true;

    /**
     * Whether to show save/discard buttons in header.
     */
    protected bool $hasHeaderSaveActions = true;

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
        $actions = [];

        // Add Save and Discard buttons first
        if ($this->hasHeaderSaveActions) {
            $actions = array_merge($actions, $this->getSaveDiscardActions());
        }

        // Add custom header actions
        $actions = array_merge($actions, $this->getEditHeaderActions());

        // Add record navigation actions
        if ($this->hasRecordNavigation) {
            $actions = array_merge($actions, $this->getRecordNavigationActions());
        }

        return $actions;
    }

    /**
     * Get the Save and Discard header actions.
     */
    protected function getSaveDiscardActions(): array
    {
        return [
            Actions\Action::make('headerSave')
                ->tooltip(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                ->color('success')
                ->icon('heroicon-s-check')
                ->iconButton()
                ->extraAttributes([
                    'class' => 'border border-success-500 bg-white hover:bg-success-50',
                    'wire:click' => 'save',
                ])
                ->keyBindings(['mod+s']),

            Actions\Action::make('headerDiscard')
                ->tooltip(__('core::core.discard'))
                ->color('danger')
                ->icon('heroicon-s-x-mark')
                ->iconButton()
                ->extraAttributes([
                    'class' => 'border border-danger-500 bg-white hover:bg-danger-50',
                ])
                ->url($this->getResource()::getUrl('index'))
                ->keyBindings(['escape']),
        ];
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
