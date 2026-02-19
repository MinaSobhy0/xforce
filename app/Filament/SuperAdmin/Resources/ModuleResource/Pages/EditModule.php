<?php

namespace App\Filament\SuperAdmin\Resources\ModuleResource\Pages;

use App\Filament\SuperAdmin\Resources\ModuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditModule extends EditRecord
{
    use EditRecord\Concerns\Translatable;

    protected static string $resource = ModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\LocaleSwitcher::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
