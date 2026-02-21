<?php

namespace Modules\Booking\Filament\Resources\PractitionerScheduleResource\Pages;

use Modules\Booking\Filament\Resources\PractitionerScheduleResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditPractitionerSchedule extends BaseEditRecord
{
    protected static string $resource = PractitionerScheduleResource::class;

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
