<?php

namespace Modules\Packages\Filament\Resources\PackageSubscriptionResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Modules\Packages\Models\PackageSubscription;
use Modules\Packages\Filament\Resources\PackageSubscriptionResource;

class ViewPackageSubscription extends ViewRecord
{
    protected static string $resource = PackageSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('freeze')
                ->label(__('packages::packages.actions.freeze'))
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->visible(fn () => $this->record->isActive())
                ->requiresConfirmation()
                ->form([
                    \Filament\Forms\Components\DatePicker::make('frozen_until')
                        ->label(__('packages::packages.subscriptions.fields.frozen_until'))
                        ->minDate(now()->addDay())
                        ->maxDate(now()->addDays(90)),
                ])
                ->action(function (array $data) {
                    $until = $data['frozen_until'] ? \Carbon\Carbon::parse($data['frozen_until']) : null;
                    if ($this->record->freeze($until)) {
                        Notification::make()
                            ->title(__('packages::packages.messages.frozen'))
                            ->success()
                            ->send();
                        $this->refreshFormData(['status']);
                    }
                }),

            Actions\Action::make('unfreeze')
                ->label(__('packages::packages.actions.unfreeze'))
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn () => $this->record->isFrozen())
                ->requiresConfirmation()
                ->action(function () {
                    if ($this->record->unfreeze()) {
                        Notification::make()
                            ->title(__('packages::packages.messages.unfrozen'))
                            ->success()
                            ->send();
                        $this->refreshFormData(['status']);
                    }
                }),

            Actions\Action::make('cancel')
                ->label(__('packages::packages.actions.cancel'))
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn () => $this->record->canTransitionTo(PackageSubscription::STATUS_CANCELLED))
                ->requiresConfirmation()
                ->form([
                    \Filament\Forms\Components\Textarea::make('cancellation_reason')
                        ->label(__('packages::packages.fields.cancellation_reason'))
                        ->required(),
                ])
                ->action(function (array $data) {
                    if ($this->record->cancel($data['cancellation_reason'])) {
                        Notification::make()
                            ->title(__('packages::packages.messages.cancelled'))
                            ->success()
                            ->send();
                        $this->refreshFormData(['status']);
                    }
                }),

            Actions\Action::make('book_session')
                ->label(__('packages::packages.actions.book_session'))
                ->icon('heroicon-o-calendar')
                ->color('primary')
                ->visible(fn () => $this->record->isActive() && $this->record->sessions_remaining > 0)
                ->url(fn () => route('filament.tenant.pages.create-booking', [
                    'patient_id' => $this->record->patient_id,
                    'package_subscription_id' => $this->record->id,
                ])),
        ];
    }
}
