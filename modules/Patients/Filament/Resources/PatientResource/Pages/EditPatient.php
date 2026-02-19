<?php

namespace Modules\Patients\Filament\Resources\PatientResource\Pages;

use Modules\Patients\Filament\Resources\PatientResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPatient extends EditRecord
{
    protected static string $resource = PatientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load medical history data
        $medicalHistory = $this->record->medicalHistory;

        if ($medicalHistory) {
            $data['medicalHistory'] = $medicalHistory->toArray();
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Extract medical history data
        $medicalHistoryData = $data['medicalHistory'] ?? [];
        unset($data['medicalHistory']);

        // Store for after save
        $this->medicalHistoryData = $medicalHistoryData;

        return $data;
    }

    protected function afterSave(): void
    {
        // Update or create medical history record
        if (!empty($this->medicalHistoryData)) {
            $this->record->medicalHistory()->updateOrCreate(
                ['patient_id' => $this->record->id],
                array_merge($this->medicalHistoryData, [
                    'last_updated_by' => auth()->id(),
                    'last_updated_at' => now(),
                ])
            );
        }
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('patients::patients.messages.updated');
    }

    private array $medicalHistoryData = [];
}
