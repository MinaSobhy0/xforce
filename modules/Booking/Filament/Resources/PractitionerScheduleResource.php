<?php

namespace Modules\Booking\Filament\Resources;

use Modules\Booking\Filament\Resources\PractitionerScheduleResource\Pages;
use Modules\Booking\Models\PractitionerSchedule;
use Modules\Auth\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PractitionerScheduleResource extends Resource
{
    protected static ?string $model = PractitionerSchedule::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 12;

    public static function getNavigationLabel(): string
    {
        return __('booking::schedules.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('booking::schedules.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('booking::schedules.plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('booking::schedules.sections.practitioner'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('user_id')
                                    ->label(__('booking::schedules.fields.practitioner'))
                                    ->relationship('practitioner', 'first_name')
                                    ->getOptionLabelFromRecordUsing(fn (User $record) => $record->full_name)
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Forms\Components\Select::make('branch_id')
                                    ->label(__('booking::schedules.fields.branch'))
                                    ->relationship('branch', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->default(fn () => current_branch_id())
                                    ->disabled(fn () => current_branch_id() !== null)
                                    ->dehydrated(),
                            ]),
                    ]),

                Forms\Components\Section::make(__('booking::schedules.sections.schedule'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('day_of_week')
                                    ->label(__('booking::schedules.fields.day_of_week'))
                                    ->options(PractitionerSchedule::DAYS)
                                    ->required(),

                                Forms\Components\Toggle::make('is_available')
                                    ->label(__('booking::schedules.fields.is_available'))
                                    ->default(true)
                                    ->live(),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TimePicker::make('start_time')
                                    ->label(__('booking::schedules.fields.start_time'))
                                    ->seconds(false)
                                    ->required()
                                    ->visible(fn (Forms\Get $get) => $get('is_available')),

                                Forms\Components\TimePicker::make('end_time')
                                    ->label(__('booking::schedules.fields.end_time'))
                                    ->seconds(false)
                                    ->required()
                                    ->visible(fn (Forms\Get $get) => $get('is_available')),
                            ]),

                        Forms\Components\Fieldset::make(__('booking::schedules.sections.break'))
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TimePicker::make('break_start')
                                            ->label(__('booking::schedules.fields.break_start'))
                                            ->seconds(false),

                                        Forms\Components\TimePicker::make('break_end')
                                            ->label(__('booking::schedules.fields.break_end'))
                                            ->seconds(false),
                                    ]),
                            ])
                            ->visible(fn (Forms\Get $get) => $get('is_available')),

                        Forms\Components\TextInput::make('max_appointments')
                            ->label(__('booking::schedules.fields.max_appointments'))
                            ->numeric()
                            ->minValue(1)
                            ->helperText(__('booking::schedules.fields.max_appointments_help'))
                            ->visible(fn (Forms\Get $get) => $get('is_available')),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('booking::schedules.fields.notes'))
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
                    ->label(__('booking::schedules.fields.practitioner'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('booking::schedules.fields.branch'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('day_name')
                    ->label(__('booking::schedules.fields.day_of_week'))
                    ->sortable(['day_of_week']),

                Tables\Columns\IconColumn::make('is_available')
                    ->label(__('booking::schedules.fields.is_available'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('formatted_hours')
                    ->label(__('booking::schedules.fields.hours')),

                Tables\Columns\TextColumn::make('formatted_break')
                    ->label(__('booking::schedules.fields.break'))
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('max_appointments')
                    ->label(__('booking::schedules.fields.max_appointments'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label(__('booking::schedules.fields.practitioner'))
                    ->relationship('practitioner', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn (User $record) => $record->full_name)
                    ->preload()
                    ->searchable(),

                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('booking::schedules.fields.branch'))
                    ->relationship('branch', 'name')
                    ->preload()
                    ->searchable(),

                Tables\Filters\SelectFilter::make('day_of_week')
                    ->label(__('booking::schedules.fields.day_of_week'))
                    ->options(PractitionerSchedule::DAYS),

                Tables\Filters\TernaryFilter::make('is_available')
                    ->label(__('booking::schedules.fields.is_available')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('day_of_week');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPractitionerSchedules::route('/'),
            'create' => Pages\CreatePractitionerSchedule::route('/create'),
            'edit' => Pages\EditPractitionerSchedule::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['practitioner', 'branch']);
    }
}
