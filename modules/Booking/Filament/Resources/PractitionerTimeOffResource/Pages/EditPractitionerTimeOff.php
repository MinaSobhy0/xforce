<?php

namespace Modules\Booking\Filament\Resources\PractitionerTimeOffResource\Pages;

use Modules\Booking\Filament\Resources\PractitionerTimeOffResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditPractitionerTimeOff extends BaseEditRecord
{
    protected static string $resource = PractitionerTimeOffResource::class;

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
