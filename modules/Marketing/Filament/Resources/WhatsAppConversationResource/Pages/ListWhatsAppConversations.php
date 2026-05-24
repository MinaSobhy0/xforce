<?php

namespace Modules\Marketing\Filament\Resources\WhatsAppConversationResource\Pages;

use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Marketing\Filament\Resources\WhatsAppConversationResource;

class ListWhatsAppConversations extends BaseListRecords
{
    protected static string $resource = WhatsAppConversationResource::class;

    /** Poll-refresh every 10s so freshly inbound conversations surface. */
    protected ?string $pollingInterval = '10s';

    protected function getHeaderActions(): array
    {
        return []; // Conversations are created by inbound webhooks, not the admin
    }
}
