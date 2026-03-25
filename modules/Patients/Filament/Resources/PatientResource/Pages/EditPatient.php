<?php

namespace Modules\Patients\Filament\Resources\PatientResource\Pages;

use Modules\Patients\Filament\Resources\PatientResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Notifications\Notification;
use Illuminate\Database\UniqueConstraintViolationException;

class EditPatient extends BaseEditRecord
{
    protected static string $resource = PatientResource::class;

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
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

    protected function getEditHeaderActions(): array
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
