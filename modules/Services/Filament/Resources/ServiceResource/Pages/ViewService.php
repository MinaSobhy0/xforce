<?php

namespace Modules\Services\Filament\Resources\ServiceResource\Pages;

use App\Filament\Resources\Pages\BaseViewRecord;
use Filament\Actions;
use Modules\Services\Filament\Resources\ServiceResource;

class ViewService extends BaseViewRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
