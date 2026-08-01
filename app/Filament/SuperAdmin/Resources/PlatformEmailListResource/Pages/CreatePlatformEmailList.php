<?php

namespace App\Filament\SuperAdmin\Resources\PlatformEmailListResource\Pages;

use App\Filament\SuperAdmin\Resources\PlatformEmailListResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlatformEmailList extends CreateRecord
{
    protected static string $resource = PlatformEmailListResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }
}
