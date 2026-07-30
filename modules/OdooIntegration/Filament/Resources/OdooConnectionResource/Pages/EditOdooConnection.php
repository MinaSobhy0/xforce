<?php

namespace Modules\OdooIntegration\Filament\Resources\OdooConnectionResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Modules\OdooIntegration\Filament\Resources\OdooConnectionResource;

class EditOdooConnection extends EditRecord
{
    protected static string $resource = OdooConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makeTestConnectionAction(),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Override so the Test Connection button sits alongside Save + Cancel
     * in the form footer where it's naturally discoverable while editing.
     */
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            $this->makeTestConnectionAction(),
            $this->getCancelFormAction(),
        ];
    }

    /**
     * Test against the saved record for editable rows — safer than testing
     * unsaved form state because password fields on the form may be blank
     * (they're masked and don't ship the current value back on rehydration).
     */
    protected function makeTestConnectionAction(): Actions\Action
    {
        return Actions\Action::make('test_connection')
            ->label(__('odoo-integration::odoo.actions.test_connection'))
            ->icon('heroicon-o-signal')
            ->color('info')
            ->action(fn () => OdooConnectionResource::runTestConnection($this->record));
    }
}
