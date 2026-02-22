<?php

namespace Modules\Patients\Filament\Resources\PatientResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Booking\Models\Appointment;

class AppointmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'appointments';

    protected static ?string $recordTitleAttribute = 'code';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('booking::appointments.fields.code'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('date')
                    ->label(__('booking::appointments.fields.date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_time')
                    ->label(__('booking::appointments.fields.time'))
                    ->time('H:i'),

                Tables\Columns\TextColumn::make('treatment.name')
                    ->label(__('booking::appointments.fields.treatment'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '') : $state)
                    ->limit(25),

                Tables\Columns\TextColumn::make('practitioner.name')
                    ->label(__('booking::appointments.fields.practitioner'))
                    ->limit(20),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('booking::appointments.fields.status'))
                    ->colors([
                        'gray' => Appointment::STATUS_SCHEDULED,
                        'info' => Appointment::STATUS_CONFIRMED,
                        'warning' => Appointment::STATUS_CHECKED_IN,
                        'primary' => Appointment::STATUS_IN_PROGRESS,
                        'success' => Appointment::STATUS_COMPLETED,
                        'danger' => fn ($state) => in_array($state, [
                            Appointment::STATUS_CANCELLED,
                            Appointment::STATUS_NO_SHOW,
                        ]),
                    ]),

                Tables\Columns\TextColumn::make('price_minor')
                    ->label(__('booking::appointments.fields.price'))
                    ->money(current_currency(), divideBy: 100)
                    ->alignEnd(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Appointment::STATUS_SCHEDULED => __('booking::appointments.statuses.scheduled'),
                        Appointment::STATUS_CONFIRMED => __('booking::appointments.statuses.confirmed'),
                        Appointment::STATUS_COMPLETED => __('booking::appointments.statuses.completed'),
                        Appointment::STATUS_CANCELLED => __('booking::appointments.statuses.cancelled'),
                    ]),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn ($record) => route('filament.tenant.resources.appointments.view', $record)),
            ])
            ->bulkActions([])
            ->defaultSort('date', 'desc');
    }

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('booking::appointments.plural');
    }
}
