<?php

namespace App\Filament\SuperAdmin\Resources\PlatformWhatsAppTemplateResource\Pages;

use App\Filament\SuperAdmin\Resources\PlatformWhatsAppTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreatePlatformWhatsAppTemplate extends CreateRecord
{
    use Translatable;

    protected static string $resource = PlatformWhatsAppTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }
}
