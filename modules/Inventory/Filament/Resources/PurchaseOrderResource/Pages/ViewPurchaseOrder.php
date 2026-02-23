<?php

namespace Modules\Inventory\Filament\Resources\PurchaseOrderResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;
use Filament\Notifications\Notification;
use Modules\Inventory\Models\PurchaseOrder;
use Modules\Inventory\Models\VendorBill;
use Modules\Inventory\Filament\Resources\PurchaseOrderResource;
use Modules\Inventory\Filament\Resources\VendorBillResource;

class ViewPurchaseOrder extends BaseViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->isEditable()),

            Actions\Action::make('send')
                ->label(__('inventory::inventory.actions.send'))
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->visible(fn () => $this->record->canTransitionTo(PurchaseOrder::STATUS_SENT))
                ->requiresConfirmation()
                ->action(function () {
                    if ($this->record->send(auth()->id())) {
                        Notification::make()
                            ->title(__('inventory::inventory.messages.order_sent'))
                            ->success()
                            ->send();
                        $this->refreshFormData(['status']);
                    }
                }),

            Actions\Action::make('receive')
                ->label(__('inventory::inventory.actions.receive'))
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('success')
                ->visible(fn () => $this->record->canReceive())
                ->url(fn () => $this->getResource()::getUrl('receive', ['record' => $this->record->getKey()])),

            Actions\Action::make('cancel')
                ->label(__('inventory::inventory.actions.cancel'))
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn () => $this->record->canTransitionTo(PurchaseOrder::STATUS_CANCELLED))
                ->requiresConfirmation()
                ->action(function () {
                    if ($this->record->cancel()) {
                        Notification::make()
                            ->title(__('inventory::inventory.messages.order_cancelled'))
                            ->success()
                            ->send();
                        $this->refreshFormData(['status']);
                    }
                }),

            Actions\Action::make('reset_to_draft')
                ->label(__('inventory::inventory.actions.reset_to_draft'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn () => $this->record->canResetToDraft())
                ->requiresConfirmation()
                ->action(function () {
                    if ($this->record->resetToDraft()) {
                        Notification::make()
                            ->title(__('inventory::inventory.messages.order_reset_to_draft'))
                            ->success()
                            ->send();
                        $this->refreshFormData(['status']);
                    }
                }),

            Actions\Action::make('create_bill')
                ->label('Create Vendor Bill')
                ->icon('heroicon-o-document-minus')
                ->color('primary')
                ->visible(fn () => $this->record->isReceived() && !$this->record->vendor_bill_id)
                ->requiresConfirmation()
                ->modalDescription('This will create a vendor bill from this purchase order.')
                ->action(function () {
                    $bill = VendorBill::createFromPurchaseOrder($this->record);

                    Notification::make()
                        ->title('Vendor bill created successfully')
                        ->success()
                        ->send();

                    return redirect(VendorBillResource::getUrl('view', ['record' => $bill]));
                }),

            Actions\Action::make('view_bill')
                ->label('View Vendor Bill')
                ->icon('heroicon-o-document-minus')
                ->color('info')
                ->visible(fn () => $this->record->vendor_bill_id !== null)
                ->url(fn () => VendorBillResource::getUrl('view', ['record' => $this->record->vendor_bill_id])),

            Actions\Action::make('reverse_receiving')
                ->label(__('inventory::inventory.actions.reverse_receiving'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn () => $this->record->canReverseReceiving())
                ->modalHeading(__('inventory::inventory.actions.reverse_receiving'))
                ->modalDescription(__('inventory::inventory.messages.reverse_confirmation'))
                ->form(function () {
                    $lines = $this->record->lines()
                        ->where('quantity_received', '>', 0)
                        ->with('product')
                        ->get();

                    $schema = [];
                    foreach ($lines as $line) {
                        $schema[] = \Filament\Forms\Components\Grid::make(3)
                            ->schema([
                                \Filament\Forms\Components\Placeholder::make("product_{$line->id}")
                                    ->label(__('inventory::inventory.fields.product'))
                                    ->content("[{$line->product->sku}] " . $line->product->getTranslation('name', app()->getLocale())),

                                \Filament\Forms\Components\Placeholder::make("received_{$line->id}")
                                    ->label(__('inventory::inventory.fields.received'))
                                    ->content($line->quantity_received),

                                \Filament\Forms\Components\TextInput::make("reverse_qty_{$line->id}")
                                    ->label(__('inventory::inventory.fields.reverse_qty'))
                                    ->numeric()
                                    ->default($line->quantity_received)
                                    ->minValue(0)
                                    ->maxValue($line->quantity_received)
                                    ->required(),
                            ]);
                    }

                    return $schema;
                })
                ->action(function (array $data) {
                    $reversed = false;

                    \DB::transaction(function () use ($data, &$reversed) {
                        foreach ($this->record->lines()->where('quantity_received', '>', 0)->get() as $line) {
                            $reverseQty = (int) ($data["reverse_qty_{$line->id}"] ?? 0);

                            if ($reverseQty > 0) {
                                $line->reverseReceiving($reverseQty);
                                $reversed = true;
                            }
                        }

                        // Update order status based on remaining received quantities
                        $this->record->refresh();
                        $totalReceived = $this->record->lines()->sum('quantity_received');

                        if ($totalReceived === 0) {
                            $this->record->status = \Modules\Inventory\Models\PurchaseOrder::STATUS_SENT;
                            $this->record->received_date = null;
                            $this->record->received_by = null;
                        } elseif ($totalReceived < $this->record->lines()->sum('quantity')) {
                            $this->record->status = \Modules\Inventory\Models\PurchaseOrder::STATUS_PARTIALLY_RECEIVED;
                        }
                        $this->record->save();
                    });

                    if ($reversed) {
                        Notification::make()
                            ->title(__('inventory::inventory.messages.receiving_reversed'))
                            ->success()
                            ->send();
                        $this->refreshFormData(['status']);
                    }
                }),
        ];
    }
}
