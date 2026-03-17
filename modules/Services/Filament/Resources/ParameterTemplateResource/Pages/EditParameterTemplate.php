<?php

namespace Modules\Services\Filament\Resources\ParameterTemplateResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Modules\Services\Filament\Resources\ParameterTemplateResource;

class EditParameterTemplate extends EditRecord
{
    protected static string $resource = ParameterTemplateResource::class;

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
                ->title(__('services::services.notifications.editing_system_template'))
                ->body(__('services::services.notifications.editing_system_template_body'))
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
