<?php

namespace App\Filament\SuperAdmin\Resources\SubscriptionPlanResource\Pages;

use App\Filament\SuperAdmin\Resources\SubscriptionPlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSubscriptionPlan extends CreateRecord
{
    use CreateRecord\Concerns\Translatable;

    protected static string $resource = SubscriptionPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\LocaleSwitcher::make(),
        ];
    }
}
