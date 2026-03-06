<?php

namespace Modules\Services\Filament\Resources\ServiceCategoryResource\Pages;

use App\Filament\Resources\Pages\BaseViewRecord;
use Filament\Actions;
use Modules\Services\Filament\Resources\ServiceCategoryResource;

class ViewServiceCategory extends BaseViewRecord
{
    protected static string $resource = ServiceCategoryResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
