<?php

namespace Modules\OdooIntegration\Filament\Resources\OdooConnectionResource\Pages;

use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Actions;
use Modules\OdooIntegration\Filament\Resources\OdooConnectionResource;

class EditOdooConnection extends BaseEditRecord
{
    protected static string $resource = OdooConnectionResource::class;

    /**
     * BaseEditRecord already prepends Save + Discard (icon buttons) to the
     * header via getSaveDiscardActions(); this method appends our custom
     * actions right after them, so Test Connection sits next to Save.
     */
    protected function getEditHeaderActions(): array
    {
        return [
            Actions\Action::make('test_connection')
                ->tooltip(__('odoo-integration::odoo.actions.test_connection'))
                ->label(__('odoo-integration::odoo.actions.test_connection'))
                ->icon('heroicon-o-signal')
                ->color('info')
                ->iconButton()
                ->action(fn () => OdooConnectionResource::runTestConnection($this->record)),

            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
