<?php

namespace Modules\Marketing\Filament\Resources\MessageTemplateResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Modules\Marketing\Filament\Resources\MessageTemplateResource;

class EditMessageTemplate extends EditRecord
{
    protected static string $resource = MessageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => !$this->record->is_system),
        ];
    }
}
