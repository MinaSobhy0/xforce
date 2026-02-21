<?php

namespace Modules\Booking\Filament\Resources;

use Modules\Booking\Filament\Resources\WaitlistResource\Pages;
use Modules\Booking\Models\Waitlist;
use Modules\Booking\Models\PractitionerSchedule;
use Modules\Auth\Models\User;
use Modules\Patients\Models\Patient;
use Modules\Treatments\Models\Treatment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WaitlistResource extends Resource
{
    protected static ?string $model = Waitlist::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Booking';

    protected static ?int $navigationSort = 14;

    public static function getNavigationLabel(): string
    {
        return __('booking::waitlist.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('booking::waitlist.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('booking::waitlist.plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('booking::waitlist.sections.patient'))
                    ->schema([
                        Forms\Components\Select::make('patient_id')
                            ->label(__('booking::waitlist.fields.patient'))
                            ->relationship('patient', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn (Patient $record) => $record->full_name . ' (' . $record->code . ')')
                            ->searchable(['first_name', 'last_name', 'phone', 'code'])
                            ->preload()
                            ->required(),
                    ]),

                Forms\Components\Section::make(__('booking::waitlist.sections.treatment'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('treatment_id')
                                    ->label(__('booking::waitlist.fields.treatment'))
                                    ->relationship('treatment', 'code')
                                    ->getOptionLabelFromRecordUsing(fn (Treatment $record) => $record->translated_name)
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Forms\Components\Select::make('branch_id')
                                    ->label(__('booking::waitlist.fields.branch'))
                                    ->relationship('branch', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->default(fn () => current_branch_id())
                                    ->disabled(fn () => current_branch_id() !== null)
                                    ->dehydrated(),
                            ]),

                        Forms\Components\Select::make('practitioner_id')
                            ->label(__('booking::waitlist.fields.practitioner'))
                            ->relationship('practitioner', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn (User $record) => $record->full_name)
                            ->searchable()
                            ->preload()
                            ->helperText(__('booking::waitlist.fields.practitioner_help')),
                    ]),

                Forms\Components\Section::make(__('booking::waitlist.sections.preferences'))
                    ->schema([
                        Forms\Components\CheckboxList::make('preferred_days')
                            ->label(__('booking::waitlist.fields.preferred_days'))
                            ->options(PractitionerSchedule::DAYS)
                            ->columns(7),

                        Forms\Components\CheckboxList::make('preferred_times')
                            ->label(__('booking::waitlist.fields.preferred_times'))
                            ->options(Waitlist::PREFERRED_TIMES)
                            ->columns(4),

                        Forms\Components\Select::make('priority')
                            ->label(__('booking::waitlist.fields.priority'))
                            ->options(Waitlist::PRIORITIES)
                            ->default(Waitlist::PRIORITY_NORMAL)
                            ->required(),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label(__('booking::waitlist.fields.expires_at'))
                            ->helperText(__('booking::waitlist.fields.expires_at_help')),
                    ]),

                Forms\Components\Section::make(__('booking::waitlist.sections.notes'))
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label(__('booking::waitlist.fields.notes'))
                            ->rows(3)
                            ->maxLength(1000),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label(__('booking::waitlist.fields.patient'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('patient.phone')
                    ->label(__('patients::patients.fields.phone'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('treatment.translated_name')
                    ->label(__('booking::waitlist.fields.treatment'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('booking::waitlist.fields.branch'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('practitioner.full_name')
                    ->label(__('booking::waitlist.fields.practitioner'))
                    ->placeholder(__('booking::waitlist.any_practitioner'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\BadgeColumn::make('priority')
                    ->label(__('booking::waitlist.fields.priority'))
                    ->colors([
                        'gray' => Waitlist::PRIORITY_LOW,
                        'info' => Waitlist::PRIORITY_NORMAL,
                        'warning' => Waitlist::PRIORITY_HIGH,
                        'danger' => Waitlist::PRIORITY_URGENT,
                    ])
                    ->formatStateUsing(fn (int $state): string => Waitlist::PRIORITIES[$state] ?? 'Normal'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('booking::waitlist.fields.status'))
                    ->colors([
                        'warning' => Waitlist::STATUS_WAITING,
                        'info' => Waitlist::STATUS_NOTIFIED,
                        'success' => Waitlist::STATUS_BOOKED,
                        'gray' => fn ($state) => in_array($state, [Waitlist::STATUS_EXPIRED, Waitlist::STATUS_CANCELLED]),
                    ])
                    ->formatStateUsing(fn (string $state): string => Waitlist::STATUSES[$state] ?? $state),

                Tables\Columns\TextColumn::make('formatted_preferred_days')
                    ->label(__('booking::waitlist.fields.preferred_days'))
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('formatted_preferred_times')
                    ->label(__('booking::waitlist.fields.preferred_times'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('booking::waitlist.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('notified_at')
                    ->label(__('booking::waitlist.fields.notified_at'))
                    ->dateTime()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('booking::waitlist.fields.status'))
                    ->options(Waitlist::STATUSES)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('priority')
                    ->label(__('booking::waitlist.fields.priority'))
                    ->options(Waitlist::PRIORITIES),

                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('booking::waitlist.fields.branch'))
                    ->relationship('branch', 'name')
                    ->preload()
                    ->searchable(),

                Tables\Filters\SelectFilter::make('treatment_id')
                    ->label(__('booking::waitlist.fields.treatment'))
                    ->relationship('treatment', 'code')
                    ->getOptionLabelFromRecordUsing(fn (Treatment $record) => $record->translated_name)
                    ->preload()
                    ->searchable(),

                Tables\Filters\Filter::make('active')
                    ->label(__('booking::waitlist.filters.active_only'))
                    ->query(fn (Builder $query): Builder => $query->active())
                    ->toggle()
                    ->default(true),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),

                    Tables\Actions\Action::make('notify')
                        ->label(__('booking::waitlist.actions.notify'))
                        ->icon('heroicon-o-bell')
                        ->color('info')
                        ->requiresConfirmation()
                        ->visible(fn (Waitlist $record): bool => $record->isWaiting())
                        ->action(fn (Waitlist $record) => $record->markNotified()),

                    Tables\Actions\Action::make('book')
                        ->label(__('booking::waitlist.actions.book'))
                        ->icon('heroicon-o-calendar')
                        ->color('success')
                        ->url(fn (Waitlist $record): string => route('filament.tenant.resources.appointments.create', [
                            'patient_id' => $record->patient_id,
                            'treatment_id' => $record->treatment_id,
                            'branch_id' => $record->branch_id,
                        ]))
                        ->visible(fn (Waitlist $record): bool => $record->isActive()),

                    Tables\Actions\Action::make('mark_booked')
                        ->label(__('booking::waitlist.actions.mark_booked'))
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (Waitlist $record): bool => $record->isActive())
                        ->action(fn (Waitlist $record) => $record->markBooked()),

                    Tables\Actions\Action::make('cancel')
                        ->label(__('booking::waitlist.actions.cancel'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (Waitlist $record): bool => $record->isActive())
                        ->action(fn (Waitlist $record) => $record->cancel()),

                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('priority', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWaitlists::route('/'),
            'create' => Pages\CreateWaitlist::route('/create'),
            'edit' => Pages\EditWaitlist::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['patient', 'treatment', 'branch', 'practitioner']);
    }
}
