<?php

namespace Modules\Marketing\Filament\Resources\CampaignResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Marketing\Filament\Resources\CampaignResource;

class CreateCampaign extends CreateRecord
{
    protected static string $resource = CampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = app('currentTenant')?->id;
        $data['created_by_user_id'] = auth()->id();
        return $data;
    }
}
