<?php

namespace Modules\Booking\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Auth\Models\User;
use Modules\Booking\Filament\Resources\TimeOffAllocationResource\Pages;
use Modules\Booking\Models\TimeOffAllocation;
use Modules\Booking\Models\TimeOffType;

class TimeOffAllocationResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = TimeOffAllocation::class;

    protected static ?string $moduleCode = 'booking';

    protected static ?string $permissionKey = 'time_off_allocations';

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 14;

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
                        Forms\Components\Select::make('user_id')
                            ->label(__('booking::time_off.allocations.fields.practitioner'))
                            ->options(fn () => User::query()
                                ->get()
                                ->pluck('full_name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('time_off_type_id')
                            ->label(__('booking::time_off.allocations.fields.type'))
                            ->relationship('timeOffType', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $type = TimeOffType::find($state);
                                    if ($type) {
                                        $set('allocated_days', $type->default_days_per_year);
                                    }
                                }
                            }),

                        Forms\Components\TextInput::make('year')
                            ->label(__('booking::time_off.allocations.fields.year'))
                            ->numeric()
                            ->default(now()->year)
                            ->required()
                            ->minValue(2020)
                            ->maxValue(2050),
                    ])
                    ->columns(3),

                Forms\Components\Section::make(__('booking::time_off.allocations.sections.days'))
                    ->schema([
                        Forms\Components\TextInput::make('allocated_days')
                            ->label(__('booking::time_off.allocations.fields.allocated_days'))
                            ->numeric()
                            ->default(0)
                            ->step(0.5)
                            ->required(),

                        Forms\Components\TextInput::make('carried_over_days')
                            ->label(__('booking::time_off.allocations.fields.carried_over'))
                            ->numeric()
                            ->default(0)
                            ->step(0.5)
                            ->helperText(__('booking::time_off.allocations.help.carried_over')),

                        Forms\Components\TextInput::make('used_days')
                            ->label(__('booking::time_off.allocations.fields.used_days'))
                            ->numeric()
                            ->default(0)
                            ->step(0.5)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText(__('booking::time_off.allocations.help.used_days')),

                        Forms\Components\Placeholder::make('remaining_days')
                            ->label(__('booking::time_off.allocations.fields.remaining_days'))
                            ->content(fn ($record) => $record ? number_format($record->remaining_days, 1) : '-'),
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
                Tables\Columns\TextColumn::make('user.first_name')
                    ->label(__('booking::time_off.allocations.fields.practitioner'))
                    ->formatStateUsing(fn ($record) => $record->user?->full_name)
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('timeOffType.name')
                    ->label(__('booking::time_off.allocations.fields.type'))
                    ->badge()
                    ->color(fn ($record) => $record->timeOffType?->color ?? 'gray')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('year')
                    ->label(__('booking::time_off.allocations.fields.year'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('allocated_days')
                    ->label(__('booking::time_off.allocations.fields.allocated_days'))
                    ->numeric(decimalPlaces: 1)
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('carried_over_days')
                    ->label(__('booking::time_off.allocations.fields.carried_over'))
                    ->numeric(decimalPlaces: 1)
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('used_days')
                    ->label(__('booking::time_off.allocations.fields.used_days'))
                    ->numeric(decimalPlaces: 1)
                    ->alignEnd()
                    ->color('danger'),

                Tables\Columns\TextColumn::make('remaining_days')
                    ->label(__('booking::time_off.allocations.fields.remaining_days'))
                    ->numeric(decimalPlaces: 1)
                    ->alignEnd()
                    ->color(fn ($record) => $record->remaining_days > 0 ? 'success' : 'danger')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('notes')
                    ->label(__('booking::time_off.allocations.fields.notes'))
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('year', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label(__('booking::time_off.allocations.fields.practitioner'))
                    ->options(fn () => User::query()
                        ->get()
                        ->pluck('full_name', 'id'))
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('time_off_type_id')
                    ->label(__('booking::time_off.allocations.fields.type'))
                    ->relationship('timeOffType', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('year')
                    ->label(__('booking::time_off.allocations.fields.year'))
                    ->options(fn () => collect(range(now()->year - 2, now()->year + 1))
                        ->mapWithKeys(fn ($year) => [$year => $year])
                        ->toArray())
                    ->default(now()->year),
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
}
