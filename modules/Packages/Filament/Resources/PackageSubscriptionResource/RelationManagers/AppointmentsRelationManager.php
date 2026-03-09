<?php

namespace Modules\Packages\Filament\Resources\PackageSubscriptionResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Booking\Models\Appointment;

class AppointmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'appointments';

    protected static ?string $title = 'Appointments';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('packages::packages.subscriptions.relations.appointments');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label(__('packages::packages.subscriptions.fields.date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_time')
                    ->label(__('packages::packages.subscriptions.fields.time'))
                    ->time('H:i'),

                Tables\Columns\TextColumn::make('service.translated_name')
                    ->label(__('packages::packages.fields.service'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('practitioner.user.name')
                    ->label(__('packages::packages.subscriptions.fields.practitioner'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('packages::packages.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => Appointment::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => Appointment::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\IconColumn::make('is_package_session')
                    ->label(__('packages::packages.subscriptions.fields.is_package_session'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('packages::packages.fields.status'))
                    ->options(Appointment::STATUSES)
                    ->multiple(),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label(__('packages::packages.subscriptions.actions.view_appointment'))
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => route('filament.tenant.resources.appointments.view', ['record' => $record->id])),
            ])
            ->bulkActions([])
            ->defaultSort('date', 'desc');
    }
}
