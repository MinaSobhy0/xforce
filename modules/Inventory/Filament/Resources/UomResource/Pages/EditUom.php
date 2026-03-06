<?php

namespace Modules\Inventory\Filament\Resources\UomResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Modules\Inventory\Filament\Resources\UomResource;
use Modules\Inventory\Models\Uom;

class EditUom extends BaseEditRecord
{
    protected static string $resource = UomResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // If this is the reference unit, set ratio to 1
        if (($data['uom_type'] ?? '') === Uom::TYPE_REFERENCE || ($data['is_reference'] ?? false)) {
            $data['ratio'] = 1;
            $data['is_reference'] = true;
        }

        return $data;
    }

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->products()->count() === 0 && $this->record->productsPurchaseUom()->count() === 0),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
