<?php

namespace Modules\Booking\Filament\Resources\WorkScheduleResource\Pages;

use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Actions;
use Modules\Booking\Filament\Resources\WorkScheduleResource;

class EditWorkSchedule extends BaseEditRecord
{
    protected static string $resource = WorkScheduleResource::class;

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
