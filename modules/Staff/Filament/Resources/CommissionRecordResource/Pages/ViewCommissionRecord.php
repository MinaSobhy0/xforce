<?php

namespace Modules\Staff\Filament\Resources\CommissionRecordResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Modules\Staff\Filament\Resources\CommissionRecordResource;
use Modules\Staff\Models\StaffCommissionRecord;

class ViewCommissionRecord extends ViewRecord
{
    protected static string $resource = CommissionRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label(__('staff::staff.actions.approve'))
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn () => $this->record->canTransitionTo(StaffCommissionRecord::STATUS_APPROVED))
                ->requiresConfirmation()
                ->action(function () {
                    if ($this->record->approve(auth()->id())) {
                        Notification::make()
                            ->title(__('staff::staff.messages.approved'))
                            ->success()
                            ->send();
                        $this->refreshFormData(['status', 'approved_at', 'approved_by_user_id']);
                    }
                }),

            Actions\Action::make('mark_paid')
                ->label(__('staff::staff.actions.mark_paid'))
                ->icon('heroicon-o-banknotes')
                ->color('info')
                ->visible(fn () => $this->record->canTransitionTo(StaffCommissionRecord::STATUS_PAID))
                ->requiresConfirmation()
                ->action(function () {
                    if ($this->record->markAsPaid()) {
                        Notification::make()
                            ->title(__('staff::staff.messages.marked_paid'))
                            ->success()
                            ->send();
                        $this->refreshFormData(['status', 'paid_at']);
                    }
                }),

            Actions\Action::make('cancel')
                ->label(__('staff::staff.actions.cancel'))
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn () => $this->record->canTransitionTo(StaffCommissionRecord::STATUS_CANCELLED))
                ->requiresConfirmation()
                ->form([
                    \Filament\Forms\Components\Textarea::make('notes')
                        ->label(__('staff::staff.fields.cancellation_reason'))
                        ->required(),
                ])
                ->action(function (array $data) {
                    if ($this->record->cancel($data['notes'])) {
                        Notification::make()
                            ->title(__('staff::staff.messages.cancelled'))
                            ->success()
                            ->send();
                        $this->refreshFormData(['status', 'notes']);
                    }
                }),
        ];
    }
}
