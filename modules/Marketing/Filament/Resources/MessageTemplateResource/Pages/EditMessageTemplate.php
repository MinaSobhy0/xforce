<?php

namespace Modules\Marketing\Filament\Resources\MessageTemplateResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Modules\Marketing\Filament\Resources\MessageTemplateResource;

class EditMessageTemplate extends BaseEditRecord
{
    protected static string $resource = MessageTemplateResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => !$this->record->is_system),
        ];
    }
}
