<?php

namespace Modules\Services\Filament\Resources\ParameterTemplateResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Modules\Services\Filament\Resources\ParameterTemplateResource;

class EditParameterTemplate extends EditRecord
{
    protected static string $resource = ParameterTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn () => !$this->record->is_system),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
