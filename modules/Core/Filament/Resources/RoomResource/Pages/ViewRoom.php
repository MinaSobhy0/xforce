<?php

namespace Modules\Core\Filament\Resources\RoomResource\Pages;

use Modules\Core\Filament\Resources\RoomResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;
use Filament\Resources\Pages\ViewRecord\Concerns\Translatable;

class ViewRoom extends BaseViewRecord
{
    use Translatable;

    protected static string $resource = RoomResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
