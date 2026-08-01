<?php

namespace Modules\Booking\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Booking\Filament\Resources\TimeOffAllocationResource\Pages;
use Modules\Booking\Models\TimeOffAllocation;
use Modules\Booking\Models\TimeOffType;
use Modules\Staff\Models\StaffProfile;

class TimeOffAllocationResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = TimeOffAllocation::class;

    protected static ?string $moduleCode = 'booking';

    protected static ?string $permissionKey = 'time_off_allocations';

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'HR';

    public static function getNavigationParentItem(): ?string
    {
        return __('core::core.nav_folders.time_off');
    }

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('booking::time_off.allocations.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('booking::time_off.allocations.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('booking::time_off.allocations.plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('booking::time_off.allocations.sections.allocation'))
                    ->schema([
                        Forms\Components\Select::make('staff_profile_id')
                            ->label(__('booking::time_off.allocations.fields.practitioner'))
                            ->options(fn () => static::staffProfileOptions())
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('time_off_type_id')
                            ->label(__('booking::time_off.allocations.fields.type'))
                            ->relationship('timeOffType', 'name', fn ($query) => $query->active()->ordered())
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->translated_name)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if (! $state) {
                                    return;
                                }

                                $type = TimeOffType::find($state);
                                if (! $type) {
                                    return;
                                }

                                $set('allocated_days', $type->getEffectiveDefaultAllocation());

                                // Seed a sensible date range if empty.
                                if (! $get('date_from') || ! $get('date_to')) {
                                    [$from, $to] = TimeOffAllocation::deriveDateRange($type, now());
                                    $set('date_from', $from->toDateString());
                                    $set('date_to', $to->toDateString());
                                }
                            }),

                        Forms\Components\DatePicker::make('date_from')
                            ->label(__('booking::time_off.allocations.fields.date_from'))
                            ->native(false)
                            ->required()
                            ->default(now()->startOfYear()),

                        Forms\Components\DatePicker::make('date_to')
                            ->label(__('booking::time_off.allocations.fields.date_to'))
                            ->native(false)
                            ->required()
                            ->after('date_from')
                            ->default(now()->endOfYear()),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(fn (Forms\Get $get) => $get('time_off_type_id') && TimeOffType::find($get('time_off_type_id'))?->isHourBased()
                    ? __('booking::time_off.allocations.sections.hours')
                    : __('booking::time_off.allocations.sections.days'))
                    ->schema([
                        Forms\Components\TextInput::make('allocated_days')
                            ->label(fn (Forms\Get $get) => $get('time_off_type_id') && TimeOffType::find($get('time_off_type_id'))?->isHourBased()
                                ? __('booking::time_off.allocations.fields.allocated_hours')
                                : __('booking::time_off.allocations.fields.allocated_days'))
                            ->numeric()
                            ->default(0)
                            ->step(0.5)
                            ->required(),

                        Forms\Components\TextInput::make('carried_over_days')
                            ->label(fn (Forms\Get $get) => $get('time_off_type_id') && TimeOffType::find($get('time_off_type_id'))?->isHourBased()
                                ? __('booking::time_off.allocations.fields.carried_over_hours')
                                : __('booking::time_off.allocations.fields.carried_over'))
                            ->numeric()
                            ->default(0)
                            ->step(0.5)
                            ->helperText(__('booking::time_off.allocations.help.carried_over')),

                        Forms\Components\TextInput::make('used_days')
                            ->label(fn (Forms\Get $get) => $get('time_off_type_id') && TimeOffType::find($get('time_off_type_id'))?->isHourBased()
                                ? __('booking::time_off.allocations.fields.used_hours')
                                : __('booking::time_off.allocations.fields.used_days'))
                            ->numeric()
                            ->default(0)
                            ->step(0.5)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText(__('booking::time_off.allocations.help.used_days')),

                        Forms\Components\Placeholder::make('remaining_days')
                            ->label(fn (Forms\Get $get) => $get('time_off_type_id') && TimeOffType::find($get('time_off_type_id'))?->isHourBased()
                                ? __('booking::time_off.allocations.fields.remaining_hours')
                                : __('booking::time_off.allocations.fields.remaining_days'))
                            ->content(fn ($record) => $record ? $record->display_value : '-'),
                    ])
                    ->columns(4),

                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label(__('booking::time_off.allocations.fields.notes'))
                            ->rows(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('staffProfile.user.first_name')
                    ->label(__('booking::time_off.allocations.fields.practitioner'))
                    ->formatStateUsing(fn ($record) => $record->staffProfile?->user?->full_name)
                    ->searchable(['users.first_name', 'users.last_name'])
                    ->sortable(['users.first_name', 'users.last_name']),

                Tables\Columns\TextColumn::make('timeOffType.name')
                    ->label(__('booking::time_off.allocations.fields.type'))
                    ->badge()
                    ->color(fn ($record) => $record->timeOffType?->color ?? 'gray')
                    ->formatStateUsing(fn ($record) => $record->timeOffType?->translated_name)
                    ->searchable(['time_off_types.name'])
                    ->sortable(['time_off_types.name']),

                Tables\Columns\TextColumn::make('period_label')
                    ->label(__('booking::time_off.allocations.fields.period'))
                    ->sortable(['date_from']),

                Tables\Columns\TextColumn::make('allocated_days')
                    ->label(__('booking::time_off.allocations.fields.allocated'))
                    ->formatStateUsing(fn ($record) => $record->timeOffType?->formatValue($record->allocated_days) ?? number_format($record->allocated_days, 1))
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('carried_over_days')
                    ->label(__('booking::time_off.allocations.fields.carried_over'))
                    ->formatStateUsing(fn ($record) => $record->timeOffType?->formatValue($record->carried_over_days) ?? number_format($record->carried_over_days, 1))
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('used_days')
                    ->label(__('booking::time_off.allocations.fields.used'))
                    ->formatStateUsing(fn ($record) => $record->timeOffType?->formatValue($record->used_days) ?? number_format($record->used_days, 1))
                    ->alignEnd()
                    ->color('danger'),

                Tables\Columns\TextColumn::make('remaining_days')
                    ->label(__('booking::time_off.allocations.fields.remaining'))
                    ->formatStateUsing(fn ($record) => $record->display_value)
                    ->alignEnd()
                    ->color(fn ($record) => $record->remaining_days > 0 ? 'success' : 'danger')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('notes')
                    ->label(__('booking::time_off.allocations.fields.notes'))
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('date_from', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('staff_profile_id')
                    ->label(__('booking::time_off.allocations.fields.practitioner'))
                    ->options(fn () => static::staffProfileOptions())
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('time_off_type_id')
                    ->label(__('booking::time_off.allocations.fields.type'))
                    ->relationship('timeOffType', 'name', fn ($query) => $query->active()->ordered())
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->translated_name)
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('covers_today')
                    ->label(__('booking::time_off.allocations.filters.current'))
                    ->default()
                    ->query(fn ($query) => $query->coveringDate(now())),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTimeOffAllocations::route('/'),
            'create' => Pages\CreateTimeOffAllocation::route('/create'),
            'edit' => Pages\EditTimeOffAllocation::route('/{record}/edit'),
        ];
    }

    protected static function staffProfileOptions(): array
    {
        return StaffProfile::query()
            ->with('user:id,first_name,last_name')
            ->get()
            ->mapWithKeys(fn (StaffProfile $sp) => [$sp->id => $sp->user?->full_name ?? "#{$sp->id}"])
            ->toArray();
    }
}
