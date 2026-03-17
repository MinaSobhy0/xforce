<?php

namespace Modules\Equipment\Filament\Resources\EquipmentParameterTemplateResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Modules\Equipment\Filament\Resources\EquipmentParameterTemplateResource;

class EditEquipmentParameterTemplate extends EditRecord
{
    protected static string $resource = EquipmentParameterTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn () => ! $this->record->is_system),
        ];
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Show warning when editing a system template
        if ($this->record->is_system) {
            Notification::make()
                ->title(__('equipment::equipment.notifications.editing_system_template'))
                ->body(__('equipment::equipment.notifications.editing_system_template_body'))
                ->warning()
                ->persistent()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
