<?php

namespace Modules\Booking\Filament\Resources\TimeOffTypeResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Modules\Booking\Filament\Resources\TimeOffTypeResource;

class EditTimeOffType extends BaseEditRecord
{
    protected static string $resource = TimeOffTypeResource::class;

    protected function getEditHeaderActions(): array
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
