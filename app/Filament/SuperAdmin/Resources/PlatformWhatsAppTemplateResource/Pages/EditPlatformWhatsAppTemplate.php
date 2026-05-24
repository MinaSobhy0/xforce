<?php

namespace App\Filament\SuperAdmin\Resources\PlatformWhatsAppTemplateResource\Pages;

use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\SuperAdmin\Resources\PlatformWhatsAppTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditPlatformWhatsAppTemplate extends BaseEditRecord
{
    use Translatable;

    protected static string $resource = PlatformWhatsAppTemplateResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
