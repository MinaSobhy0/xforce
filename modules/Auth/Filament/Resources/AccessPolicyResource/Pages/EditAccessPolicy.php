<?php

namespace Modules\Auth\Filament\Resources\AccessPolicyResource\Pages;

use Modules\Auth\Filament\Resources\AccessPolicyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAccessPolicy extends EditRecord
{
    protected static string $resource = AccessPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
