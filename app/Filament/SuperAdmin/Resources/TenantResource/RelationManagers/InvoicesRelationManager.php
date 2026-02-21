<?php

namespace App\Filament\SuperAdmin\Resources\TenantResource\RelationManagers;

use App\Models\PlatformInvoice;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Invoice History';

    protected static ?string $icon = 'heroicon-o-document-text';

    protected static bool $isLazy = false;

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Invoice #')
                    ->searchable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('period_start')
                    ->label('Period')
                    ->date('M Y'),

                Tables\Columns\TextColumn::make('plan_charge_minor')
                    ->label('Plan')
                    ->money('EGP', divideBy: 100),

                Tables\Columns\TextColumn::make('addon_charges_minor')
                    ->label('Add-ons')
                    ->money('EGP', divideBy: 100),

                Tables\Columns\TextColumn::make('overage_charges_minor')
                    ->label('Overage')
                    ->money('EGP', divideBy: 100)
                    ->color(fn(int $state) => $state > 0 ? 'warning' : 'gray'),

                Tables\Columns\TextColumn::make('total_minor')
                    ->label('Total')
                    ->money('EGP', divideBy: 100)
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'paid'    => 'success',
                        'pending' => 'warning',
                        'overdue' => 'danger',
                        'refunded'=> 'gray',
                        default   => 'gray',
                    }),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Paid')
                    ->date()
                    ->placeholder('-'),
            ])
            ->defaultSort('period_start', 'desc')
            ->actions([
                Tables\Actions\Action::make('markPaid')
                    ->label('Mark Paid')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->status === 'pending' || $record->status === 'overdue')
                    ->action(function ($record) {
                        $record->markAsPaid();
                        \Filament\Notifications\Notification::make()
                            ->title('Invoice marked as paid')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('refund')
                    ->label('Refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->status === 'paid')
                    ->action(function ($record) {
                        $record->update(['status' => 'refunded']);
                        \Filament\Notifications\Notification::make()
                            ->title('Invoice refunded')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
