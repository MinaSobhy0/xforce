<?php

namespace Modules\Billing\Filament\Resources\PaymentResource\Pages;

use Modules\Billing\Filament\Resources\PaymentResource;
use App\Filament\Resources\Pages\BaseViewRecord;

class ViewPayment extends BaseViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getViewHeaderActions(): array
    {
        return [];
    }
}
