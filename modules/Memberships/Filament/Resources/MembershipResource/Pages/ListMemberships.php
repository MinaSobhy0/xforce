<?php

namespace Modules\Memberships\Filament\Resources\MembershipResource\Pages;

use Modules\Memberships\Filament\Resources\MembershipResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;

class ListMemberships extends BaseListRecords
{
    protected static string $resource = MembershipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
