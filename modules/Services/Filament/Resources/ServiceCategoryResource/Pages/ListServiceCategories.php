<?php

namespace Modules\Services\Filament\Resources\ServiceCategoryResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Services\Filament\Resources\ServiceCategoryResource;

class ListServiceCategories extends BaseListRecords
{
    protected static string $resource = ServiceCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            ...parent::getHeaderActions(),
        ];
    }
}
