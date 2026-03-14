<?php

namespace App\Filament\SuperAdmin\Resources\PlatformInvoiceResource\Pages;

use App\Filament\SuperAdmin\Resources\PlatformInvoiceResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditPlatformInvoice extends BaseEditRecord
{
    protected static string $resource = PlatformInvoiceResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Convert piasters to main currency for display
        $data['plan_charge'] = ($data['plan_charge_minor'] ?? 0) / 100;
        $data['addon_charges'] = ($data['addon_charges_minor'] ?? 0) / 100;
        $data['overage_charges'] = ($data['overage_charges_minor'] ?? 0) / 100;
        $data['discount'] = ($data['discount_minor'] ?? 0) / 100;
        $data['subtotal'] = ($data['subtotal_minor'] ?? 0) / 100;
        $data['tax'] = ($data['tax_minor'] ?? 0) / 100;
        $data['total'] = ($data['total_minor'] ?? 0) / 100;

        // Convert tax rate from decimal to percentage
        $data['tax_rate'] = ($data['tax_rate'] ?? 0.14) * 100;

        // Convert line items
        if (!empty($data['line_items'])) {
            foreach ($data['line_items'] as &$item) {
                $item['amount'] = ($item['amount_minor'] ?? 0) / 100;
            }
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Convert main currency to piasters
        $data['plan_charge_minor'] = (int) (($data['plan_charge'] ?? 0) * 100);
        $data['addon_charges_minor'] = (int) (($data['addon_charges'] ?? 0) * 100);
        $data['overage_charges_minor'] = (int) (($data['overage_charges'] ?? 0) * 100);
        $data['discount_minor'] = (int) (($data['discount'] ?? 0) * 100);

        // Convert tax rate from percentage to decimal
        $taxRatePercent = $data['tax_rate'] ?? 14;
        $data['tax_rate'] = $taxRatePercent / 100;

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
