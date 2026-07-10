<?php

namespace App\Filament\SuperAdmin\Resources\PlatformEmailListResource\Pages;

use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\SuperAdmin\Resources\PlatformEmailListResource;
use Filament\Actions;

class EditPlatformEmailList extends BaseEditRecord
{
    protected static string $resource = PlatformEmailListResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
