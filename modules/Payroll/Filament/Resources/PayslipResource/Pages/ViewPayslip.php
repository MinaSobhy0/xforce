<?php

namespace Modules\Payroll\Filament\Resources\PayslipResource\Pages;

use Modules\Payroll\Filament\Resources\PayslipResource;
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
                ->url(fn () => route('payroll.payslip.download', $this->record))
                ->openUrlInNewTab(),
        ];
    }
}
