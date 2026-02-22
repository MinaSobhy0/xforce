<?php

namespace Modules\Attendance\Filament\Resources\AttendanceRuleResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Modules\Attendance\Filament\Resources\AttendanceRuleResource;

class EditAttendanceRule extends EditRecord
{
    protected static string $resource = AttendanceRuleResource::class;

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
