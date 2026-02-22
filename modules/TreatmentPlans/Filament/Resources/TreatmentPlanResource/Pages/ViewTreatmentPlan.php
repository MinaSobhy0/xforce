<?php

namespace Modules\TreatmentPlans\Filament\Resources\TreatmentPlanResource\Pages;

use Modules\TreatmentPlans\Filament\Resources\TreatmentPlanResource;
use Modules\TreatmentPlans\Models\TreatmentPlan;
use Filament\Actions;
use Filament\Notifications\Notification;
use App\Filament\Resources\Pages\BaseViewRecord;

class ViewTreatmentPlan extends BaseViewRecord
{
    protected static string $resource = TreatmentPlanResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->isEditable()),

            Actions\Action::make('activate')
                ->label(__('treatment_plans::treatment_plans.actions.activate'))
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn () => $this->record->canTransitionTo(TreatmentPlan::STATUS_ACTIVE))
                ->requiresConfirmation()
                ->modalHeading(__('treatment_plans::treatment_plans.confirmations.activate'))
                ->action(function () {
                    if ($this->record->activate()) {
                        Notification::make()
                            ->title(__('treatment_plans::treatment_plans.messages.activated'))
                            ->success()
                            ->send();
                    }
                }),

            Actions\Action::make('pause')
                ->label(__('treatment_plans::treatment_plans.actions.pause'))
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->visible(fn () => $this->record->isActive())
                ->requiresConfirmation()
                ->action(function () {
                    if ($this->record->pause()) {
                        Notification::make()
                            ->title(__('treatment_plans::treatment_plans.messages.paused'))
                            ->success()
                            ->send();
                    }
                }),

            Actions\Action::make('resume')
                ->label(__('treatment_plans::treatment_plans.actions.resume'))
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn () => $this->record->isPaused())
                ->requiresConfirmation()
                ->action(function () {
                    if ($this->record->resume()) {
                        Notification::make()
                            ->title(__('treatment_plans::treatment_plans.messages.resumed'))
                            ->success()
                            ->send();
                    }
                }),

            Actions\Action::make('complete')
                ->label(__('treatment_plans::treatment_plans.actions.complete'))
                ->icon('heroicon-o-check-circle')
                ->color('info')
                ->visible(fn () => $this->record->canTransitionTo(TreatmentPlan::STATUS_COMPLETED))
                ->requiresConfirmation()
                ->modalHeading(__('treatment_plans::treatment_plans.confirmations.complete'))
                ->action(function () {
                    if ($this->record->complete()) {
                        Notification::make()
                            ->title(__('treatment_plans::treatment_plans.messages.completed'))
                            ->success()
                            ->send();
                    }
                }),

            Actions\Action::make('cancel')
                ->label(__('treatment_plans::treatment_plans.actions.cancel'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->canTransitionTo(TreatmentPlan::STATUS_CANCELLED))
                ->requiresConfirmation()
                ->modalHeading(__('treatment_plans::treatment_plans.confirmations.cancel'))
                ->form([
                    \Filament\Forms\Components\Textarea::make('cancellation_reason')
                        ->label(__('treatment_plans::treatment_plans.fields.cancellation_reason'))
                        ->required(),
                ])
                ->action(function (array $data) {
                    if ($this->record->cancel($data['cancellation_reason'])) {
                        Notification::make()
                            ->title(__('treatment_plans::treatment_plans.messages.cancelled'))
                            ->success()
                            ->send();
                    }
                }),

            Actions\Action::make('book_appointment')
                ->label(__('treatment_plans::treatment_plans.actions.book_appointment'))
                ->icon('heroicon-o-calendar')
                ->color('primary')
                ->visible(fn () => $this->record->isActive() && $this->record->items_needing_scheduling->count() > 0)
                ->url(fn () => route('filament.tenant.pages.create-booking', [
                    'tenant' => current_tenant_id(),
                    'booking_type' => 'treatment_plan',
                    'treatment_plan_id' => $this->record->id,
                ])),
        ];
    }
}
