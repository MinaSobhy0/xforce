<?php

namespace Modules\Memberships\Filament\Resources\MembershipResource\Pages;

use Modules\Memberships\Filament\Resources\MembershipResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMembership extends ViewRecord
{
    protected static string $resource = MembershipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
