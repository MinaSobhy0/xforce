<?php

namespace Modules\Booking\Filament\Resources\PractitionerScheduleResource\Pages;

use Modules\Booking\Filament\Resources\PractitionerScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPractitionerSchedule extends EditRecord
{
    protected static string $resource = PractitionerScheduleResource::class;

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
