<?php

namespace App\Filament\SuperAdmin\Resources\ModuleResource\Pages;

use App\Filament\SuperAdmin\Resources\ModuleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateModule extends CreateRecord
{
    use CreateRecord\Concerns\Translatable;

    protected static string $resource = ModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\LocaleSwitcher::make(),
        ];
    }
}
