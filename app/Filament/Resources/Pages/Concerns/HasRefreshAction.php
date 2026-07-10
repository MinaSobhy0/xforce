<?php

namespace App\Filament\Resources\Pages\Concerns;

use Filament\Actions;

/**
 * Small icon-only "reload data" header action. Applied on Base
 * List / View / Edit pages so every panel page gets a consistent
 * soft-refresh — re-fetches the record and re-renders the Livewire
 * component without a full browser reload (preserves scroll,
 * form state, sidebar position).
 */
trait HasRefreshAction
{
    protected function getRefreshHeaderAction(): Actions\Action
    {
        return Actions\Action::make('refreshData')
            ->tooltip('Reload data')
            ->color('gray')
            ->icon('heroicon-o-arrow-path')
            ->iconButton()
            ->extraAttributes(['class' => 'border border-gray-300 bg-white hover:bg-gray-50'])
            ->keyBindings(['mod+r'])
            ->action(function (): void {
                // Re-fetch the record from DB if this page has one
                // (View / Edit); the mere presence of the action
                // triggers a Livewire re-render which pulls fresh
                // relationship counts on list pages too.
                if (property_exists($this, 'record') && $this->record !== null && method_exists($this->record, 'refresh')) {
                    $this->record->refresh();
                }
            });
    }
}
