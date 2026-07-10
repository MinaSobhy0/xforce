<?php

namespace App\Filament\SuperAdmin\Resources\PlatformEmailCampaignResource\Pages;

use App\Filament\SuperAdmin\Resources\PlatformEmailCampaignResource;
use App\Models\PlatformEmailCampaign;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlatformEmailCampaign extends EditRecord
{
    protected static string $resource = PlatformEmailCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (PlatformEmailCampaign $record) => $record->status === PlatformEmailCampaign::STATUS_DRAFT),
        ];
    }
}
