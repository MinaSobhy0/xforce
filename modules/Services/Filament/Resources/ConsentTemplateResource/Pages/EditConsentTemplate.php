<?php

namespace Modules\Services\Filament\Resources\ConsentTemplateResource\Pages;

use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Actions;
use Modules\Services\Filament\Resources\ConsentTemplateResource;

class EditConsentTemplate extends BaseEditRecord
{
    protected static string $resource = ConsentTemplateResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
