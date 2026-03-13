<?php

namespace Modules\Booking\Filament\Resources\PractitionerTimeOffResource\Pages;

use Modules\Booking\Filament\Resources\PractitionerTimeOffResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditPractitionerTimeOff extends BaseEditRecord
{
    protected static string $resource = PractitionerTimeOffResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Redirect to view page if the record is not pending (cannot edit approved/rejected/cancelled)
        if (!$this->record->isPending()) {
            Notification::make()
                ->title(__('booking::time_off.messages.cannot_edit_non_pending'))
                ->warning()
                ->send();

            $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
        }
    }

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label(__('booking::time_off.actions.approve'))
                ->icon('heroicon-o-check')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->isPending())
                ->action(function () {
                    $this->record->approve(auth()->id());
                    Notification::make()
                        ->title(__('booking::time_off.messages.approved'))
                        ->success()
                        ->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Actions\Action::make('reject')
                ->label(__('booking::time_off.actions.reject'))
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->requiresConfirmation()
                ->form([
                    Forms\Components\Textarea::make('notes')
                        ->label(__('booking::time_off.fields.rejection_reason'))
                        ->required(),
                ])
                ->visible(fn () => $this->record->isPending())
                ->action(function (array $data) {
                    $this->record->reject(auth()->id(), $data['notes']);
                    Notification::make()
                        ->title(__('booking::time_off.messages.rejected'))
                        ->success()
                        ->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Actions\Action::make('cancel')
                ->label(__('booking::time_off.actions.cancel'))
                ->icon('heroicon-o-x-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->isApproved() || $this->record->isPending())
                ->action(function () {
                    if ($this->record->cancel()) {
                        Notification::make()
                            ->title(__('booking::time_off.messages.cancelled'))
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Cannot cancel this request')
                            ->danger()
                            ->send();
                    }
                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
