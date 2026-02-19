<?php

namespace App\Filament\SuperAdmin\Resources\EmailTemplateResource\Pages;

use App\Filament\SuperAdmin\Resources\EmailTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmailTemplates extends ListRecords
{
    use ListRecords\Concerns\Translatable;

    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\LocaleSwitcher::make(),
            Actions\CreateAction::make()
                ->label('Create Template'),
        ];
    }
}
