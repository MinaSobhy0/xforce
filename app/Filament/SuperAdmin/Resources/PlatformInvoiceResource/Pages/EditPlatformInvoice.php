<?php

namespace App\Filament\SuperAdmin\Resources\PlatformInvoiceResource\Pages;

use App\Filament\SuperAdmin\Resources\PlatformInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlatformInvoice extends EditRecord
{
    protected static string $resource = PlatformInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Recalculate totals
        $subtotal = ($data['plan_charge_minor'] ?? 0)
            + ($data['addon_charges_minor'] ?? 0)
            + ($data['overage_charges_minor'] ?? 0)
            - ($data['discount_minor'] ?? 0);

        $taxRate = $data['tax_rate'] ?? 0.14;
        $tax = (int) round($subtotal * $taxRate);

        $data['subtotal_minor'] = $subtotal;
        $data['tax_minor'] = $tax;
        $data['total_minor'] = $subtotal + $tax;

        return $data;
    }
}
