<?php

namespace App\Filament\SuperAdmin\Resources\PlatformWhatsAppTemplateResource\Pages;

use App\Filament\Resources\Pages\BaseListRecords;
use App\Filament\SuperAdmin\Resources\PlatformWhatsAppTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;

class ListPlatformWhatsAppTemplates extends BaseListRecords
{
    use Translatable;

    protected static string $resource = PlatformWhatsAppTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\CreateAction::make(),
        ];
    }
}
