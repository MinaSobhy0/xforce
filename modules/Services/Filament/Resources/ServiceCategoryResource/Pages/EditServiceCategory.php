<?php

namespace Modules\Services\Filament\Resources\ServiceCategoryResource\Pages;

use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Actions;
use Modules\Services\Filament\Resources\ServiceCategoryResource;

class EditServiceCategory extends BaseEditRecord
{
    protected static string $resource = ServiceCategoryResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
