<?php

namespace Modules\Booking\Filament\Resources\VisitResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Modules\Booking\Filament\Pages\Checkout;
use Modules\Booking\Filament\Resources\VisitResource;
use Modules\Booking\Models\Visit;

class ViewVisit extends ViewRecord
{
    protected static string $resource = VisitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('checkout')
                ->label(__('booking::visits.actions.checkout'))
                ->icon('heroicon-o-shopping-cart')
                ->color('success')
                ->url(fn (Visit $record) => Checkout::getUrl(['visit_id' => $record->id]))
                ->visible(fn (Visit $record) => $record->canCheckout()),

            Actions\Action::make('view_invoice')
                ->label(__('booking::visits.actions.view_invoice'))
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->url(fn (Visit $record) => $record->invoice_id
                    ? \Modules\Billing\Filament\Resources\InvoiceResource::getUrl('view', ['record' => $record->invoice_id])
                    : null
                )
                ->visible(fn (Visit $record) => $record->invoice_id !== null),
        ];
    }
}
