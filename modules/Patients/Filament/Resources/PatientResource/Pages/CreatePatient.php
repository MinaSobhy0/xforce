<?php

namespace Modules\Patients\Filament\Resources\PatientResource\Pages;

use Modules\Patients\Filament\Resources\PatientResource;
use Modules\Patients\Models\PatientMedicalHistory;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Database\UniqueConstraintViolationException;

class CreatePatient extends CreateRecord
{
    protected static string $resource = PatientResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (UniqueConstraintViolationException $e) {
            // Check if it's a phone uniqueness violation
            if (str_contains($e->getMessage(), 'phone')) {
                Notification::make()
                    ->title(__('patients::patients.validation.phone_exists'))
                    ->danger()
                    ->send();

                $this->halt();
            }

            throw $e;
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Extract medical history data
        $medicalHistoryData = $data['medicalHistory'] ?? [];
        unset($data['medicalHistory']);

        // Store for after create
        $this->medicalHistoryData = $medicalHistoryData;

        return $data;
    }

    protected function afterCreate(): void
    {
        // Create medical history record
        if (!empty($this->medicalHistoryData)) {
            $this->record->medicalHistory()->create($this->medicalHistoryData);
        } else {
            // Always create an empty medical history record
            $this->record->medicalHistory()->create([]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('patients::patients.messages.created');
    }

    private array $medicalHistoryData = [];
}
