<?php

namespace Modules\Payroll\Filament\Resources\PayslipResource\Pages;

use App\Filament\Resources\Pages\BaseViewRecord;
use Filament\Actions;
use Filament\Notifications\Notification;
use Modules\Payroll\Filament\Resources\PayslipResource;
use Modules\Payroll\Services\PayrollCalculationService;

class ViewPayslip extends BaseViewRecord
{
    protected static string $resource = PayslipResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\Action::make('recalculate')
                ->label(__('payroll::payroll.actions.recalculate'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('payroll::payroll.actions.recalculate'))
                ->modalDescription(__('payroll::payroll.messages.recalculate_single_confirm'))
                ->visible(fn () => $this->record->isEditable() && $this->record->payrollRun)
                ->action(function () {
                    try {
                        $service = app(PayrollCalculationService::class);
                        $service->calculateForEmployee(
                            $this->record->payrollRun,
                            $this->record->staffProfile
                        );

                        Notification::make()
                            ->title(__('payroll::payroll.messages.payslip_recalculated'))
                            ->success()
                            ->send();

                        $this->redirect(PayslipResource::getUrl('view', ['record' => $this->record]));
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title(__('payroll::payroll.messages.calculation_failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('download_pdf')
                ->label(__('payroll::payroll.actions.download_payslip'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(fn () => "/payroll/payslip/{$this->record->id}/download")
                ->openUrlInNewTab(),
        ];
    }
}
