<?php

namespace Modules\Attendance\Filament\Resources\WorkingScheduleResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Modules\Attendance\Filament\Resources\WorkingScheduleResource;

class EditWorkingSchedule extends EditRecord
{
    protected static string $resource = WorkingScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
