<?php

namespace Modules\Booking\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Booking\Filament\Resources\BookingBlackoutDateResource\Pages;
use Modules\Booking\Models\BookingBlackoutDate;
use Modules\Core\Models\Branch;
use App\Traits\ChecksResourcePermissions;

class BookingBlackoutDateResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = BookingBlackoutDate::class;
    protected static ?string $moduleCode = 'booking';
    protected static ?string $permissionKey = 'booking_blackouts';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 52;

    public static function getNavigationLabel(): string
    {
        return __('booking::config.blackout_dates');
    }

    public static function getModelLabel(): string
    {
        return __('booking::config.blackout_date');
    }

    public static function getPluralModelLabel(): string
    {
        return __('booking::config.blackout_dates');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('booking::config.blackout_details'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('booking::config.blackout_name'))
                            ->placeholder(__('booking::config.blackout_name_placeholder'))
                            ->required()
                            ->maxLength(100),

                        Forms\Components\Select::make('branch_id')
                            ->label(__('booking::config.branch'))
                            ->options(fn () => Branch::pluck('name', 'id'))
                            ->placeholder(__('booking::config.all_branches'))
                            ->searchable()
                            ->nullable(),

                        Forms\Components\DatePicker::make('start_date')
                            ->label(__('booking::config.start_date'))
                            ->required()
                            ->native(false),

                        Forms\Components\DatePicker::make('end_date')
                            ->label(__('booking::config.end_date'))
                            ->required()
                            ->native(false)
                            ->afterOrEqual('start_date'),

                        Forms\Components\Textarea::make('reason')
                            ->label(__('booking::config.reason'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('booking::config.recurrence'))
                    ->schema([
                        Forms\Components\Toggle::make('is_recurring')
                            ->label(__('booking::config.is_recurring'))
                            ->helperText(__('booking::config.recurring_help'))
                            ->live()
                            ->default(false),

                        Forms\Components\Select::make('recurrence_type')
                            ->label(__('booking::config.recurrence_type'))
                            ->options(BookingBlackoutDate::RECURRENCE_TYPES)
                            ->visible(fn (Get $get) => $get('is_recurring'))
                            ->required(fn (Get $get) => $get('is_recurring')),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('booking::config.booking_scope'))
                    ->schema([
                        Forms\Components\Toggle::make('affects_online_booking')
                            ->label(__('booking::config.affects_online'))
                            ->helperText(__('booking::config.affects_online_help'))
                            ->default(true),

                        Forms\Components\Toggle::make('affects_staff_booking')
                            ->label(__('booking::config.affects_staff'))
                            ->helperText(__('booking::config.affects_staff_help'))
                            ->default(false),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('booking::config.active'))
                            ->default(true),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('booking::config.blackout_name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label(__('booking::config.dates'))
                    ->formatStateUsing(fn (BookingBlackoutDate $record) => $record->date_range_display)
                    ->sortable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('booking::config.branch'))
                    ->placeholder(__('booking::config.all_branches'))
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_recurring')
                    ->label(__('booking::config.recurring'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('recurrence_type')
                    ->label(__('booking::config.recurrence_type'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? BookingBlackoutDate::RECURRENCE_TYPES[$state] : null)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('affects_online_booking')
                    ->label(__('booking::config.online'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('affects_staff_booking')
                    ->label(__('booking::config.staff'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('booking::config.active'))
                    ->boolean(),
            ])
            ->defaultSort('start_date', 'asc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_recurring')
                    ->label(__('booking::config.recurring')),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('booking::config.active')),

                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('booking::config.branch'))
                    ->options(fn () => Branch::pluck('name', 'id'))
                    ->placeholder(__('booking::config.all_branches')),

                Tables\Filters\Filter::make('upcoming')
                    ->label(__('booking::config.upcoming_only'))
                    ->query(fn ($query) => $query->where('end_date', '>=', now()->startOfDay()))
                    ->default(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle')
                    ->icon(fn (BookingBlackoutDate $record) => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn (BookingBlackoutDate $record) => $record->is_active ? 'warning' : 'success')
                    ->action(fn (BookingBlackoutDate $record) => $record->update(['is_active' => !$record->is_active]))
                    ->requiresConfirmation(),
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
            'index' => Pages\ListBookingBlackoutDates::route('/'),
            'create' => Pages\CreateBookingBlackoutDate::route('/create'),
            'edit' => Pages\EditBookingBlackoutDate::route('/{record}/edit'),
        ];
    }
}
