<?php

namespace App\Filament\SuperAdmin\Resources\AnnouncementResource\Pages;

use App\Filament\SuperAdmin\Resources\AnnouncementResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;

class ListAnnouncements extends BaseListRecords
{
    use Translatable;

    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            \Filament\Actions\LocaleSwitcher::make(),
            Actions\CreateAction::make()
                ->label('New Announcement'),
        ];
    }
}
