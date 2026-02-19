<?php

namespace App\Filament\SuperAdmin\Resources\EmailTemplateResource\Pages;

use App\Filament\SuperAdmin\Resources\EmailTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmailTemplate extends CreateRecord
{
    use CreateRecord\Concerns\Translatable;

    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\LocaleSwitcher::make(),
        ];
    }
}
