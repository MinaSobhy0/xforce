<?php

namespace App\Filament\Admin\Resources\MyInvoiceResource\Pages;

use App\Filament\Admin\Resources\MyInvoiceResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class ViewMyInvoice extends ViewRecord
{
    protected static string $resource = MyInvoiceResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Invoice Details')
                    ->columns(3)
                    ->schema([
                        Components\TextEntry::make('invoice_number')
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

                Components\Section::make('Amount')
                    ->columns(3)
                    ->schema([
                        Components\TextEntry::make('amount_minor')
                            ->label('Subtotal')
                            ->formatStateUsing(fn($state) => 'EGP ' . number_format($state / 100, 2)),

                        Components\TextEntry::make('overage_minor')
                            ->label('Overage')
                            ->formatStateUsing(fn($state) => 'EGP ' . number_format(($state ?? 0) / 100, 2)),

                        Components\TextEntry::make('total')
                            ->label('Total')
                            ->state(fn($record) => 'EGP ' . number_format((($record->amount_minor ?? 0) + ($record->overage_minor ?? 0)) / 100, 2))
                            ->weight('bold'),
                    ]),

                Components\Section::make('Notes')
                    ->schema([
                        Components\TextEntry::make('notes')
                            ->placeholder('No notes'),
                    ])
                    ->collapsed(),
            ]);
    }
}
