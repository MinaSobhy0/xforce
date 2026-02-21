<?php

namespace Modules\Core\Filament\Resources\RoomResource\Pages;

use Modules\Core\Filament\Resources\RoomResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;

class ListRooms extends BaseListRecords
{
    use Translatable;

    protected static string $resource = RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\LocaleSwitcher::make(),
            Actions\CreateAction::make(),
        ];
    }
}
