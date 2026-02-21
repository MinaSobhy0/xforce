<?php

namespace App\Filament\SuperAdmin\Resources\SupportTicketResource\Pages;

use App\Filament\SuperAdmin\Resources\SupportTicketResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;

class ListSupportTickets extends BaseListRecords
{
    protected static string $resource = SupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make()
                ->label('Create Ticket'),
        ];
    }
}
