<?php

namespace Modules\Services\Filament\Resources\ServiceResource\Pages;

use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Actions;
use Modules\Services\Filament\Resources\ServiceResource;

class EditService extends BaseEditRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Store relationship data for afterSave
        $this->roomIds = $data['room_ids'] ?? [];
        $this->qualifiedStaffIds = $data['qualified_staff_ids'] ?? [];

        // Remove non-model fields (equipment is handled by CheckboxList with relationship())
        unset($data['room_ids'], $data['qualified_staff_ids']);

        return $data;
    }

    protected function afterSave(): void
    {
        // Sync rooms relationship
        $this->record->rooms()->sync($this->roomIds ?? []);

        // Sync qualified staff relationship
        $this->record->qualifiedStaff()->sync($this->qualifiedStaffIds ?? []);
    }

    protected array $roomIds = [];
    protected array $qualifiedStaffIds = [];
}
