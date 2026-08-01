<?php

namespace App\Filament\SuperAdmin\Resources\PlatformEmailSuppressionResource\Pages;

use App\Filament\SuperAdmin\Resources\PlatformEmailSuppressionResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlatformEmailSuppression extends CreateRecord
{
    protected static string $resource = PlatformEmailSuppressionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['added_by_user_id'] = auth()->id();

        return $data;
    }
}
