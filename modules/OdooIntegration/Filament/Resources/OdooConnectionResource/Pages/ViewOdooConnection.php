<?php

namespace Modules\OdooIntegration\Filament\Resources\OdooConnectionResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Modules\OdooIntegration\Filament\Resources\OdooConnectionResource;

class ViewOdooConnection extends ViewRecord
{
    protected static string $resource = OdooConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('test_connection')
                ->label(__('odoo-integration::odoo.actions.test_connection'))
                ->icon('heroicon-o-signal')
                ->color('info')
                ->action(fn () => OdooConnectionResource::runTestConnection($this->record)),
            Actions\EditAction::make(),
        ];
    }
}
