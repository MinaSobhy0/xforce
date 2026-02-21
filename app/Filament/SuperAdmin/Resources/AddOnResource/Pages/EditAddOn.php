<?php

namespace App\Filament\SuperAdmin\Resources\AddOnResource\Pages;

use App\Filament\SuperAdmin\Resources\AddOnResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditAddOn extends BaseEditRecord
{
    protected static string $resource = AddOnResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
