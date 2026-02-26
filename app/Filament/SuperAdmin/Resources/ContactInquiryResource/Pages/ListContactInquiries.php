<?php

namespace App\Filament\SuperAdmin\Resources\ContactInquiryResource\Pages;

use App\Filament\SuperAdmin\Resources\ContactInquiryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContactInquiries extends ListRecords
{
    protected static string $resource = ContactInquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
