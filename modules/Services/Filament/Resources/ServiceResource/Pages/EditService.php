<?php

namespace Modules\Services\Filament\Resources\ServiceResource\Pages;

use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Actions;
use Modules\Services\Filament\Resources\ServiceResource;

class EditService extends BaseEditRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
