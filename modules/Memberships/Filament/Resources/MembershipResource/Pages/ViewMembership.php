<?php

namespace Modules\Memberships\Filament\Resources\MembershipResource\Pages;

use Modules\Memberships\Filament\Resources\MembershipResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;

class ViewMembership extends BaseViewRecord
{
    protected static string $resource = MembershipResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
