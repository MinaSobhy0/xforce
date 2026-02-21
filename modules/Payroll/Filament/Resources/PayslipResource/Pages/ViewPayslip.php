<?php

namespace Modules\Payroll\Filament\Resources\PayslipResource\Pages;

use Modules\Payroll\Filament\Resources\PayslipResource;
use Modules\Payroll\Services\SalarySlipPdfService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPayslip extends ViewRecord
{
    protected static string $resource = PayslipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_pdf')
                ->label(__('payroll::payroll.actions.download_payslip'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(function () {
                    $service = app(SalarySlipPdfService::class);
                    return $service->download($this->record);
                }),
        ];
    }
}
