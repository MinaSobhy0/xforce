<?php

namespace Modules\Memberships\Filament\Resources\MembershipResource\Pages;

use Modules\Memberships\Filament\Resources\MembershipResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMembership extends CreateRecord
{
    protected static string $resource = MembershipResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
