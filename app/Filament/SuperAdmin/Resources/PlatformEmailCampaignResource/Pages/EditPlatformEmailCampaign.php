<?php

namespace App\Filament\SuperAdmin\Resources\PlatformEmailCampaignResource\Pages;

use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\SuperAdmin\Resources\PlatformEmailCampaignResource;
use App\Filament\SuperAdmin\Resources\PlatformEmailCampaignResource\Concerns\HasCampaignActions;
use App\Models\PlatformEmailCampaign;
use Filament\Actions;

class EditPlatformEmailCampaign extends BaseEditRecord
{
    use HasCampaignActions;

    protected static string $resource = PlatformEmailCampaignResource::class;

    protected function getEditHeaderActions(): array
    {
        return array_merge(
            $this->campaignActions(),
            [
                Actions\DeleteAction::make()
                    ->visible(fn (PlatformEmailCampaign $record) => $record->status === PlatformEmailCampaign::STATUS_DRAFT),
            ],
        );
    }
}
