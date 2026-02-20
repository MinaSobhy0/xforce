<?php

namespace App\Filament\SuperAdmin\Resources\ContactInquiryResource\Pages;

use App\Filament\SuperAdmin\Resources\ContactInquiryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContactInquiry extends EditRecord
{
    protected static string $resource = ContactInquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
