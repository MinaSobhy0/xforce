<?php

namespace Modules\Prescriptions\Filament\Resources\PrescriptionResource\Pages;

use Modules\Prescriptions\Filament\Resources\PrescriptionResource;
use Modules\Prescriptions\Models\Prescription;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditPrescription extends EditRecord
{
    protected static string $resource = PrescriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),

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
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                }),

            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->isDraft()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function beforeFill(): void
    {
        if (!$this->record->isEditable()) {
            Notification::make()
                ->title(__('prescriptions::prescription.messages.not_editable'))
                ->warning()
                ->send();

            $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
        }
    }
}
