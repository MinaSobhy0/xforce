<?php

namespace Modules\Treatments\Filament\Resources\TreatmentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Booking\Models\Appointment;
use Illuminate\Support\Facades\DB;

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

                Tables\Columns\TextColumn::make('patient.name')
                    ->label(__('booking::appointments.fields.patient'))
                    ->searchable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('practitioner.name')
                    ->label(__('booking::appointments.fields.practitioner'))
                    ->limit(20),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('booking::appointments.fields.branch'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '') : $state),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('booking::appointments.fields.status'))
                    ->colors([
                        'gray' => Appointment::STATUS_SCHEDULED,
                        'info' => Appointment::STATUS_CONFIRMED,
                        'success' => Appointment::STATUS_COMPLETED,
                        'danger' => Appointment::STATUS_CANCELLED,
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Appointment::STATUS_COMPLETED => __('booking::appointments.statuses.completed'),
                        Appointment::STATUS_SCHEDULED => __('booking::appointments.statuses.scheduled'),
                        Appointment::STATUS_CANCELLED => __('booking::appointments.statuses.cancelled'),
                    ]),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn ($record) => route('filament.tenant.resources.appointments.view', $record)),
            ])
            ->bulkActions([])
            ->defaultSort('date', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->limit(50));
    }

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('booking::appointments.plural');
    }
}
