<?php

namespace Modules\Payroll\Filament\Resources\PayrollRunResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use App\Filament\Resources\Pages\BaseEditRecord;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Filament\Resources\PayrollRunResource;
use Modules\Payroll\Services\PayrollCalculationService;

class EditPayrollRun extends BaseEditRecord
{
    protected static string $resource = PayrollRunResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),

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
                }),

            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->isEditable()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
