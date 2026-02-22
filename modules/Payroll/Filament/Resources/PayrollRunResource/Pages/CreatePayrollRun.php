<?php

namespace Modules\Payroll\Filament\Resources\PayrollRunResource\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Modules\Payroll\Filament\Resources\PayrollRunResource;
use Modules\Payroll\Services\PayrollCalculationService;

class CreatePayrollRun extends CreateRecord
{
    protected static string $resource = PayrollRunResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function afterCreate(): void
    {
        // Auto-calculate all payslips using PayrollCalculationService
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
                ->warning()
                ->send();
        }
    }
}
