<?php

namespace Modules\TreatmentPlans\Filament\Resources\TreatmentPlanResource\RelationManagers;

use Modules\TreatmentPlans\Models\TreatmentPlanAppointment;
use Modules\Booking\Models\Appointment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AppointmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('treatment_plans::treatment_plans.plan_appointments.title');
    }

    public function table(Table $table): Table
    {
        // We need to show appointments through items
        return $table
            ->query(function () {
                return TreatmentPlanAppointment::query()
                    ->whereHas('item', function (Builder $query) {
                        $query->where('treatment_plan_id', $this->getOwnerRecord()->id);
                    })
                    ->with(['item.service', 'appointment.practitioner', 'appointment.room']);
            })
            ->columns([
                Tables\Columns\TextColumn::make('session_display')
                    ->label(__('treatment_plans::treatment_plans.fields.session_number'))
                    ->getStateUsing(fn (TreatmentPlanAppointment $record) => $record->session_display),

                Tables\Columns\TextColumn::make('item.service.translated_name')
                    ->label(__('treatment_plans::treatment_plans.fields.service'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('appointment.date')
                    ->label(__('treatment_plans::treatment_plans.fields.date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('appointment.formatted_time')
                    ->label(__('treatment_plans::treatment_plans.fields.time')),

                Tables\Columns\TextColumn::make('appointment.practitioner.name')
                    ->label(__('treatment_plans::treatment_plans.fields.practitioner')),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('treatment_plans::treatment_plans.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => Appointment::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => Appointment::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('appointment.room.name')
                    ->label('Room')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('treatment_plans::treatment_plans.fields.status'))
                    ->options(Appointment::STATUSES),

                Tables\Filters\Filter::make('upcoming')
                    ->label('Upcoming')
                    ->query(fn (Builder $query) => $query->whereHas('appointment', fn ($q) => $q->where('date', '>=', today())))
                    ->toggle(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('book')
                    ->label(__('treatment_plans::treatment_plans.plan_appointments.add'))
                    ->icon('heroicon-o-calendar-days')
                    ->color('primary')
                    ->visible(fn () => $this->getOwnerRecord()->isActive() && $this->getOwnerRecord()->items_needing_scheduling->count() > 0)
                    ->url(fn () => route('filament.tenant.pages.create-booking', [
                        'tenant' => current_tenant_id(),
                        'booking_type' => 'treatment_plan',
                        'treatment_plan_id' => $this->getOwnerRecord()->id,
                    ])),
            ])
            ->actions([
                Tables\Actions\Action::make('view_appointment')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (TreatmentPlanAppointment $record) => route('filament.tenant.resources.appointments.view', [
                        'tenant' => current_tenant_id(),
                        'record' => $record->appointment_id,
                    ])),
            ])
            ->bulkActions([])
            ->defaultSort('appointment.date', 'asc')
            ->emptyStateHeading(__('treatment_plans::treatment_plans.plan_appointments.empty'))
            ->emptyStateDescription('Book appointments to track progress on this treatment plan.')
            ->emptyStateIcon('heroicon-o-calendar');
    }

    public function form(Form $form): Form
    {
        // This relation manager is read-only - appointments are created through the booking flow
        return $form->schema([]);
    }
}
