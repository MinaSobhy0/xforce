<?php

namespace App\Filament\SuperAdmin\Resources\PlatformEmailCampaignResource\Pages;

use App\Filament\SuperAdmin\Resources\PlatformEmailCampaignResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlatformEmailCampaign extends CreateRecord
{
    protected static string $resource = PlatformEmailCampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();
        return $data;
    }
}
