<?php

namespace App\Filament\SuperAdmin\Resources\OnboardingRequestResource\Pages;

use App\Filament\SuperAdmin\Resources\OnboardingRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOnboardingRequest extends ViewRecord
{
    protected static string $resource = OnboardingRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('Approve & Provision')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn() => $this->record->status === 'pending')
                ->action(function () {
                    $this->record->approve(auth()->id());
                    \Filament\Notifications\Notification::make()
                        ->title('Request approved')
                        ->success()
                        ->send();
                    $this->refreshFormData(['status']);
                }),

            Actions\EditAction::make()
                ->visible(fn() => $this->record->status === 'pending'),
        ];
    }
}
