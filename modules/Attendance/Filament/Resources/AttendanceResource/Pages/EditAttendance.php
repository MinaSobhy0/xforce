<?php

namespace Modules\Attendance\Filament\Resources\AttendanceResource\Pages;

use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Actions;
use Modules\Attendance\Filament\Resources\AttendanceResource;

class EditAttendance extends BaseEditRecord
{
    protected static string $resource = AttendanceResource::class;

    protected function getEditHeaderActions(): array
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
