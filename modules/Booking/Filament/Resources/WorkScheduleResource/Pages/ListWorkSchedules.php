<?php

namespace Modules\Booking\Filament\Resources\WorkScheduleResource\Pages;

use Modules\Booking\Filament\Resources\WorkScheduleResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;

class ListWorkSchedules extends BaseListRecords
{
    protected static string $resource = WorkScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            ...parent::getHeaderActions(),
        ];
    }
}
