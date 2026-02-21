<?php

namespace App\Filament\SuperAdmin\Resources\SubscriptionPlanResource\Pages;

use App\Filament\SuperAdmin\Resources\SubscriptionPlanResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditSubscriptionPlan extends BaseEditRecord
{
    use Translatable;

    protected static string $resource = SubscriptionPlanResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            \Filament\Actions\LocaleSwitcher::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
