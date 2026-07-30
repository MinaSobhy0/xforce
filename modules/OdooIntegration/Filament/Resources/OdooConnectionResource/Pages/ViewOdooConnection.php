<?php

namespace Modules\OdooIntegration\Filament\Resources\OdooConnectionResource\Pages;

use App\Filament\Resources\Pages\BaseViewRecord;
use Filament\Actions;
use Modules\OdooIntegration\Filament\Resources\OdooConnectionResource;

class ViewOdooConnection extends BaseViewRecord
{
    protected static string $resource = OdooConnectionResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\Action::make('test_connection')
                ->tooltip(__('odoo-integration::odoo.actions.test_connection'))
                ->label(__('odoo-integration::odoo.actions.test_connection'))
                ->icon('heroicon-o-signal')
                ->color('info')
                ->iconButton()
                ->action(fn () => OdooConnectionResource::runTestConnection($this->record)),

            Actions\EditAction::make(),
        ];
    }
}
