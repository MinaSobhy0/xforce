<?php

namespace Modules\TreatmentPlans\Filament\Resources\TreatmentPlanResource\RelationManagers;

use Modules\TreatmentPlans\Models\TreatmentPlanItem;
use Modules\Services\Models\Service;
use Modules\Auth\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $recordTitleAttribute = 'service.name';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('treatment_plans::treatment_plans.items.title');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('service_id')
                            ->label(__('treatment_plans::treatment_plans.fields.service'))
                            ->options(fn () => Service::active()
                                ->get()
                                ->mapWithKeys(fn ($service) => [
                                    $service->id => $service->translated_name
                                ]))
                            ->required()
                            ->searchable()
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('recommended_sessions')
                            ->label(__('treatment_plans::treatment_plans.fields.recommended_sessions'))
                            ->required()
                            ->numeric()
                            ->default(1)
                            ->minValue(1),

                        Forms\Components\TextInput::make('session_interval_days')
                            ->label(__('treatment_plans::treatment_plans.fields.session_interval_days'))
                            ->numeric()
                            ->default(config('treatment_plans.default_session_interval_days', 7)),
                    ]),

                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\Select::make('preferred_practitioner_id')
                            ->label(__('treatment_plans::treatment_plans.fields.preferred_practitioner'))
                            ->options(fn () => User::whereHas('roles', fn ($q) => $q->whereIn('name', ['doctor', 'therapist', 'practitioner']))
                                ->get()
                                ->pluck('name', 'id'))
                            ->searchable(),

                        Forms\Components\Select::make('preferred_day_of_week')
                            ->label(__('treatment_plans::treatment_plans.fields.preferred_day_of_week'))
                            ->multiple()
                            ->options([
                                0 => __('treatment_plans::treatment_plans.days_of_week.0'),
                                1 => __('treatment_plans::treatment_plans.days_of_week.1'),
                                2 => __('treatment_plans::treatment_plans.days_of_week.2'),
                                3 => __('treatment_plans::treatment_plans.days_of_week.3'),
                                4 => __('treatment_plans::treatment_plans.days_of_week.4'),
                                5 => __('treatment_plans::treatment_plans.days_of_week.5'),
                                6 => __('treatment_plans::treatment_plans.days_of_week.6'),
                            ]),

                        Forms\Components\Select::make('preferred_time_slot')
                            ->label(__('treatment_plans::treatment_plans.fields.preferred_time_slot'))
                            ->options(TreatmentPlanItem::TIME_SLOTS),
                    ]),

                Forms\Components\Textarea::make('notes')
                    ->label(__('treatment_plans::treatment_plans.fields.notes'))
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('service.translated_name')
                    ->label(__('treatment_plans::treatment_plans.fields.service'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('treatment_plans::treatment_plans.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => TreatmentPlanItem::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => TreatmentPlanItem::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('session_progress')
                    ->label(__('treatment_plans::treatment_plans.fields.completed_sessions'))
                    ->getStateUsing(fn (TreatmentPlanItem $record) => $record->session_progress_display),

                Tables\Columns\TextColumn::make('progress_percentage')
                    ->label(__('treatment_plans::treatment_plans.progress.overall'))
                    ->formatStateUsing(fn ($state) => $state . '%')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state >= 100 => 'success',
                        $state >= 50 => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('remaining_sessions')
                    ->label(__('treatment_plans::treatment_plans.fields.remaining_sessions')),

                Tables\Columns\TextColumn::make('session_interval_days')
                    ->label(__('treatment_plans::treatment_plans.fields.session_interval_days'))
                    ->suffix(' days'),

                Tables\Columns\TextColumn::make('next_suggested_date')
                    ->label('Next Suggested')
                    ->date()
                    ->visible(fn () => $this->getOwnerRecord()->isActive()),

                Tables\Columns\TextColumn::make('preferredPractitioner.name')
                    ->label(__('treatment_plans::treatment_plans.fields.preferred_practitioner'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('treatment_plans::treatment_plans.fields.status'))
                    ->options(TreatmentPlanItem::STATUSES),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('treatment_plans::treatment_plans.items.add'))
                    ->visible(fn () => $this->getOwnerRecord()->isEditable()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->isEditable()),

                Tables\Actions\Action::make('book')
                    ->label(__('treatment_plans::treatment_plans.actions.book_appointment'))
                    ->icon('heroicon-o-calendar')
                    ->color('primary')
                    ->visible(fn (TreatmentPlanItem $record) =>
                        $this->getOwnerRecord()->isActive() && $record->canBook()
                    )
                    ->url(fn (TreatmentPlanItem $record) => route('filament.tenant.pages.create-booking', [
                        'tenant' => current_tenant_id(),
                        'booking_type' => 'treatment_plan',
                        'treatment_plan_id' => $this->getOwnerRecord()->id,
                        'treatment_plan_item_id' => $record->id,
                    ])),

                Tables\Actions\Action::make('cancel')
                    ->label(__('treatment_plans::treatment_plans.actions.cancel'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (TreatmentPlanItem $record) =>
                        $this->getOwnerRecord()->isEditable() && !$record->isCompleted() && !$record->isCancelled()
                    )
                    ->requiresConfirmation()
                    ->action(function (TreatmentPlanItem $record) {
                        if ($record->cancel()) {
                            Notification::make()
                                ->title(__('treatment_plans::treatment_plans.messages.item_removed'))
                                ->success()
                                ->send();
                        }
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (TreatmentPlanItem $record) =>
                        $this->getOwnerRecord()->isDraft() && $record->completed_sessions === 0
                    ),
            ])
            ->bulkActions([])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }
}
