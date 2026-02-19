<?php

namespace App\Filament\SuperAdmin\Resources\SubscriptionPlanResource\Pages;

use App\Filament\SuperAdmin\Resources\SubscriptionPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptionPlans extends ListRecords
{
    use ListRecords\Concerns\Translatable;

    protected static string $resource = SubscriptionPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\LocaleSwitcher::make(),
            Actions\CreateAction::make()
                ->label('Create Plan'),
        ];
    }
}
