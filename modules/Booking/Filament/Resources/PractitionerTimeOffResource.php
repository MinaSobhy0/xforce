<?php

namespace Modules\Booking\Filament\Resources;

use App\Traits\ChecksTenantModuleAccess;
use Modules\Booking\Filament\Resources\PractitionerTimeOffResource\Pages;
use Modules\Booking\Models\PractitionerTimeOff;
use Modules\Auth\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PractitionerTimeOffResource extends Resource
{
    use ChecksTenantModuleAccess;

    protected static ?string $model = PractitionerTimeOff::class;

    protected static ?string $moduleCode = 'booking';

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 13;

    public static function getNavigationLabel(): string
    {
        return __('booking::time_off.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('booking::time_off.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('booking::time_off.plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('booking::time_off.sections.request'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('user_id')
                                    ->label(__('booking::time_off.fields.practitioner'))
                                    ->relationship('practitioner', 'first_name')
                                    ->getOptionLabelFromRecordUsing(fn (User $record) => $record->full_name)
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Forms\Components\Select::make('type')
                                    ->label(__('booking::time_off.fields.type'))
                                    ->options(PractitionerTimeOff::TYPES)
                                    ->required(),
                            ]),

                        Forms\Components\Select::make('branch_id')
                            ->label(__('booking::time_off.fields.branch'))
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload()
                            ->default(fn () => current_branch_id())
                            ->helperText(__('booking::time_off.fields.branch_help')),
                    ]),

                Forms\Components\Section::make(__('booking::time_off.sections.period'))
                    ->schema([
                        Forms\Components\Toggle::make('is_full_day')
                            ->label(__('booking::time_off.fields.is_full_day'))
                            ->default(true)
                            ->live(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('start_date')
                                    ->label(__('booking::time_off.fields.start_date'))
                                    ->native(false)
                                    ->required(),

                                Forms\Components\DatePicker::make('end_date')
                                    ->label(__('booking::time_off.fields.end_date'))
                                    ->native(false)
                                    ->required()
                                    ->afterOrEqual('start_date'),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TimePicker::make('start_time')
                                    ->label(__('booking::time_off.fields.start_time'))
                                    ->seconds(false)
                                    ->required(),

                                Forms\Components\TimePicker::make('end_time')
                                    ->label(__('booking::time_off.fields.end_time'))
                                    ->seconds(false)
                                    ->required(),
                            ])
                            ->visible(fn (Forms\Get $get) => !$get('is_full_day')),
                    ]),

                Forms\Components\Section::make(__('booking::time_off.sections.details'))
                    ->schema([
                        Forms\Components\Textarea::make('reason')
                            ->label(__('booking::time_off.fields.reason'))
                            ->rows(2)
                            ->maxLength(500),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('booking::time_off.fields.notes'))
                            ->rows(2)
                            ->maxLength(500),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('practitioner.full_name')
                    ->label(__('booking::time_off.fields.practitioner'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('booking::time_off.fields.type'))
                    ->formatStateUsing(fn (string $state): string => PractitionerTimeOff::TYPES[$state] ?? $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('formatted_period')
                    ->label(__('booking::time_off.fields.period')),

                Tables\Columns\TextColumn::make('duration_days')
                    ->label(__('booking::time_off.fields.days'))
                    ->suffix(' ' . __('booking::appointments.days')),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('booking::time_off.fields.status'))
                    ->colors([
                        'warning' => PractitionerTimeOff::STATUS_PENDING,
                        'success' => PractitionerTimeOff::STATUS_APPROVED,
                        'danger' => PractitionerTimeOff::STATUS_REJECTED,
                        'gray' => PractitionerTimeOff::STATUS_CANCELLED,
                    ])
                    ->formatStateUsing(fn (string $state): string => PractitionerTimeOff::STATUSES[$state] ?? $state),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('booking::time_off.fields.branch'))
                    ->placeholder(__('booking::time_off.all_branches'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('approvedBy.full_name')
                    ->label(__('booking::time_off.fields.approved_by'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('booking::time_off.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label(__('booking::time_off.fields.practitioner'))
                    ->relationship('practitioner', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn (User $record) => $record->full_name)
                    ->preload()
                    ->searchable(),

                Tables\Filters\SelectFilter::make('type')
                    ->label(__('booking::time_off.fields.type'))
                    ->options(PractitionerTimeOff::TYPES),

                Tables\Filters\SelectFilter::make('status')
                    ->label(__('booking::time_off.fields.status'))
                    ->options(PractitionerTimeOff::STATUSES),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label(__('booking::time_off.filters.from')),
                        Forms\Components\DatePicker::make('until')
                            ->label(__('booking::time_off.filters.until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->where('start_date', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->where('end_date', '<=', $date));
                    }),

                Tables\Filters\Filter::make('pending')
                    ->label(__('booking::time_off.filters.pending_only'))
                    ->query(fn (Builder $query): Builder => $query->pending())
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),

                    Tables\Actions\Action::make('approve')
                        ->label(__('booking::time_off.actions.approve'))
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (PractitionerTimeOff $record): bool => $record->isPending())
                        ->action(fn (PractitionerTimeOff $record) => $record->approve(auth()->id())),

                    Tables\Actions\Action::make('reject')
                        ->label(__('booking::time_off.actions.reject'))
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Textarea::make('notes')
                                ->label(__('booking::time_off.fields.rejection_reason'))
                                ->required(),
                        ])
                        ->visible(fn (PractitionerTimeOff $record): bool => $record->isPending())
                        ->action(fn (PractitionerTimeOff $record, array $data) => $record->reject(auth()->id(), $data['notes'])),

                    Tables\Actions\Action::make('cancel')
                        ->label(__('booking::time_off.actions.cancel'))
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->visible(fn (PractitionerTimeOff $record): bool => $record->isActive())
                        ->action(fn (PractitionerTimeOff $record) => $record->cancel()),

                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('start_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPractitionerTimeOff::route('/'),
            'create' => Pages\CreatePractitionerTimeOff::route('/create'),
            'edit' => Pages\EditPractitionerTimeOff::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['practitioner', 'branch', 'approvedBy']);
    }
}
