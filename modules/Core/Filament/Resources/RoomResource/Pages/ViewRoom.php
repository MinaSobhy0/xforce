<?php

namespace Modules\Core\Filament\Resources\RoomResource\Pages;

use Modules\Core\Filament\Resources\RoomResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\Pages\ViewRecord\Concerns\Translatable;

class ViewRoom extends ViewRecord
{
    use Translatable;

    protected static string $resource = RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
