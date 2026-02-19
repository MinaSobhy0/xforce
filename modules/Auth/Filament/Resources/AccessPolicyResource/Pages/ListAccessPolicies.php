<?php

namespace Modules\Auth\Filament\Resources\AccessPolicyResource\Pages;

use Modules\Auth\Filament\Resources\AccessPolicyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

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
