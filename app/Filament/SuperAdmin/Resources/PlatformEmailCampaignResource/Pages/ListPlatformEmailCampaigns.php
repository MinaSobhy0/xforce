<?php

namespace App\Filament\SuperAdmin\Resources\PlatformEmailCampaignResource\Pages;

use App\Filament\SuperAdmin\Resources\PlatformEmailCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlatformEmailCampaigns extends ListRecords
{
    protected static string $resource = PlatformEmailCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('New campaign')];
    }
}
