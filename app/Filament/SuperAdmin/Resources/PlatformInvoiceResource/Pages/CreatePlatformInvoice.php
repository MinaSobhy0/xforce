<?php

namespace App\Filament\SuperAdmin\Resources\PlatformInvoiceResource\Pages;

use App\Filament\SuperAdmin\Resources\PlatformInvoiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlatformInvoice extends CreateRecord
{
    protected static string $resource = PlatformInvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Convert main currency to piasters (minor units)
        $data['plan_charge_minor'] = (int) (($data['plan_charge'] ?? 0) * 100);
        $data['addon_charges_minor'] = (int) (($data['addon_charges'] ?? 0) * 100);
        $data['overage_charges_minor'] = (int) (($data['overage_charges'] ?? 0) * 100);
        $data['discount_minor'] = (int) (($data['discount'] ?? 0) * 100);

        // Convert tax rate from percentage to decimal
        $taxRatePercent = $data['tax_rate'] ?? 14;
        $data['tax_rate'] = $taxRatePercent / 100; // Store as decimal (0.14)

        // Calculate totals in piasters
        $subtotal = $data['plan_charge_minor'] + $data['addon_charges_minor']
            + $data['overage_charges_minor'] - $data['discount_minor'];
        $tax = (int) round($subtotal * $data['tax_rate']);

        $data['subtotal_minor'] = $subtotal;
        $data['tax_minor'] = $tax;
        $data['total_minor'] = $subtotal + $tax;

        // Convert line items to piasters
        if (!empty($data['line_items'])) {
            foreach ($data['line_items'] as &$item) {
                $item['amount_minor'] = (int) (($item['amount'] ?? 0) * 100);
                unset($item['amount']);
            }
        }

        // Remove display-only fields
        unset($data['plan_charge'], $data['addon_charges'], $data['overage_charges'],
              $data['discount'], $data['subtotal'], $data['tax'], $data['total']);

        return $data;
    }
}
