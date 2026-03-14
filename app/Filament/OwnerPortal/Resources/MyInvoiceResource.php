<?php

namespace App\Filament\OwnerPortal\Resources;

use App\Filament\OwnerPortal\Resources\MyInvoiceResource\Pages;
use App\Models\PlatformInvoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MyInvoiceResource extends Resource
{
    protected static ?string $model = PlatformInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Subscription';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'My Invoices';

    protected static ?string $modelLabel = 'Invoice';

    protected static ?string $pluralModelLabel = 'Invoices';

    protected static ?string $slug = 'my-invoices';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', Auth::user()?->tenant_id);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_start')
                    ->label('Period')
                    ->formatStateUsing(fn($state, $record) =>
                        $state?->format('M d') . ' - ' . $record->period_end?->format('M d, Y')
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_minor')
                    ->label('Amount')
                    ->money('EGP', divideBy: 100)
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'overdue' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Paid On')
                    ->date()
                    ->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'overdue' => 'Overdue',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('download')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function (PlatformInvoice $record) {
                        // TODO: Generate and download PDF
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyInvoices::route('/'),
            'view' => Pages\ViewMyInvoice::route('/{record}'),
        ];
    }
}
