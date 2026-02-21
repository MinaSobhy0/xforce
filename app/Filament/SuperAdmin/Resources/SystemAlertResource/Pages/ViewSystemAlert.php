<?php

namespace App\Filament\SuperAdmin\Resources\SystemAlertResource\Pages;

use App\Filament\SuperAdmin\Resources\SystemAlertResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;

class ViewSystemAlert extends BaseViewRecord
{
    protected static string $resource = SystemAlertResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\Action::make('resolve')
                ->label('Resolve')
                ->icon('heroicon-o-check')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn() => !$this->record->is_resolved)
                ->action(function () {
                    $this->record->resolve(auth()->id());
                    \Filament\Notifications\Notification::make()
                        ->title('Alert resolved')
                        ->success()
                        ->send();
                    $this->refreshFormData(['is_resolved', 'resolved_at', 'resolved_by']);
                }),
        ];
    }
}
