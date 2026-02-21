<?php

namespace App\Filament\SuperAdmin\Resources\SupportTicketResource\Pages;

use App\Filament\SuperAdmin\Resources\SupportTicketResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditSupportTicket extends BaseEditRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
