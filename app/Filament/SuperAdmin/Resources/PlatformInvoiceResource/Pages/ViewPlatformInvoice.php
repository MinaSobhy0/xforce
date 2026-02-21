<?php

namespace App\Filament\SuperAdmin\Resources\PlatformInvoiceResource\Pages;

use App\Filament\SuperAdmin\Resources\PlatformInvoiceResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;

class ViewPlatformInvoice extends BaseViewRecord
{
    protected static string $resource = PlatformInvoiceResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
