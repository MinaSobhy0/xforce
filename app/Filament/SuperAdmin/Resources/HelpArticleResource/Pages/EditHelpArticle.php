<?php

namespace App\Filament\SuperAdmin\Resources\HelpArticleResource\Pages;

use App\Filament\SuperAdmin\Resources\HelpArticleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHelpArticle extends EditRecord
{
    protected static string $resource = HelpArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
