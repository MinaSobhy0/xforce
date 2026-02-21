<?php

namespace App\Filament\SuperAdmin\Resources\ModuleResource\Pages;

use App\Filament\SuperAdmin\Resources\ModuleResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;

class ListModules extends BaseListRecords
{
    use Translatable;

    protected static string $resource = ModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            \Filament\Actions\LocaleSwitcher::make(),
            Actions\CreateAction::make()
                ->label('Register Module'),
        ];
    }
}
