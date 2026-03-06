<?php

namespace Modules\Inventory\Filament\Resources\UomResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Inventory\Filament\Resources\UomResource;
use Modules\Inventory\Models\Uom;

class CreateUom extends CreateRecord
{
    protected static string $resource = UomResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // If this is the reference unit, set ratio to 1
        if (($data['uom_type'] ?? '') === Uom::TYPE_REFERENCE || ($data['is_reference'] ?? false)) {
            $data['ratio'] = 1;
            $data['is_reference'] = true;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
