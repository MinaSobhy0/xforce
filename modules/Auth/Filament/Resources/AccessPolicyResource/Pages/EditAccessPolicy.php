<?php

namespace Modules\Auth\Filament\Resources\AccessPolicyResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Modules\Auth\Filament\Resources\AccessPolicyResource;

class EditAccessPolicy extends BaseEditRecord
{
    protected static string $resource = AccessPolicyResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
