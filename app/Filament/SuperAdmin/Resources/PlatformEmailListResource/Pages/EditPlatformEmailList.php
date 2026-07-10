<?php

namespace App\Filament\SuperAdmin\Resources\PlatformEmailListResource\Pages;

use App\Filament\SuperAdmin\Resources\PlatformEmailListResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlatformEmailList extends EditRecord
{
    protected static string $resource = PlatformEmailListResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
