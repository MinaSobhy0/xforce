<?php

namespace App\Filament\OwnerPortal\Resources\MySupportTicketResource\Pages;

use App\Filament\OwnerPortal\Resources\MySupportTicketResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMySupportTickets extends ListRecords
{
    protected static string $resource = MySupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Ticket'),
        ];
    }
}
