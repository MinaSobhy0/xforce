<?php

namespace Modules\Prescriptions\Filament\Resources\PrescriptionResource\Pages;

use Modules\Prescriptions\Filament\Resources\PrescriptionResource;
use Modules\Prescriptions\Models\Prescription;
use Modules\Prescriptions\Services\PrescriptionPdfService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;

class ViewPrescription extends ViewRecord
{
    protected static string $resource = PrescriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->isEditable()),

            Actions\Action::make('finalize')
                ->label(__('prescriptions::prescription.actions.finalize'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('prescriptions::prescription.modals.finalize_heading'))
                ->modalDescription(__('prescriptions::prescription.modals.finalize_description'))
                ->visible(fn () => $this->record->isDraft() && $this->record->items()->count() > 0)
                ->action(function () {
                    $this->record->finalize();
                    Notification::make()
                        ->title(__('prescriptions::prescription.messages.finalized'))
                        ->success()
                        ->send();
                    $this->refreshFormData(['status', 'issued_at', 'finalized_at', 'finalized_by']);
                }),

            Actions\Action::make('print')
                ->label(__('prescriptions::prescription.actions.print'))
                ->icon('heroicon-o-printer')
                ->color('info')
                ->visible(fn () => $this->record->canPrint())
                ->action(function () {
                    $this->record->markPrinted();
                    $url = route('filament.tenant.prescriptions.print', ['prescription' => $this->record->id]);
                    $this->js("window.open('{$url}', '_blank')");
                }),

            Actions\Action::make('download')
                ->label(__('prescriptions::prescription.actions.download'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn () => $this->record->canPrint())
                ->action(function () {
                    $this->record->markPrinted();
                    $url = route('filament.tenant.prescriptions.download', ['prescription' => $this->record->id]);
                    $this->js("window.location.href = '{$url}'");
                }),

            Actions\Action::make('cancel')
                ->label(__('prescriptions::prescription.actions.cancel'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->canTransitionTo(Prescription::STATUS_CANCELLED))
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->label(__('prescriptions::prescription.fields.cancellation_reason'))
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->record->cancel($data['reason']);
                    Notification::make()
                        ->title(__('prescriptions::prescription.messages.cancelled'))
                        ->success()
                        ->send();
                    $this->refreshFormData(['status', 'cancelled_at', 'cancelled_by', 'cancellation_reason']);
                }),
        ];
    }
}
