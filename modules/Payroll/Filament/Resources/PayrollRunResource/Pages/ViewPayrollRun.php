<?php

namespace Modules\Payroll\Filament\Resources\PayrollRunResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Filament\Resources\PayrollRunResource;

class ViewPayrollRun extends ViewRecord
{
    protected static string $resource = PayrollRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->isEditable()),

            Actions\Action::make('approve')
                ->label(__('payroll::payroll.actions.approve'))
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn () => $this->record->canTransitionTo(PayrollRun::STATUS_APPROVED))
                ->requiresConfirmation()
                ->action(function () {
                    if ($this->record->approve(auth()->id())) {
                        Notification::make()
                            ->title(__('payroll::payroll.messages.approved'))
                            ->success()
                            ->send();
                        $this->refreshFormData(['status']);
                    }
                }),

            Actions\Action::make('pay')
                ->label(__('payroll::payroll.actions.pay'))
                ->icon('heroicon-o-banknotes')
                ->color('info')
                ->visible(fn () => $this->record->canTransitionTo(PayrollRun::STATUS_PAID))
                ->requiresConfirmation()
                ->action(function () {
                    if ($this->record->markAsPaid(auth()->id())) {
                        Notification::make()
                            ->title(__('payroll::payroll.messages.paid'))
                            ->success()
                            ->send();
                        $this->refreshFormData(['status']);
                    }
                }),

            Actions\Action::make('cancel')
                ->label(__('payroll::payroll.actions.cancel'))
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn () => $this->record->canTransitionTo(PayrollRun::STATUS_CANCELLED))
                ->requiresConfirmation()
                ->action(function () {
                    if ($this->record->cancel()) {
                        Notification::make()
                            ->title(__('payroll::payroll.messages.cancelled'))
                            ->success()
                            ->send();
                        $this->refreshFormData(['status']);
                    }
                }),
        ];
    }
}
