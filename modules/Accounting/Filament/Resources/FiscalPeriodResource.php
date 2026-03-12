<?php

namespace Modules\Accounting\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
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
    use ChecksResourcePermissions;

    protected static ?string $model = FiscalPeriod::class;

    protected static ?string $moduleCode = 'accounting';

    protected static ?string $permissionKey = 'fiscal_periods';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 45;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('accounting::accounting.fiscal_periods');
    }

    public static function getModelLabel(): string
    {
        return __('accounting::accounting.fiscal_period');
    }

    public static function getPluralModelLabel(): string
    {
        return __('accounting::accounting.fiscal_periods');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('accounting::accounting.period_resource.name'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\DatePicker::make('start_date')
                            ->label(__('accounting::accounting.period_resource.start_date'))
                            ->required(),

                        Forms\Components\DatePicker::make('end_date')
                            ->label(__('accounting::accounting.period_resource.end_date'))
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
                    ->label(__('accounting::accounting.period_resource.name'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('start_date')
                    ->label(__('accounting::accounting.period_resource.start_date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label(__('accounting::accounting.period_resource.end_date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('accounting::accounting.status'))
                    ->colors([
                        'success' => FiscalPeriod::STATUS_OPEN,
                        'warning' => FiscalPeriod::STATUS_CLOSED,
                        'danger' => FiscalPeriod::STATUS_LOCKED,
                    ]),

                Tables\Columns\TextColumn::make('closedBy.name')
                    ->label(__('accounting::accounting.period_resource.closed_by'))
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('closed_at')
                    ->label(__('accounting::accounting.period_resource.closed_at'))
                    ->dateTime()
                    ->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('accounting::accounting.status'))
                    ->options(FiscalPeriod::STATUSES),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (FiscalPeriod $record) => $record->isOpen()),

                Tables\Actions\Action::make('close')
                    ->label(__('accounting::accounting.period_resource.close_period'))
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (FiscalPeriod $record) => $record->isOpen())
                    ->action(fn (FiscalPeriod $record) => $record->close()),

                Tables\Actions\Action::make('reopen')
                    ->label(__('accounting::accounting.period_resource.reopen_period'))
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (FiscalPeriod $record) => $record->isClosed())
                    ->action(fn (FiscalPeriod $record) => $record->reopen()),

                Tables\Actions\Action::make('lock')
                    ->label(__('accounting::accounting.period_resource.lock_period'))
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('accounting::accounting.period_resource.lock_warning'))
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
