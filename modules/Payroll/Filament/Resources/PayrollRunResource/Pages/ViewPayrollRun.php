<?php

namespace Modules\Payroll\Filament\Resources\PayrollRunResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;
use Filament\Notifications\Notification;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Filament\Resources\PayrollRunResource;
use Modules\Payroll\Services\PayrollCalculationService;

class ViewPayrollRun extends BaseViewRecord
{
    protected static string $resource = PayrollRunResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->isEditable()),

            Actions\Action::make('calculate')
                ->label(__('payroll::payroll.actions.calculate'))
                ->icon('heroicon-o-calculator')
                ->color('primary')
                ->visible(fn () => $this->record->status === PayrollRun::STATUS_DRAFT)
                ->requiresConfirmation()
                ->modalHeading(__('payroll::payroll.actions.calculate'))
                ->modalDescription(__('payroll::payroll.messages.calculate_confirm'))
                ->action(function () {
                    $this->record->startCalculation();

                    $service = app(PayrollCalculationService::class);
                    $result = $service->calculatePayrollRun($this->record);

                    if ($result['success']) {
                        Notification::make()
                            ->title(__('payroll::payroll.messages.calculated'))
                            ->body(__('payroll::payroll.messages.calculated_count', ['count' => $result['count']]))
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title(__('payroll::payroll.messages.calculation_failed'))
                            ->body($result['errors'][0]['error'] ?? 'Unknown error')
                            ->danger()
                            ->send();
                    }

                    $this->refreshFormData(['status', 'employee_count', 'total_base_salary_minor', 'total_net_salary_minor']);
                }),

            Actions\Action::make('recalculate')
                ->label(__('payroll::payroll.actions.recalculate'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn () => $this->record->canRecalculate())
                ->requiresConfirmation()
                ->modalHeading(__('payroll::payroll.actions.recalculate'))
                ->modalDescription(__('payroll::payroll.messages.recalculate_confirm'))
                ->action(function () {
                    $service = app(PayrollCalculationService::class);
                    $result = $service->calculatePayrollRun($this->record);

                    if ($result['success']) {
                        Notification::make()
                            ->title(__('payroll::payroll.messages.recalculated'))
                            ->body(__('payroll::payroll.messages.calculated_count', ['count' => $result['count']]))
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title(__('payroll::payroll.messages.calculation_failed'))
                            ->body($result['errors'][0]['error'] ?? 'Unknown error')
                            ->danger()
                            ->send();
                    }

                    $this->refreshFormData(['status', 'employee_count', 'total_base_salary_minor', 'total_net_salary_minor']);
                }),

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
