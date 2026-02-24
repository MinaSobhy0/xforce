<?php

namespace Modules\Equipment\Filament\Resources\EquipmentParameterTemplateResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Modules\Equipment\Filament\Resources\EquipmentParameterTemplateResource;

class EditEquipmentParameterTemplate extends EditRecord
{
    protected static string $resource = EquipmentParameterTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn ($record) => !$record->is_system),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
