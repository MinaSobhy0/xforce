<?php

namespace App\Filament\SuperAdmin\Resources\AnnouncementResource\Pages;

use App\Filament\SuperAdmin\Resources\AnnouncementResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditAnnouncement extends BaseEditRecord
{
    use Translatable;

    protected static string $resource = AnnouncementResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            \Filament\Actions\LocaleSwitcher::make(),
            Actions\Action::make('send')
                ->label('Send Now')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn() => $this->record->status !== 'sent')
                ->action(function () {
                    $this->record->send();
                    \Filament\Notifications\Notification::make()
                        ->title('Announcement sent')
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
