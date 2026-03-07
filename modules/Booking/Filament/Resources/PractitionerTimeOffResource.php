<?php

namespace Modules\Booking\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Booking\Filament\Resources\PractitionerTimeOffResource\Pages;
use Modules\Booking\Models\PractitionerTimeOff;
use Modules\Booking\Models\TimeOffAllocation;
use Modules\Booking\Models\TimeOffType;
use Modules\Auth\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PractitionerTimeOffResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = PractitionerTimeOff::class;

    protected static ?string $moduleCode = 'booking';

    protected static ?string $permissionKey = 'practitioner_time_off';

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

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
                                    ->label(__('booking::time_off.fields.staff'))
                                    ->options(fn () => User::query()->get()->pluck('full_name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($state) {
                                            $user = User::find($state);
                                            if ($user && $user->branch_id) {
                                                $set('branch_id', $user->branch_id);
                                            }
                                        }
                                    }),

                                Forms\Components\Select::make('time_off_type_id')
                                    ->label(__('booking::time_off.fields.time_off_type'))
                                    ->relationship('timeOffType', 'name', fn ($query) => $query->active()->ordered())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                        // Auto-calculate days when type changes
                                        $startDate = $get('start_date');
                                        $endDate = $get('end_date');
                                        if ($startDate && $endDate) {
                                            $days = \Carbon\Carbon::parse($startDate)->diffInDays(\Carbon\Carbon::parse($endDate)) + 1;
                                            $set('days_requested', $days);
                                        }
                                    })
                                    ->helperText(function (Forms\Get $get) {
                                        $userId = $get('user_id');
                                        $typeId = $get('time_off_type_id');
                                        if ($userId && $typeId) {
                                            $allocation = TimeOffAllocation::where('user_id', $userId)
                                                ->where('time_off_type_id', $typeId)
                                                ->where('year', now()->year)
                                                ->first();
                                            if ($allocation) {
                                                return __('booking::time_off.fields.remaining_days', ['days' => number_format($allocation->remaining_days, 1)]);
                                            }
                                        }
                                        return null;
                                    }),
                            ]),

                        Forms\Components\Select::make('branch_id')
                            ->label(__('booking::time_off.fields.branch'))
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload()
                            ->disabled()
                            ->dehydrated()
                            ->helperText(__('booking::time_off.fields.branch_auto')),
                    ]),

                Forms\Components\Section::make(__('booking::time_off.sections.period'))
                    ->schema([
                        Forms\Components\Toggle::make('is_full_day')
                            ->label(__('booking::time_off.fields.is_full_day'))
                            ->default(true)
                            ->live(),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\DatePicker::make('start_date')
                                    ->label(__('booking::time_off.fields.start_date'))
                                    ->native(false)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                        $endDate = $get('end_date');
                                        if ($state && $endDate) {
                                            $days = \Carbon\Carbon::parse($state)->diffInDays(\Carbon\Carbon::parse($endDate)) + 1;
                                            $set('days_requested', $days);
                                        }
                                    }),

                                Forms\Components\DatePicker::make('end_date')
                                    ->label(__('booking::time_off.fields.end_date'))
                                    ->native(false)
                                    ->required()
                                    ->afterOrEqual('start_date')
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                        $startDate = $get('start_date');
                                        if ($startDate && $state) {
                                            $days = \Carbon\Carbon::parse($startDate)->diffInDays(\Carbon\Carbon::parse($state)) + 1;
                                            $set('days_requested', $days);
                                        }
                                    }),

                                Forms\Components\TextInput::make('days_requested')
                                    ->label(__('booking::time_off.fields.days_requested'))
                                    ->numeric()
                                    ->step(0.5)
                                    ->minValue(0.5)
                                    ->visible(fn (Forms\Get $get) => $get('time_off_type_id'))
                                    ->helperText(__('booking::time_off.fields.days_requested_help')),
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
                Tables\Columns\TextColumn::make('practitioner.first_name')
                    ->label(__('booking::time_off.fields.staff'))
                    ->formatStateUsing(fn ($record) => $record->practitioner?->full_name)
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('timeOffType.name')
                    ->label(__('booking::time_off.fields.time_off_type'))
                    ->badge()
                    ->color(fn ($record) => $record->timeOffType?->color ?? 'gray')
                    ->placeholder(fn ($record) => PractitionerTimeOff::TYPES[$record->type] ?? $record->type)
                    ->sortable(),

                Tables\Columns\TextColumn::make('days_requested')
                    ->label(__('booking::time_off.fields.days_requested'))
                    ->numeric(decimalPlaces: 1)
                    ->placeholder('-')
                    ->toggleable(),

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
                    ->label(__('booking::time_off.fields.staff'))
                    ->options(fn () => User::query()->get()->pluck('full_name', 'id'))
                    ->preload()
                    ->searchable(),

                Tables\Filters\SelectFilter::make('time_off_type_id')
                    ->label(__('booking::time_off.fields.time_off_type'))
                    ->relationship('timeOffType', 'name')
                    ->searchable()
                    ->preload(),

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

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),

                    Tables\Actions\Action::make('cancel')
                        ->label(__('booking::time_off.actions.cancel'))
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->visible(fn (PractitionerTimeOff $record): bool => $record->isApproved() || $record->isPending())
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
            ->with(['practitioner', 'branch', 'approvedBy', 'timeOffType']);
    }
}
