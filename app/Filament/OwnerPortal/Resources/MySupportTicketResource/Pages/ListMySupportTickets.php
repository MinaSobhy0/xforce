<?php

namespace App\Filament\OwnerPortal\Resources\MySupportTicketResource\Pages;

use App\Filament\OwnerPortal\Resources\MySupportTicketResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;

class ListMySupportTickets extends BaseListRecords
{
    protected static string $resource = MySupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make()
                ->label('New Ticket'),
        ];
    }
}
