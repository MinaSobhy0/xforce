<?php

namespace App\Filament\SuperAdmin\Resources\HelpArticleResource\Pages;

use App\Filament\SuperAdmin\Resources\HelpArticleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHelpArticle extends CreateRecord
{
    protected static string $resource = HelpArticleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
