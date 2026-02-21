<?php

namespace App\Filament\SuperAdmin\Resources\ModuleResource\Pages;

use App\Filament\SuperAdmin\Resources\ModuleResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditModule extends BaseEditRecord
{
    use EditRecord\Concerns\Translatable;

    protected static string $resource = ModuleResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            \Filament\Actions\LocaleSwitcher::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
