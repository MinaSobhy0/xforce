<?php

namespace App\Filament\SuperAdmin\Resources\EmailTemplateResource\Pages;

use App\Filament\SuperAdmin\Resources\EmailTemplateResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;

class ListEmailTemplates extends BaseListRecords
{
    use Translatable;

    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            \Filament\Actions\LocaleSwitcher::make(),
            Actions\CreateAction::make()
                ->label('Create Template'),
        ];
    }
}
