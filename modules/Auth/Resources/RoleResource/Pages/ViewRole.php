<?php

namespace Modules\Auth\Resources\RoleResource\Pages;

use Modules\Auth\Resources\RoleResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;

class ViewRole extends BaseViewRecord
{
    protected static string $resource = RoleResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}