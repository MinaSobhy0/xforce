<?php

namespace Modules\Inventory\Filament\Resources\PurchaseOrderResource\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\Page;
use Filament\Notifications\Notification;
use Modules\Inventory\Models\PurchaseOrder;
use Modules\Inventory\Models\PurchaseOrderLine;
use Modules\Inventory\Models\StockLocation;
use Modules\Inventory\Filament\Resources\PurchaseOrderResource;

class ReceivePurchaseOrder extends Page
{
    protected static string $resource = PurchaseOrderResource::class;

    protected static string $view = 'inventory::filament.pages.receive-purchase-order';

    public ?array $data = [];

    public PurchaseOrder $record;

    public function mount(int | string $record): void
    {
        // Handle case where record might be passed as JSON or model
        $recordId = is_string($record) && str_starts_with($record, '{')
            ? json_decode($record, true)['id'] ?? $record
            : (is_object($record) ? $record->getKey() : $record);

        $this->record = PurchaseOrder::with(['lines.product', 'lines.uom'])->findOrFail($recordId);

        if (!$this->record->canReceive()) {
            Notification::make()
                ->title(__('inventory::inventory.messages.cannot_receive'))
                ->danger()
                ->send();

            $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
            return;
        }

        $this->form->fill([
            'lines' => $this->record->lines->map(function (PurchaseOrderLine $line) {
                return [
                    'id' => $line->id,
                    'product_name' => $line->product?->getTranslation('name', app()->getLocale()),
                    'uom_name' => $line->uom?->getTranslation('name', app()->getLocale()) ?? $line->uom?->name,
                    'ordered' => $line->quantity,
                    'received' => $line->quantity_received,
                    'remaining' => $line->remaining_quantity,
                    'receive_now' => $line->remaining_quantity, // Default to receiving all remaining
                ];
            })->toArray(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('inventory::inventory.sections.receive_items'))
                    ->schema([
                        Forms\Components\Select::make('destination_location_id')
                            ->label(__('inventory::inventory.fields.destination_location'))
                            ->options(function () {
                                $branchId = $this->record->branch_id;
                                return StockLocation::where('branch_id', $branchId)
                                    ->where('location_type', StockLocation::TYPE_INTERNAL)
                                    ->active()
                                    ->orderBy('sort_order')
                                    ->get()
                                    ->pluck('indented_name', 'id');
                            })
                            ->default(function () {
                                $branchId = $this->record->branch_id;
                                // Default to first internal location (ordered by sort_order)
                                return StockLocation::where('branch_id', $branchId)
                                    ->where('location_type', StockLocation::TYPE_INTERNAL)
                                    ->active()
                                    ->orderBy('sort_order')
                                    ->first()?->id;
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText(__('inventory::inventory.helpers.destination_location')),

                        Forms\Components\Repeater::make('lines')
                            ->schema([
                                Forms\Components\Hidden::make('id'),

                                Forms\Components\TextInput::make('product_name')
                                    ->label(__('inventory::inventory.fields.product'))
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('uom_name')
                                    ->label(__('inventory::inventory.fields.uom'))
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('ordered')
                                    ->label(__('inventory::inventory.fields.ordered'))
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('received')
                                    ->label(__('inventory::inventory.fields.already_received'))
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('remaining')
                                    ->label(__('inventory::inventory.fields.remaining'))
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('receive_now')
                                    ->label(__('inventory::inventory.fields.receive_now'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->columnSpan(1),
                            ])
                            ->columns(9)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false),
                    ]),
            ])
            ->statePath('data');
    }

    public function receive(): void
    {
        $data = $this->form->getState();

        $hasReceivedItems = false;
        $locationId = $data['destination_location_id'] ?? null;

        foreach ($data['lines'] as $lineData) {
            $receiveQty = (int) ($lineData['receive_now'] ?? 0);

            if ($receiveQty > 0) {
                $line = PurchaseOrderLine::find($lineData['id']);

                if ($line && $line->remaining_quantity > 0) {
                    $line->receiveItems(min($receiveQty, $line->remaining_quantity), $locationId);
                    $hasReceivedItems = true;
                }
            }
        }

        if ($hasReceivedItems) {
            // Refresh the order to update status
            $this->record->refresh();

            Notification::make()
                ->title(__('inventory::inventory.messages.items_received'))
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title(__('inventory::inventory.messages.no_items_to_receive'))
                ->warning()
                ->send();
        }

        $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return __('inventory::inventory.pages.receive_order', ['number' => $this->record->order_number]);
    }
}
