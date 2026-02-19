<?php

namespace Modules\Core\Resources\TenantResource\Pages;

use Modules\Core\Resources\TenantResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Modules\Core\Services\TenantService;
use Modules\Core\Models\TenantStatus;

class ViewTenant extends ViewRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('switchToTenant')
                ->label(__('Login to Tenant'))
                ->icon('heroicon-o-arrow-right-on-rectangle')
                ->color('info')
                ->url(fn () => route('tenant.switch', $this->getRecord()))
                ->openUrlInNewTab()
                ->visible(fn () => $this->getRecord()->status === TenantStatus::ACTIVE),

            Actions\Action::make('activateTenant')
                ->label(__('Activate'))
                ->icon('heroicon-o-check')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    $this->getRecord()->update(['status' => TenantStatus::ACTIVE]);

                    Notification::make()
                        ->title(__('Tenant activated'))
                        ->success()
                        ->send();
                })
                ->visible(fn () => $this->getRecord()->status !== TenantStatus::ACTIVE),

            Actions\Action::make('suspendTenant')
                ->label(__('Suspend'))
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription(__('This will suspend the tenant and prevent access to their system.'))
                ->action(function () {
                    $this->getRecord()->update(['status' => TenantStatus::SUSPENDED]);

                    Notification::make()
                        ->title(__('Tenant suspended'))
                        ->warning()
                        ->send();
                })
                ->visible(fn () => $this->getRecord()->status === TenantStatus::ACTIVE),

            Actions\Action::make('resetTenant')
                ->label(__('Reset Database'))
                ->icon('heroicon-o-arrow-path')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(__('This will delete all tenant data and recreate the database schema. This action cannot be undone.'))
                ->action(function () {
                    try {
                        $tenantService = app(TenantService::class);
                        $tenantService->resetTenantDatabase($this->getRecord());

                        Notification::make()
                            ->title(__('Tenant database reset'))
                            ->body(__('All data has been removed and schema recreated.'))
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title(__('Reset failed'))
                            ->body(__('Failed to reset tenant database: :error', ['error' => $e->getMessage()]))
                            ->danger()
                            ->send();
                    }
                }),

            Actions\DeleteAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TenantResource\Widgets\TenantUsageWidget::class,
        ];
    }
}