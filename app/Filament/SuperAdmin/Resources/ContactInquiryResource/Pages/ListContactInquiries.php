<?php

namespace App\Filament\SuperAdmin\Resources\ContactInquiryResource\Pages;

use App\Filament\SuperAdmin\Resources\ContactInquiryResource;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Actions;

class ListContactInquiries extends BaseListRecords
{
    protected static string $resource = ContactInquiryResource::class;

    protected function getHeaderActions(): array
    {
        return array_merge(
            [Actions\CreateAction::make()],
            parent::getHeaderActions()
        );
    }
}
