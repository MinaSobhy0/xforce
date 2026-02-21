<?php

namespace App\Filament\SuperAdmin\Resources\ContactInquiryResource\Pages;

use App\Filament\SuperAdmin\Resources\ContactInquiryResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditContactInquiry extends BaseEditRecord
{
    protected static string $resource = ContactInquiryResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
