<?php

namespace App\Filament\SuperAdmin\Resources\PlatformAdminResource\Pages;

use App\Filament\SuperAdmin\Resources\PlatformAdminResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlatformAdmin extends EditRecord
{
    protected static string $resource = PlatformAdminResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn() => $this->record->id !== auth()->id()),
        ];
    }
}
