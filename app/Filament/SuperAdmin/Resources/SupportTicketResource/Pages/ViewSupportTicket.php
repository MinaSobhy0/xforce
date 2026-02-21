<?php

namespace App\Filament\SuperAdmin\Resources\SupportTicketResource\Pages;

use App\Filament\SuperAdmin\Resources\SupportTicketResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;

class ViewSupportTicket extends BaseViewRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
