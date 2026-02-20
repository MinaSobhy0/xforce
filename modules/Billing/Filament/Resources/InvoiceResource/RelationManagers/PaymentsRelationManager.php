<?php

namespace Modules\Billing\Filament\Resources\InvoiceResource\RelationManagers;

use Modules\Billing\Models\Payment;
use Modules\Accounting\Models\Journal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('amount_minor')
                    ->label('Amount')
                    ->numeric()
                    ->required()
                    ->prefix(config('app.currency_symbol', 'EGP'))
                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                    ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0),

                Forms\Components\Select::make('journal_id')
                    ->label('Payment Method')
                    ->options(fn () => Journal::active()
                        ->whereIn('type', ['cash', 'bank'])
                        ->get()
                        ->pluck('display_name', 'id'))
                    ->required()
                    ->searchable()
                    ->default(fn () => Journal::getCashJournal()?->id),

                Forms\Components\DateTimePicker::make('paid_at')
                    ->label('Payment Date/Time')
                    ->required()
                    ->default(now()),

                Forms\Components\TextInput::make('reference_number')
                    ->label('Reference Number')
                    ->maxLength(255),

                Forms\Components\Textarea::make('notes')
                    ->label('Notes')
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Payment #')
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->suffix(' ' . config('app.currency_symbol', 'EGP'))
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('journal.name')
                    ->label('Method')
                    ->badge()
                    ->color(fn (Payment $record) => $record->journal?->type_color ?? 'gray'),

                Tables\Columns\TextColumn::make('reference_number')
                    ->label('Reference')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('receivedBy.name')
                    ->label('Received By'),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Date/Time')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => $this->ownerRecord->canRecordPayment())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['received_by_user_id'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('paid_at', 'desc');
    }
}
