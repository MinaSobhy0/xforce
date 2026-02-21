<?php

namespace App\Filament\SuperAdmin\Resources\SubscriptionPlanResource\Pages;

use App\Filament\SuperAdmin\Resources\SubscriptionPlanResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;

class ListSubscriptionPlans extends BaseListRecords
{
    use Translatable;

    protected static string $resource = SubscriptionPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            \Filament\Actions\LocaleSwitcher::make(),
            Actions\CreateAction::make()
                ->label('Create Plan'),
        ];
    }
}
