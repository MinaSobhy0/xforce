<?php

namespace App\Filament\OwnerPortal\Resources\MyInvoiceResource\Pages;

use App\Filament\OwnerPortal\Resources\MyInvoiceResource;
use App\Filament\Resources\Pages\BaseViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class ViewMyInvoice extends BaseViewRecord
{
    protected static string $resource = MyInvoiceResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Invoice Details')
                    ->columns(3)
                    ->schema([
                        Components\TextEntry::make('number')
                            ->label('Invoice Number'),

                        Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn(string $state) => match ($state) {
                                'paid' => 'success',
                                'pending' => 'warning',
                                'overdue' => 'danger',
                                default => 'gray',
                            }),

                        Components\TextEntry::make('due_date')
                            ->label('Due Date')
                            ->date(),

                        Components\TextEntry::make('period_start')
                            ->label('Period Start')
                            ->date(),

                        Components\TextEntry::make('period_end')
                            ->label('Period End')
                            ->date(),

                        Components\TextEntry::make('paid_at')
                            ->label('Paid On')
                            ->date()
                            ->placeholder('Not paid yet'),
                    ]),

                Components\Section::make('Charges')
                    ->columns(4)
                    ->schema([
                        Components\TextEntry::make('plan_charge_minor')
                            ->label('Plan')
                            ->formatStateUsing(fn($state, $record) => ($record->currency ?? 'EGP') . ' ' . number_format(($state ?? 0) / 100, 2)),

                        Components\TextEntry::make('addon_charges_minor')
                            ->label('Add-ons')
                            ->formatStateUsing(fn($state, $record) => ($record->currency ?? 'EGP') . ' ' . number_format(($state ?? 0) / 100, 2)),

                        Components\TextEntry::make('overage_charges_minor')
                            ->label('Overage')
                            ->formatStateUsing(fn($state, $record) => ($record->currency ?? 'EGP') . ' ' . number_format(($state ?? 0) / 100, 2)),

                        Components\TextEntry::make('discount_minor')
                            ->label('Discount')
                            ->formatStateUsing(fn($state, $record) => '-' . ($record->currency ?? 'EGP') . ' ' . number_format(($state ?? 0) / 100, 2))
                            ->visible(fn($record) => ($record->discount_minor ?? 0) > 0),
                    ]),

                Components\Section::make('Total')
                    ->columns(3)
                    ->schema([
                        Components\TextEntry::make('subtotal_minor')
                            ->label('Subtotal')
                            ->formatStateUsing(fn($state, $record) => ($record->currency ?? 'EGP') . ' ' . number_format(($state ?? 0) / 100, 2)),

                        Components\TextEntry::make('tax_minor')
                            ->label('Tax')
                            ->formatStateUsing(fn($state, $record) => ($record->currency ?? 'EGP') . ' ' . number_format(($state ?? 0) / 100, 2)),

                        Components\TextEntry::make('total_minor')
                            ->label('Total')
                            ->formatStateUsing(fn($state, $record) => ($record->currency ?? 'EGP') . ' ' . number_format(($state ?? 0) / 100, 2))
                            ->weight('bold')
                            ->size('lg'),
                    ]),

                Components\Section::make('Notes')
                    ->schema([
                        Components\TextEntry::make('notes')
                            ->placeholder('No notes'),
                    ])
                    ->collapsed()
                    ->visible(fn($record) => !empty($record->notes)),
            ]);
    }
}
