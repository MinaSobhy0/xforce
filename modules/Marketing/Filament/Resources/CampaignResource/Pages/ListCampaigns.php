<?php

namespace Modules\Marketing\Filament\Resources\CampaignResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Marketing\Filament\Resources\CampaignResource;

class ListCampaigns extends BaseListRecords
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
