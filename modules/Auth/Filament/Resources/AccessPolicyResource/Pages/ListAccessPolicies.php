<?php

namespace Modules\Auth\Filament\Resources\AccessPolicyResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Modules\Auth\Filament\Resources\AccessPolicyResource;

class ListAccessPolicies extends ListRecords
{
    protected static string $resource = AccessPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
