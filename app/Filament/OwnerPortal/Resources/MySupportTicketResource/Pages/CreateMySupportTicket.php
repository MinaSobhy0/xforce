<?php

namespace App\Filament\OwnerPortal\Resources\MySupportTicketResource\Pages;

use App\Filament\OwnerPortal\Resources\MySupportTicketResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateMySupportTicket extends CreateRecord
{
    protected static string $resource = MySupportTicketResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = Auth::user()->tenant_id;
        $data['ticket_number'] = 'TKT-' . strtoupper(uniqid());
        $data['status'] = 'open';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
