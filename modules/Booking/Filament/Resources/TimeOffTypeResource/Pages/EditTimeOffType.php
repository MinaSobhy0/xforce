<?php

namespace Modules\Booking\Filament\Resources\TimeOffTypeResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Modules\Booking\Filament\Resources\TimeOffTypeResource;

class EditTimeOffType extends EditRecord
{
    protected static string $resource = TimeOffTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
