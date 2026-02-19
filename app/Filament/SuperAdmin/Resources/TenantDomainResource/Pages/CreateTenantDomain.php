<?php

namespace App\Filament\SuperAdmin\Resources\TenantDomainResource\Pages;

use App\Filament\SuperAdmin\Resources\TenantDomainResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTenantDomain extends CreateRecord
{
    protected static string $resource = TenantDomainResource::class;

    protected function afterCreate(): void
    {
        // Generate verification token
        $this->record->update([
            'verification_token' => \Illuminate\Support\Str::random(32),
        ]);
    }
}
