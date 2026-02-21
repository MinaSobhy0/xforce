<?php

namespace Modules\Marketing\Filament\Resources\CampaignResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Modules\Marketing\Filament\Resources\CampaignResource;

class EditCampaign extends BaseEditRecord
{
    protected static string $resource = CampaignResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->isEditable()),
        ];
    }
}
