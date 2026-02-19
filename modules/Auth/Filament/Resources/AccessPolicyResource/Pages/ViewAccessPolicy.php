<?php

namespace Modules\Auth\Filament\Resources\AccessPolicyResource\Pages;

use Modules\Auth\Filament\Resources\AccessPolicyResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAccessPolicy extends ViewRecord
{
    protected static string $resource = AccessPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
