<?php

namespace Modules\Core\Filament\Resources\RoomResource\Pages;

use Modules\Core\Filament\Resources\RoomResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditRoom extends BaseEditRecord
{
    use Translatable;

    protected static string $resource = RoomResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
