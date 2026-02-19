<?php

namespace App\Filament\SuperAdmin\Resources\AnnouncementResource\Pages;

use App\Filament\SuperAdmin\Resources\AnnouncementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAnnouncements extends ListRecords
{
    use ListRecords\Concerns\Translatable;

    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\LocaleSwitcher::make(),
            Actions\CreateAction::make()
                ->label('New Announcement'),
        ];
    }
}
