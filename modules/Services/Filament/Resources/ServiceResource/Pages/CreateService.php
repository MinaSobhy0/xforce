<?php

namespace Modules\Services\Filament\Resources\ServiceResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Services\Filament\Resources\ServiceResource;

class CreateService extends CreateRecord
{
    protected static string $resource = ServiceResource::class;

    protected array $roomIds = [];
    protected array $qualifiedStaffIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Store relationship data for afterCreate
        $this->roomIds = $data['room_ids'] ?? [];
        $this->qualifiedStaffIds = $data['qualified_staff_ids'] ?? [];

        // Remove non-model fields (equipment is handled by CheckboxList with relationship())
        unset($data['room_ids'], $data['qualified_staff_ids']);

        return $data;
    }

    protected function afterCreate(): void
    {
        // Sync rooms relationship
        $this->record->rooms()->sync($this->roomIds ?? []);

        // Sync qualified staff relationship
        $this->record->qualifiedStaff()->sync($this->qualifiedStaffIds ?? []);
    }
}
