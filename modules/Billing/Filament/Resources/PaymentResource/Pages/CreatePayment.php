<?php

namespace Modules\Billing\Filament\Resources\PaymentResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Billing\Filament\Resources\PaymentResource;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Convert amount to minor units
        if (isset($data['amount_minor'])) {
            $data['amount_minor'] = (int) ($data['amount_minor'] * 100);
        }

        // Set the user who recorded this payment
        $data['received_by_user_id'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
