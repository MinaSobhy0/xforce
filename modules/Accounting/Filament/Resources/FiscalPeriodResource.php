<?php

namespace Modules\Accounting\Filament\Resources;

use App\Traits\ChecksTenantModuleAccess;
use Modules\Accounting\Filament\Resources\FiscalPeriodResource\Pages;
use Modules\Accounting\Models\FiscalPeriod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;

class FiscalPeriodResource extends Resource
{
    use ChecksTenantModuleAccess;

    protected static ?string $model = FiscalPeriod::class;

    protected static ?string $moduleCode = 'accounting';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 54;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\DatePicker::make('start_date')
                            ->required(),

                        Forms\Components\DatePicker::make('end_date')
                            ->required()
                            ->afterOrEqual('start_date'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => FiscalPeriod::STATUS_OPEN,
                        'warning' => FiscalPeriod::STATUS_CLOSED,
                        'danger' => FiscalPeriod::STATUS_LOCKED,
                    ]),

                Tables\Columns\TextColumn::make('closedBy.name')
                    ->label('Closed By')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('closed_at')
                    ->dateTime()
                    ->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(FiscalPeriod::STATUSES),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (FiscalPeriod $record) => $record->isOpen()),

                Tables\Actions\Action::make('close')
                    ->label('Close Period')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (FiscalPeriod $record) => $record->isOpen())
                    ->action(fn (FiscalPeriod $record) => $record->close()),

                Tables\Actions\Action::make('reopen')
                    ->label('Reopen Period')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (FiscalPeriod $record) => $record->isClosed())
                    ->action(fn (FiscalPeriod $record) => $record->reopen()),

                Tables\Actions\Action::make('lock')
                    ->label('Lock Period')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Locking a period is permanent and cannot be undone.')
                    ->visible(fn (FiscalPeriod $record) => $record->isClosed())
                    ->action(fn (FiscalPeriod $record) => $record->lock()),
            ])
            ->bulkActions([])
            ->defaultSort('start_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFiscalPeriods::route('/'),
            'create' => Pages\CreateFiscalPeriod::route('/create'),
            'edit' => Pages\EditFiscalPeriod::route('/{record}/edit'),
        ];
    }
}
