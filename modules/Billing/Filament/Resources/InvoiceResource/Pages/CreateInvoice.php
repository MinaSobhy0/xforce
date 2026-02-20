<?php

namespace Modules\Billing\Filament\Resources\InvoiceResource\Pages;

use Modules\Billing\Filament\Resources\InvoiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();
        return $data;
    }

    protected function afterCreate(): void
    {
        // Recalculate totals after lines are created
        $this->record->recalculateTotals();
    }
}
