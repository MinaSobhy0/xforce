<?php

namespace Modules\Auth\Filament\Resources\AccessPolicyResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Modules\Auth\Filament\Resources\AccessPolicyResource;

class EditAccessPolicy extends EditRecord
{
    protected static string $resource = AccessPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
