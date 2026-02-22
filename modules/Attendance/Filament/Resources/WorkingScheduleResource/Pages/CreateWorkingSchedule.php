<?php

namespace Modules\Attendance\Filament\Resources\WorkingScheduleResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Attendance\Filament\Resources\WorkingScheduleResource;

class CreateWorkingSchedule extends CreateRecord
{
    protected static string $resource = WorkingScheduleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant_id;
        $data['created_by'] = auth()->id();

        return $data;
    }
}
