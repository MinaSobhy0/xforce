<?php

namespace Modules\Patients\Filament\Resources\PatientResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\TreatmentPlans\Models\TreatmentPlan;

class TreatmentPlansRelationManager extends RelationManager
{
    protected static string $relationship = 'treatmentPlans';

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
                    ->label(__('treatment_plans::treatment_plans.fields.code'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('treatment_plans::treatment_plans.fields.name'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '') : $state)
                    ->limit(30),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('treatment_plans::treatment_plans.fields.status'))
                    ->formatStateUsing(fn ($state) => TreatmentPlan::STATUSES[$state] ?? $state)
                    ->colors([
                        'gray' => 'draft',
                        'success' => 'active',
                        'warning' => 'paused',
                        'info' => 'completed',
                        'danger' => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('progress_percentage')
                    ->label(__('treatment_plans::treatment_plans.progress.overall'))
                    ->getStateUsing(fn (TreatmentPlan $record) => $record->progress_percentage)
                    ->formatStateUsing(fn ($state) => $state . '%')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state >= 100 => 'success',
                        $state >= 50 => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('sessions')
                    ->label(__('treatment_plans::treatment_plans.fields.completed_sessions'))
                    ->getStateUsing(fn (TreatmentPlan $record) =>
                        $record->total_completed_sessions . '/' . $record->total_recommended_sessions
                    ),

                Tables\Columns\TextColumn::make('start_date')
                    ->label(__('treatment_plans::treatment_plans.fields.start_date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('treatment_plans::treatment_plans.fields.created_at'))
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('treatment_plans::treatment_plans.fields.status'))
                    ->options(TreatmentPlan::STATUSES),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create')
                    ->label(__('treatment_plans::treatment_plans.actions.create'))
                    ->icon('heroicon-o-plus')
                    ->url(fn () => route('filament.tenant.resources.treatment-plans.create', [
                        'tenant' => current_tenant_id(),
                        'patient_id' => $this->getOwnerRecord()->id,
                    ])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (TreatmentPlan $record) => route('filament.tenant.resources.treatment-plans.view', [
                        'tenant' => current_tenant_id(),
                        'record' => $record->id,
                    ])),

                Tables\Actions\Action::make('book')
                    ->label(__('treatment_plans::treatment_plans.actions.book_appointment'))
                    ->icon('heroicon-o-calendar')
                    ->color('success')
                    ->visible(fn (TreatmentPlan $record) => $record->isActive() && $record->items_needing_scheduling->count() > 0)
                    ->url(fn (TreatmentPlan $record) => route('filament.tenant.pages.create-booking', [
                        'tenant' => current_tenant_id(),
                        'booking_type' => 'treatment_plan',
                        'treatment_plan_id' => $record->id,
                    ])),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('treatment_plans::treatment_plans.items.empty'))
            ->emptyStateDescription('Create a treatment plan to track the patient\'s recommended services and progress.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('treatment_plans::treatment_plans.navigation_label');
    }
}
