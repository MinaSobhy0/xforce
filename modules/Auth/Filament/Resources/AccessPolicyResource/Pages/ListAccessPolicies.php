<?php

namespace Modules\Auth\Filament\Resources\AccessPolicyResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Auth\Filament\Resources\AccessPolicyResource;

class ListAccessPolicies extends BaseListRecords
{
    protected static string $resource = AccessPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
